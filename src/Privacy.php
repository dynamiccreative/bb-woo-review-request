<?php
/**
 * RGPD : export et effacement des données personnelles, texte de politique de confidentialité.
 *
 * @package BB\WooReviewRequest
 */

namespace BB\WooReviewRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Seule donnée propre au plugin : l'opposition aux demandes d'avis (empreinte de l'adresse + date).
 * Les dates d'envoi sont des métadonnées de commande, traitées avec la commande par WooCommerce.
 */
final class Privacy {

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_action( 'admin_init', array( $this, 'policy_content' ) );
	}

	/**
	 * Déclare l'exporteur.
	 *
	 * @param mixed $exporters Exporteurs.
	 * @return array<string, mixed>
	 */
	public function register_exporter( $exporters ): array {
		$exporters                          = is_array( $exporters ) ? $exporters : array();
		$exporters['bb-woo-review-request'] = array(
			'exporter_friendly_name' => __( 'Demandes d’avis', 'bb-woo-review-request' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Déclare l'effaceur.
	 *
	 * @param mixed $erasers Effaceurs.
	 * @return array<string, mixed>
	 */
	public function register_eraser( $erasers ): array {
		$erasers                          = is_array( $erasers ) ? $erasers : array();
		$erasers['bb-woo-review-request'] = array(
			'eraser_friendly_name' => __( 'Demandes d’avis', 'bb-woo-review-request' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export : date de l'opposition, le cas échéant.
	 *
	 * @param string $email_address Adresse.
	 * @return array{data:array<int,mixed>,done:bool}
	 */
	public function export( $email_address ): array {
		$at   = OptOut::opted_out_at( (string) $email_address );
		$data = array();
		if ( null !== $at ) {
			$data[] = array(
				'group_id'    => 'bb-woo-review-request',
				'group_label' => __( 'Demandes d’avis', 'bb-woo-review-request' ),
				'item_id'     => 'bb-wrr-optout',
				'data'        => array(
					array(
						'name'  => __( 'Opposition aux demandes d’avis', 'bb-woo-review-request' ),
						'value' => wp_date( (string) get_option( 'date_format' ), $at ),
					),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Effacement : l'opposition est conservée (l'effacer ferait reprendre les envois), sous forme d'empreinte.
	 *
	 * @param string $email_address Adresse.
	 * @return array{items_removed:bool,items_retained:bool,messages:string[],done:bool}
	 */
	public function erase( $email_address ): array {
		$retained = OptOut::is_opted_out( (string) $email_address );
		return array(
			'items_removed'  => false,
			'items_retained' => $retained,
			'messages'       => $retained ? array( __( 'L’opposition aux demandes d’avis est conservée, sous forme d’empreinte non réversible de l’adresse, pour continuer à la respecter.', 'bb-woo-review-request' ) ) : array(),
			'done'           => true,
		);
	}

	/**
	 * Texte suggéré pour la politique de confidentialité.
	 */
	public function policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			'BB Woo Review Request',
			wp_kses_post(
				wpautop(
					__( 'Quelques jours après la livraison de votre commande, nous pouvons vous envoyer un e-mail vous invitant à donner votre avis sur les produits achetés, éventuellement suivi d’une relance. Cet envoi repose sur notre intérêt légitime à recueillir l’avis de nos clients. Vous pouvez vous y opposer à tout moment grâce au lien présent dans chaque e-mail : nous conservons alors une empreinte non réversible de votre adresse pour respecter votre choix. Aucune mesure d’ouverture ou de clic n’est effectuée.', 'bb-woo-review-request' )
				)
			)
		);
	}
}

<?php
/**
 * Opposition aux demandes d'avis (lien présent dans chaque e-mail).
 *
 * @package BB\WooReviewRequest
 */

namespace BB\WooReviewRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Lien signé, sans adresse e-mail en clair : l'URL porte une empreinte HMAC de l'adresse et une clé
 * de vérification. Seules les empreintes sont conservées (option non chargée automatiquement).
 *
 * Un GET affiche une page de confirmation (les antivirus et webmails qui ouvrent les liens ne
 * désinscrivent personne) ; un POST enregistre l'opposition, y compris la désinscription en un clic
 * des clients mail (RFC 8058, en-tête List-Unsubscribe-Post).
 */
final class OptOut {

	public const OPTION = 'bb_wrr_optouts';
	public const QUERY  = 'bb_wrr_optout';

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'handle' ), 1 );
	}

	/**
	 * Empreinte d'une adresse (identifiant stocké et placé dans le lien).
	 *
	 * @param string $email Adresse e-mail.
	 */
	public static function id( string $email ): string {
		return hash_hmac( 'sha256', strtolower( trim( $email ) ), wp_salt( 'auth' ) );
	}

	/**
	 * Clé de vérification d'un identifiant.
	 *
	 * @param string $id Empreinte.
	 */
	private static function signature( string $id ): string {
		return substr( hash_hmac( 'sha256', 'optout|' . $id, wp_salt( 'secure_auth' ) ), 0, 32 );
	}

	/**
	 * Lien d'opposition pour une adresse (vide si pas d'adresse, ex. aperçu sans client).
	 *
	 * @param string $email Adresse e-mail (première adresse si plusieurs).
	 */
	public static function url( string $email ): string {
		$email = trim( explode( ',', $email )[0] );
		return '' !== $email ? self::url_for_id( self::id( $email ) ) : '';
	}

	/**
	 * Lien d'opposition pour une empreinte.
	 *
	 * @param string $id Empreinte.
	 */
	private static function url_for_id( string $id ): string {
		return add_query_arg(
			array(
				self::QUERY => $id,
				'key'       => self::signature( $id ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Le lien est-il authentique ?
	 *
	 * @param string $id  Empreinte.
	 * @param string $key Clé reçue.
	 */
	public static function verify( string $id, string $key ): bool {
		return 1 === preg_match( '/^[a-f0-9]{64}$/', $id ) && hash_equals( self::signature( $id ), $key );
	}

	/**
	 * L'adresse s'est-elle opposée aux demandes d'avis ?
	 *
	 * @param string $email Adresse e-mail.
	 */
	public static function is_opted_out( string $email ): bool {
		return '' !== trim( $email ) && null !== self::opted_out_at( $email );
	}

	/**
	 * Date de l'opposition (horodatage), null si aucune.
	 *
	 * @param string $email Adresse e-mail.
	 */
	public static function opted_out_at( string $email ): ?int {
		$list = self::all();
		$id   = self::id( $email );
		return isset( $list[ $id ] ) ? (int) $list[ $id ] : null;
	}

	/**
	 * Enregistre l'opposition d'une adresse.
	 *
	 * @param string $email Adresse e-mail.
	 */
	public static function opt_out( string $email ): void {
		self::record( self::id( $email ) );
	}

	/**
	 * Enregistre l'opposition d'une empreinte.
	 *
	 * @param string $id Empreinte.
	 */
	private static function record( string $id ): void {
		$list = self::all();
		if ( ! isset( $list[ $id ] ) ) {
			$list[ $id ] = time();
			update_option( self::OPTION, $list, false );
		}
	}

	/**
	 * Oppositions enregistrées.
	 *
	 * @return array<string, int> Empreinte => horodatage.
	 */
	private static function all(): array {
		$list = get_option( self::OPTION, array() );
		return is_array( $list ) ? $list : array();
	}

	/**
	 * Traite le lien d'opposition.
	 */
	public function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- lien signé par HMAC, ouvert depuis un e-mail : aucun nonce possible.
		if ( empty( $_GET[ self::QUERY ] ) ) {
			return;
		}
		$id        = sanitize_key( wp_unslash( $_GET[ self::QUERY ] ) );
		$key       = isset( $_GET['key'] ) ? sanitize_key( wp_unslash( $_GET['key'] ) ) : '';
		$post      = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );
		$one_click = isset( $_POST['List-Unsubscribe'] );
		// phpcs:enable

		nocache_headers();
		$title = __( 'Demandes d’avis', 'bb-woo-review-request' );

		if ( ! self::verify( $id, $key ) ) {
			wp_die( esc_html__( 'Ce lien n’est pas valide. Copiez l’adresse complète depuis l’e-mail reçu.', 'bb-woo-review-request' ), esc_html( $title ), array( 'response' => 400 ) );
		}

		if ( $post ) {
			self::record( $id );
			if ( $one_click ) {
				status_header( 200 );
				exit;
			}
		}

		if ( $post || isset( self::all()[ $id ] ) ) {
			wp_die( esc_html__( 'C’est noté : vous ne recevrez plus de demandes d’avis de notre part. Les e-mails liés à vos commandes (confirmation, expédition…) ne sont pas concernés.', 'bb-woo-review-request' ), esc_html( $title ), array( 'response' => 200 ) );
		}

		$form = sprintf(
			'<form method="post" action="%1$s"><p>%2$s</p><p><button type="submit" class="button">%3$s</button></p></form>',
			esc_url( self::url_for_id( $id ) ),
			esc_html__( 'Vous ne souhaitez plus recevoir de demandes d’avis après vos achats ? Confirmez ci-dessous. Les e-mails liés à vos commandes (confirmation, expédition…) ne sont pas concernés.', 'bb-woo-review-request' ),
			esc_html__( 'Ne plus recevoir de demandes d’avis', 'bb-woo-review-request' )
		);
		wp_die( $form, esc_html( $title ), array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- composants échappés ci-dessus.
	}
}

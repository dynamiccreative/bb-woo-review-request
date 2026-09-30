<?php
/**
 * Planification des demandes d'avis (Action Scheduler, fourni par WooCommerce).
 *
 * @package BB\WooReviewRequest
 */

namespace BB\WooReviewRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Demande planifiée au passage de la commande en « Terminée », relance planifiée après l'envoi.
 *
 * Les conditions sont revérifiées à l'envoi : une commande remboursée ou annulée entre-temps,
 * un e-mail désactivé ou un client opposé n'entraînent aucun envoi.
 */
final class Scheduler {

	public const HOOK  = 'bb_wrr_send';
	public const GROUP = 'bb-woo-review-request';

	/** Horodatage de la planification (une seule demande par commande). */
	public const META_SCHEDULED = '_bb_wrr_scheduled';

	/** Préfixe des horodatages d'envoi : _bb_wrr_sent_1 (demande), _bb_wrr_sent_2 (relance). */
	public const META_SENT = '_bb_wrr_sent_';

	public const STEP_REQUEST  = 1;
	public const STEP_REMINDER = 2;

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 10, 4 );
		add_action( self::HOOK, array( $this, 'send' ), 10, 2 );
	}

	/**
	 * Statuts qui déclenchent la demande.
	 *
	 * @return string[] Statuts sans le préfixe « wc- ».
	 */
	public static function trigger_statuses(): array {
		/**
		 * Statuts de commande qui déclenchent la demande d'avis (ex. ajouter un statut « livrée »).
		 *
		 * @param string[] $statuses Statuts sans le préfixe « wc- ».
		 *
		 * @since 0.1.0
		 */
		return array_map( 'strval', (array) apply_filters( 'bb_wrr_order_statuses', array( 'completed' ) ) );
	}

	/**
	 * Planifie la demande quand la commande atteint un statut déclencheur.
	 *
	 * @param int            $order_id Commande.
	 * @param string         $from     Ancien statut.
	 * @param string         $to       Nouveau statut.
	 * @param \WC_Order|null $order    Commande.
	 */
	public function on_status_changed( $order_id, $from, $to, $order = null ): void {
		if ( ! in_array( (string) $to, self::trigger_statuses(), true ) ) {
			return;
		}
		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order_id );
		if ( $order instanceof \WC_Order ) {
			$this->schedule( $order );
		}
	}

	/**
	 * Planifie la première demande (une seule fois par commande).
	 *
	 * @param \WC_Order $order Commande.
	 * @return bool Planifiée.
	 */
	public function schedule( \WC_Order $order ): bool {
		$email = Plugin::email();
		if ( ! $email || ! $email->is_enabled() || ! self::is_eligible( $order ) || $order->get_meta( self::META_SCHEDULED ) ) {
			return false;
		}

		$at = time() + $email->delay_days() * DAY_IN_SECONDS;
		as_schedule_single_action( $at, self::HOOK, self::args( $order, self::STEP_REQUEST ), self::GROUP );

		$order->update_meta_data( self::META_SCHEDULED, (string) $at );
		$order->save_meta_data();
		return true;
	}

	/**
	 * Commande concernée : commande client (ni remboursement, ni sous-commande), avec e-mail.
	 *
	 * @param \WC_Order $order Commande.
	 */
	public static function is_eligible( \WC_Order $order ): bool {
		$eligible = 'shop_order' === $order->get_type() && is_email( $order->get_billing_email() );

		/**
		 * La commande peut-elle recevoir une demande d'avis ? (ex. exclure les commandes B2B)
		 *
		 * @param bool      $eligible Commande client avec une adresse e-mail valide.
		 * @param \WC_Order $order    Commande.
		 *
		 * @since 0.1.0
		 */
		return (bool) apply_filters( 'bb_wrr_order_is_eligible', $eligible, $order );
	}

	/**
	 * Envoi planifié (demande ou relance).
	 *
	 * @param int $order_id Commande.
	 * @param int $step     Étape (1 = demande, 2 = relance).
	 */
	public function send( $order_id, $step = self::STEP_REQUEST ): void {
		$step  = self::STEP_REMINDER === (int) $step ? self::STEP_REMINDER : self::STEP_REQUEST;
		$order = wc_get_order( (int) $order_id );
		$email = Plugin::email();

		if ( ! $order instanceof \WC_Order || ! $email || ! self::is_eligible( $order ) ) {
			return;
		}
		// Commande remboursée, annulée… depuis la planification ; étape déjà envoyée.
		if ( ! $order->has_status( self::trigger_statuses() ) || $order->get_meta( self::META_SENT . $step ) ) {
			return;
		}

		if ( ! $email->trigger( $order->get_id(), self::STEP_REMINDER === $step ) ) {
			return;
		}

		$order->update_meta_data( self::META_SENT . $step, (string) time() );
		$order->save_meta_data();
		$order->add_order_note(
			self::STEP_REMINDER === $step
				? __( 'Relance de la demande d’avis envoyée au client.', 'bb-woo-review-request' )
				: __( 'Demande d’avis envoyée au client.', 'bb-woo-review-request' )
		);

		if ( self::STEP_REQUEST === $step && $email->reminder_days() > 0 ) {
			as_schedule_single_action( time() + $email->reminder_days() * DAY_IN_SECONDS, self::HOOK, self::args( $order, self::STEP_REMINDER ), self::GROUP );
		}
	}

	/**
	 * Arguments de l'action (Action Scheduler les passe dans cet ordre au callback).
	 *
	 * @param \WC_Order $order Commande.
	 * @param int       $step  Étape.
	 * @return array{order_id:int,step:int}
	 */
	private static function args( \WC_Order $order, int $step ): array {
		return array(
			'order_id' => $order->get_id(),
			'step'     => $step,
		);
	}
}

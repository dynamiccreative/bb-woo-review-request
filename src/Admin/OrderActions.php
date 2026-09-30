<?php
/**
 * Action « Envoyer la demande d'avis » sur la fiche commande.
 *
 * @package BB\WooReviewRequest
 */

namespace BB\WooReviewRequest\Admin;

use BB\WooReviewRequest\Plugin;
use BB\WooReviewRequest\Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Envoi immédiat depuis la liste « Actions de commande » (tester, ou relancer un client à la main).
 */
final class OrderActions {

	public const ACTION = 'bb_wrr_send_review_request';

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_order_actions', array( $this, 'add_action' ), 10, 2 );
		add_action( 'woocommerce_order_action_' . self::ACTION, array( $this, 'run' ) );
	}

	/**
	 * Ajoute l'action pour une commande éligible, si l'e-mail est activé.
	 *
	 * @param mixed          $actions Actions.
	 * @param \WC_Order|null $order   Commande (WooCommerce 8.0+).
	 * @return array<string, string>
	 */
	public function add_action( $actions, $order = null ): array {
		$actions = is_array( $actions ) ? $actions : array();
		$email   = Plugin::email();
		if ( $email && $email->is_enabled() && ( ! $order instanceof \WC_Order || Scheduler::is_eligible( $order ) ) ) {
			$actions[ self::ACTION ] = __( 'Envoyer la demande d’avis', 'bb-woo-review-request' );
		}
		return $actions;
	}

	/**
	 * Envoie la demande et consigne le résultat dans les notes de commande.
	 *
	 * @param mixed $order Commande.
	 */
	public function run( $order ): void {
		$email = Plugin::email();
		if ( ! $order instanceof \WC_Order || ! $email ) {
			return;
		}
		$order->add_order_note(
			$email->trigger( $order->get_id() )
				? __( 'Demande d’avis envoyée manuellement au client.', 'bb-woo-review-request' )
				: __( 'Demande d’avis non envoyée : client opposé aux demandes d’avis, adresse manquante ou aucun produit à noter.', 'bb-woo-review-request' )
		);
	}
}

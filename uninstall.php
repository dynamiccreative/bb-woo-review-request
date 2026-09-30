<?php
/**
 * Suppression du plugin : réglages, oppositions et envois planifiés (la désactivation conserve les réglages).
 * Les métadonnées de commande (_bb_wrr_*) sont laissées : de simples dates, sans effet sans le plugin.
 *
 * @package BB\WooReviewRequest
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'woocommerce_customer_review_request_settings' );
delete_option( 'bb_wrr_optouts' );
delete_option( 'bb_wrr_github_access_token' );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'bb_wrr_send', array(), 'bb-woo-review-request' );
}

<?php
/**
 * Plugin Name:          BB Woo Review Request
 * Plugin URI:           https://github.com/dynamiccreative/bb-woo-review-request
 * Description:          Demande d'avis après achat pour WooCommerce : e-mail envoyé quelques jours après la commande, relance facultative, lien d'opposition. En français, sans tracking.
 * Version:              0.1.0
 * Requires at least:    6.4
 * Tested up to:         7.1
 * Requires PHP:         8.1
 * Requires Plugins:     woocommerce
 * Author:               bleuebuzz — Mathieu Paillet
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          bb-woo-review-request
 * Domain Path:          /languages
 * WC requires at least: 8.0
 * WC tested up to:      11.1
 * Update URI:           https://github.com/dynamiccreative/bb-woo-review-request
 *
 * @package BB\WooReviewRequest
 */

defined( 'ABSPATH' ) || exit;

define( 'BB_WRR_VERSION', '0.1.0' );
define( 'BB_WRR_FILE', __FILE__ );
define( 'BB_WRR_DIR', plugin_dir_path( __FILE__ ) );

// Autoloader PSR-4 minimal : BB\WooReviewRequest\X\Y → src/X/Y.php.
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'BB\\WooReviewRequest\\';
		if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
			return;
		}
		$file = BB_WRR_DIR . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

// Compatibilité HPOS (commandes lues uniquement via l'API CRUD) et checkout en blocs (aucun code de checkout).
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BB_WRR_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', BB_WRR_FILE, true );
		}
	}
);

register_deactivation_hook( __FILE__, array( BB\WooReviewRequest\Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( BB\WooReviewRequest\Plugin::class, 'boot' ), 20 );

<?php
/**
 * Point d'entrée du plugin : enregistrement des services.
 *
 * @package BB\WooReviewRequest
 */

namespace BB\WooReviewRequest;

use BB\WooReviewRequest\Admin\OrderActions;
use BB\WooReviewRequest\Compat\MailLayout;
use BB\WooReviewRequest\Email\ReviewRequestEmail;

defined( 'ABSPATH' ) || exit;

/**
 * Démarrage du plugin.
 */
final class Plugin {

	public const MIN_WC_VERSION = '8.0';

	/** Clé de l'e-mail dans WC()->mailer()->get_emails(). */
	public const EMAIL_KEY = 'BB_WRR_Review_Request_Email';

	/**
	 * Démarrage sur `plugins_loaded`.
	 */
	public static function boot(): void {
		add_action( 'init', array( self::class, 'load_textdomain' ) );

		// Avant le contrôle WooCommerce : le plugin reste mis à jour même si WooCommerce est inactif.
		self::register_updater();

		if ( ! self::woocommerce_is_compatible() ) {
			add_action( 'admin_notices', array( self::class, 'missing_woocommerce_notice' ) );
			return;
		}

		add_filter( 'woocommerce_email_classes', array( self::class, 'register_email' ) );

		( new Scheduler() )->register();
		( new OptOut() )->register();
		( new Privacy() )->register();
		( new MailLayout() )->register();

		if ( is_admin() ) {
			( new OrderActions() )->register();
		}
	}

	/**
	 * Désactivation : les envois planifiés sont annulés (sinon Action Scheduler les marquerait en échec).
	 * Les commandes déjà planifiées ne le seront pas de nouveau à la réactivation.
	 */
	public static function deactivate(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Scheduler::HOOK, array(), Scheduler::GROUP );
		}
	}

	/**
	 * Déclare l'e-mail auprès de WooCommerce.
	 *
	 * @param mixed $emails E-mails.
	 * @return array<string, \WC_Email>
	 */
	public static function register_email( $emails ): array {
		$emails                    = is_array( $emails ) ? $emails : array();
		$emails[ self::EMAIL_KEY ] = new ReviewRequestEmail();
		return $emails;
	}

	/**
	 * Instance de l'e-mail « Demande d'avis » (chargée par le mailer WooCommerce).
	 */
	public static function email(): ?ReviewRequestEmail {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}
		$email = WC()->mailer()->get_emails()[ self::EMAIL_KEY ] ?? null;
		return $email instanceof ReviewRequestEmail ? $email : null;
	}

	/**
	 * Mises à jour depuis GitHub (mécanisme maison des plugins Dynamic Creative / bleuebuzz).
	 * La version publiée est celle de l'en-tête du fichier principal sur la branche `main`.
	 */
	private static function register_updater(): void {
		require_once BB_WRR_DIR . 'lib/GitHubUpdater.php';

		// Voir BB Woo Mail Layout : chemin reconstruit à partir des noms réels (Windows, liens symboliques).
		$file    = WP_PLUGIN_DIR . '/' . basename( dirname( BB_WRR_FILE ) ) . '/' . basename( BB_WRR_FILE );
		$updater = new \BB_WRR_GitHubUpdater( $file );
		$updater->setBranch( 'main' );
		$updater->setAccessToken( (string) get_option( 'bb_wrr_github_access_token', '' ) );
		$updater->setPluginIcon( 'https://raw.githubusercontent.com/dynamiccreative/setting-plugin/main/img/icon-256x256.png' );
		$updater->setPluginBannerSmall( 'https://raw.githubusercontent.com/dynamiccreative/setting-plugin/main/img/banner-1544x500.png' );
		$updater->setPluginBannerLarge( 'https://raw.githubusercontent.com/dynamiccreative/setting-plugin/main/img/banner-1544x500.png' );
		$updater->setChangelog( 'CHANGELOG.md' );
		$updater->add();
	}

	/**
	 * WooCommerce est-il actif, en version suffisante ?
	 */
	private static function woocommerce_is_compatible(): bool {
		if ( ! class_exists( 'WooCommerce' ) || ! defined( 'WC_VERSION' ) ) {
			return false;
		}
		return version_compare( (string) constant( 'WC_VERSION' ), self::MIN_WC_VERSION, '>=' );
	}

	/**
	 * Charge les traductions du plugin (chaînes source en français).
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'bb-woo-review-request', false, basename( dirname( BB_WRR_FILE ) ) . '/languages' );
	}

	/**
	 * Notice si WooCommerce est absent ou trop ancien.
	 */
	public static function missing_woocommerce_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: version minimale de WooCommerce. */
					__( 'BB Woo Review Request nécessite WooCommerce %s ou supérieur. Le plugin est inactif.', 'bb-woo-review-request' ),
					self::MIN_WC_VERSION
				)
			)
		);
	}
}

<?php
/**
 * Intégration optionnelle à BB Woo Mail Layout.
 *
 * @package BB\WooReviewRequest
 */

namespace BB\WooReviewRequest\Compat;

use BB\WooReviewRequest\Email\ReviewRequestEmail;
use BB\WooReviewRequest\OptOut;
use BB\WooReviewRequest\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Sans BB Woo Mail Layout, l'e-mail utilise le gabarit WooCommerce et affiche lui-même intro et bouton.
 *
 * Avec : le layout habille l'e-mail comme les autres (il le détecte seul) et affiche dans son en-tête
 * l'intro et le bouton, fournis ici par ses filtres publics. L'intro reste modifiable dans le layout
 * (onglet E-mails) ; à défaut, c'est celle des réglages de l'e-mail. Le lien d'opposition passe dans
 * le pied de page du layout (tout en bas) quand celui-ci est affiché.
 */
final class MailLayout {

	private const PLUGIN_CLASS = '\\BB\\WooMailLayout\\Plugin';

	/**
	 * Hooks, seulement si BB Woo Mail Layout est installé.
	 */
	public function register(): void {
		if ( ! class_exists( self::PLUGIN_CLASS ) ) {
			return;
		}
		add_filter( 'bb_email_default_texts', array( $this, 'default_texts' ) );
		add_filter( 'bb_email_intro_text', array( $this, 'intro_text' ), 10, 2 );
		add_filter( 'bb_email_action_button', array( $this, 'action_button' ), 10, 2 );
		add_filter( 'bb_email_footer_text', array( $this, 'footer_text' ), 10, 2 );
	}

	/**
	 * Le layout met-il en forme cet e-mail (layout actif pour cet e-mail, format HTML) ?
	 *
	 * @param \WC_Email $email E-mail.
	 */
	public static function applies( \WC_Email $email ): bool {
		if ( ! class_exists( self::PLUGIN_CLASS ) ) {
			return false;
		}
		try {
			return (bool) \BB\WooMailLayout\Plugin::instance()->renderer()->applies_to( $email );
		} catch ( \Throwable $e ) {
			// Layout présent mais inactif (WooCommerce trop ancien…) : rendu WooCommerce standard.
			return false;
		}
	}

	/**
	 * Intro par défaut de l'e-mail, affichée et utilisée par le layout.
	 *
	 * @param mixed $texts Identifiant => texte.
	 * @return mixed
	 */
	public function default_texts( $texts ) {
		$email = Plugin::email();
		if ( is_array( $texts ) && $email ) {
			$texts[ ReviewRequestEmail::ID ] = $email->intro_raw();
		}
		return $texts;
	}

	/**
	 * Relance : intro de la relance, sauf si l'intro a été personnalisée dans le layout.
	 *
	 * @param mixed $text  Texte brut.
	 * @param mixed $email E-mail.
	 * @return mixed
	 */
	public function intro_text( $text, $email ) {
		if ( $email instanceof ReviewRequestEmail && $email->is_reminder && $text === $email->intro_raw() ) {
			return $email->intro_raw( true );
		}
		return $text;
	}

	/**
	 * Bouton « Donner mon avis » sous l'intro, sauf si un bouton est réglé dans le layout.
	 *
	 * @param mixed $button Bouton réglé.
	 * @param mixed $email  E-mail.
	 * @return mixed
	 */
	public function action_button( $button, $email ) {
		return $email instanceof ReviewRequestEmail && null === $button ? $email->button() : $button;
	}

	/**
	 * Lien d'opposition ajouté en dernier au texte du pied de page du layout.
	 *
	 * @param mixed $text  HTML du texte de pied de page.
	 * @param mixed $email E-mail.
	 * @return mixed
	 */
	public function footer_text( $text, $email ) {
		if ( ! $email instanceof ReviewRequestEmail || ! is_string( $text ) ) {
			return $text;
		}
		$url = OptOut::url( (string) $email->recipient );
		if ( '' === $url ) {
			return $text;
		}
		return $text . sprintf(
			'<p>%1$s <a href="%2$s" target="_blank">%3$s</a></p>',
			esc_html__( 'Vous ne souhaitez plus recevoir de demandes d’avis ?', 'bb-woo-review-request' ),
			esc_url( $url ),
			esc_html__( 'Ne plus recevoir ces e-mails', 'bb-woo-review-request' )
		);
	}

	/**
	 * Le pied de page du layout est-il affiché (il porte alors le lien d'opposition) ?
	 */
	public static function shows_footer(): bool {
		try {
			return 'yes' === ( \BB\WooMailLayout\Settings\Options::all()['show_footer'] ?? '' );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Couleurs de la charte du layout (bouton, bordures, texte atténué).
	 *
	 * @return array{button:string,border:string,muted:string}|null
	 */
	public static function colors(): ?array {
		try {
			$colors = \BB\WooMailLayout\Plugin::instance()->renderer()->colors( \BB\WooMailLayout\Settings\Options::all() );
			return array(
				'button' => (string) $colors['button'],
				'border' => (string) $colors['border'],
				'muted'  => (string) $colors['muted'],
			);
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Couleurs sans le layout : couleur de base des e-mails WooCommerce.
	 *
	 * @return array{button:string,border:string,muted:string}
	 */
	public static function default_colors(): array {
		return array(
			'button' => (string) get_option( 'woocommerce_email_base_color', '#7f54b3' ),
			'border' => '#e5e5e5',
			'muted'  => '#767676',
		);
	}
}

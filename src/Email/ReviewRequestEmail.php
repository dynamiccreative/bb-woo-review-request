<?php
/**
 * E-mail « Demande d'avis » (client).
 *
 * @package BB\WooReviewRequest
 */

namespace BB\WooReviewRequest\Email;

use BB\WooReviewRequest\Compat\MailLayout;
use BB\WooReviewRequest\OptOut;
use BB\WooReviewRequest\Reviews;

defined( 'ABSPATH' ) || exit;

/**
 * E-mail WooCommerce standard : réglages dans WooCommerce → Réglages → E-mails → Demande d'avis,
 * template surchargeable par le thème (woocommerce/emails/customer-review-request.php).
 */
class ReviewRequestEmail extends \WC_Email {

	public const ID = 'customer_review_request';

	/** Destination du lien : fiche produit (avis WooCommerce) ou lien externe (Google, Trustpilot…). */
	public const DESTINATION_PRODUCT  = 'product';
	public const DESTINATION_EXTERNAL = 'external';

	/**
	 * Envoi en cours : relance ?
	 *
	 * @var bool
	 */
	public bool $is_reminder = false;

	/**
	 * Produits à noter (null = calculés à la demande depuis la commande, ex. aperçu).
	 *
	 * @var \WC_Product[]|null
	 */
	public ?array $products = null;

	/**
	 * Constructeur.
	 */
	public function __construct() {
		$this->id             = self::ID;
		$this->customer_email = true;
		$this->title          = __( 'Demande d’avis', 'bb-woo-review-request' );
		$this->description    = __( 'Envoyé au client quelques jours après le passage de sa commande en « Terminée », pour l’inviter à donner son avis sur les produits achetés. Une relance facultative peut suivre. Chaque e-mail contient un lien d’opposition.', 'bb-woo-review-request' );
		$this->template_html  = 'emails/customer-review-request.php';
		$this->template_plain = 'emails/plain/customer-review-request.php';
		$this->template_base  = BB_WRR_DIR . 'templates/';
		$this->placeholders   = array(
			'{customer_first_name}' => '',
			'{order_number}'        => '',
			'{order_date}'          => '',
		);

		parent::__construct();
	}

	/*
	 * ------------------------------------------------------------------
	 * Envoi
	 * ------------------------------------------------------------------
	 */

	/**
	 * Envoie la demande (ou la relance) pour une commande.
	 *
	 * Rien n'est envoyé si l'e-mail est désactivé, si le client s'est opposé aux demandes d'avis,
	 * ou s'il n'y a aucun produit à noter (lien vers les fiches produit).
	 *
	 * @param int  $order_id Commande.
	 * @param bool $reminder Relance.
	 * @return bool Envoyé.
	 */
	public function trigger( $order_id, $reminder = false ): bool {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		$this->setup_locale();

		$this->object      = $order;
		$this->recipient   = $order->get_billing_email();
		$this->is_reminder = (bool) $reminder;
		$this->products    = Reviews::products_to_review( $order, $this->skips_reviewed() );

		$sent = false;
		if ( $this->can_send() ) {
			$sent = (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();

		return $sent;
	}

	/**
	 * Conditions d'envoi (après trigger()).
	 */
	private function can_send(): bool {
		$recipient = (string) $this->recipient;
		if ( ! $this->is_enabled() || '' === $recipient || OptOut::is_opted_out( $recipient ) ) {
			return false;
		}
		return self::DESTINATION_EXTERNAL === $this->destination() || array() !== $this->products();
	}

	/**
	 * En-têtes : désinscription en un clic (RFC 8058), exigée par Gmail et Yahoo pour les envois en nombre.
	 *
	 * @return string
	 */
	public function get_headers() {
		$headers = rtrim( (string) parent::get_headers() ) . "\r\n";
		$url     = OptOut::url( (string) $this->recipient );
		if ( '' !== $url ) {
			$headers .= 'List-Unsubscribe: <' . esc_url_raw( $url ) . ">\r\n";
			$headers .= "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
		}
		return $headers;
	}

	/*
	 * ------------------------------------------------------------------
	 * Contenu
	 * ------------------------------------------------------------------
	 */

	/**
	 * Placeholders calculés depuis la commande (aussi pour l'aperçu, qui ne passe pas par trigger()).
	 */
	private function sync_placeholders(): void {
		if ( ! $this->object instanceof \WC_Order ) {
			return;
		}
		$order                                       = $this->object;
		$this->placeholders['{customer_first_name}'] = $order->get_billing_first_name();
		$this->placeholders['{order_number}']        = (string) $order->get_order_number();
		$this->placeholders['{order_date}']          = $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '';
	}

	/**
	 * Sujet (relance : sujet dédié).
	 *
	 * @return string
	 */
	public function get_subject() {
		$this->sync_placeholders();
		if ( ! $this->is_reminder ) {
			return parent::get_subject();
		}
		$subject = $this->format_string( (string) $this->get_option( 'subject_reminder', $this->get_default_reminder_subject() ) );

		/**
		 * Filtre WooCommerce standard du sujet, appliqué aussi à la relance (voir WC_Email::get_subject()).
		 *
		 * @since 0.1.0
		 */
		return (string) apply_filters( 'woocommerce_email_subject_' . $this->id, $subject, $this->object, $this ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- hook WooCommerce.
	}

	/**
	 * Titre.
	 *
	 * @return string
	 */
	public function get_heading() {
		$this->sync_placeholders();
		return parent::get_heading();
	}

	/**
	 * Sujet par défaut.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Que pensez-vous de votre commande sur {site_title} ?', 'bb-woo-review-request' );
	}

	/**
	 * Sujet par défaut de la relance.
	 */
	public function get_default_reminder_subject(): string {
		return __( 'Petit rappel : votre avis nous serait précieux', 'bb-woo-review-request' );
	}

	/**
	 * Titre par défaut.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Votre avis compte', 'bb-woo-review-request' );
	}

	/**
	 * Texte d'intro par défaut.
	 *
	 * @param bool $reminder Relance.
	 */
	public function get_default_intro( bool $reminder = false ): string {
		return $reminder
			? __( "Bonjour {customer_first_name},\n\nNous nous permettons de revenir vers vous : si vous avez un instant, votre avis sur votre commande n° {order_number} nous serait précieux. Il ne vous prendra qu’une minute.", 'bb-woo-review-request' )
			: __( "Bonjour {customer_first_name},\n\nVous avez reçu votre commande n° {order_number} il y a quelques jours : nous espérons qu’elle vous donne entière satisfaction.\n\nVotre avis aide les autres clients à faire leur choix et nous aide à nous améliorer. Il ne vous prendra qu’une minute.", 'bb-woo-review-request' );
	}

	/**
	 * Texte d'intro brut (placeholders non remplacés) : texte saisi, sinon texte par défaut.
	 *
	 * @param bool $reminder Relance.
	 */
	public function intro_raw( bool $reminder = false ): string {
		$key  = $reminder ? 'intro_reminder' : 'intro';
		$text = trim( (string) $this->get_option( $key, '' ) );
		return '' !== $text ? $text : $this->get_default_intro( $reminder );
	}

	/**
	 * Texte d'intro de l'envoi en cours, placeholders remplacés (texte brut).
	 */
	public function intro_text(): string {
		$this->sync_placeholders();
		return $this->format_string( $this->intro_raw( $this->is_reminder ) );
	}

	/**
	 * Produits à noter.
	 *
	 * @return \WC_Product[]
	 */
	public function products(): array {
		if ( null === $this->products ) {
			$this->products = $this->object instanceof \WC_Order ? Reviews::products_to_review( $this->object, $this->skips_reviewed() ) : array();
		}
		return $this->products;
	}

	/**
	 * Bouton principal : lien externe, ou produit à noter s'il n'y en a qu'un.
	 *
	 * Avec plusieurs produits, pas de bouton global (il favoriserait arbitrairement le premier) :
	 * chaque ligne de la liste a le sien.
	 *
	 * @return array{url:string,label:string}|null
	 */
	public function button(): ?array {
		$url = '';
		if ( self::DESTINATION_EXTERNAL === $this->destination() ) {
			$url = $this->external_url();
		} elseif ( 1 === count( $this->products() ) ) {
			$url = Reviews::review_url( $this->products()[0] );
		}
		return '' !== $url ? array(
			'url'   => $url,
			'label' => $this->button_label(),
		) : null;
	}

	/**
	 * Contenu HTML.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->template_args( false ), '', $this->template_base );
	}

	/**
	 * Contenu texte.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->template_args( true ), '', $this->template_base );
	}

	/**
	 * Variables des templates.
	 *
	 * @param bool $plain_text Version texte.
	 * @return array<string, mixed>
	 */
	private function template_args( bool $plain_text ): array {
		$layout = ! $plain_text && MailLayout::applies( $this );
		return array(
			'order'              => $this->object,
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => false,
			'plain_text'         => $plain_text,
			'email'              => $this,
			'intro'              => $this->intro_text(),
			'button'             => $this->button(),
			'products'           => self::DESTINATION_PRODUCT === $this->destination() ? $this->products() : array(),
			'optout_url'         => OptOut::url( (string) $this->recipient ),
			// Avec BB Woo Mail Layout, l'intro et le bouton sont affichés par le layout (en-tête).
			'layout'             => $layout,
			// Couleurs de la charte du layout, sinon couleur de base des e-mails WooCommerce.
			'colors'             => ( $layout ? MailLayout::colors() : null ) ?? MailLayout::default_colors(),
			// Avec le pied de page du layout, le lien d'opposition y est affiché (tout en bas).
			'optout_in_footer'   => $layout && MailLayout::shows_footer(),
		);
	}

	/*
	 * ------------------------------------------------------------------
	 * Réglages
	 * ------------------------------------------------------------------
	 */

	/**
	 * Délai avant la demande, en jours après le passage en « Terminée ».
	 */
	public function delay_days(): int {
		return max( 1, absint( $this->get_option( 'delay', 7 ) ) );
	}

	/**
	 * Délai de la relance, en jours après la demande (0 = pas de relance).
	 */
	public function reminder_days(): int {
		return absint( $this->get_option( 'reminder_delay', 0 ) );
	}

	/**
	 * Destination du lien (lien externe seulement s'il est renseigné).
	 */
	public function destination(): string {
		return self::DESTINATION_EXTERNAL === $this->get_option( 'destination' ) && '' !== $this->external_url()
			? self::DESTINATION_EXTERNAL
			: self::DESTINATION_PRODUCT;
	}

	/**
	 * Lien externe (page d'avis Google, Trustpilot, Avis Vérifiés…).
	 */
	public function external_url(): string {
		return esc_url_raw( (string) $this->get_option( 'external_url', '' ), array( 'http', 'https' ) );
	}

	/**
	 * Libellé du bouton.
	 */
	public function button_label(): string {
		$label = trim( (string) $this->get_option( 'button_label', '' ) );
		return '' !== $label ? $label : __( 'Donner mon avis', 'bb-woo-review-request' );
	}

	/**
	 * Ne pas redemander d'avis sur un produit déjà noté par le client.
	 */
	public function skips_reviewed(): bool {
		return 'no' !== $this->get_option( 'skip_reviewed', 'yes' );
	}

	/**
	 * Champs de réglage (WooCommerce → Réglages → E-mails → Demande d'avis).
	 *
	 * @return void
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		/* translators: %s: liste des placeholders. */
		$placeholder_text = sprintf( __( 'Placeholders disponibles : %s', 'bb-woo-review-request' ), '<code>{site_title}, {customer_first_name}, {order_number}, {order_date}</code>' );
		$base             = $this->form_fields;
		$base['enabled']  = array_merge( $base['enabled'], array( 'default' => 'no' ) );

		$fields = array(
			'enabled'          => $base['enabled'],
			'delay'            => array(
				'title'             => __( 'Délai d’envoi (jours)', 'bb-woo-review-request' ),
				'type'              => 'number',
				'description'       => __( 'Nombre de jours après le passage de la commande en « Terminée ». Laissez au client le temps de recevoir et d’essayer ses produits.', 'bb-woo-review-request' ),
				'desc_tip'          => true,
				'default'           => '7',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			),
			'reminder_delay'   => array(
				'title'             => __( 'Relance (jours)', 'bb-woo-review-request' ),
				'type'              => 'number',
				'description'       => __( 'Nombre de jours après la demande. 0 = pas de relance. La relance n’est pas envoyée si le client a déjà noté tous ses produits.', 'bb-woo-review-request' ),
				'desc_tip'          => true,
				'default'           => '0',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			),
			'destination'      => array(
				'title'   => __( 'Lien « Donner mon avis »', 'bb-woo-review-request' ),
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => self::DESTINATION_PRODUCT,
				'options' => array(
					self::DESTINATION_PRODUCT  => __( 'Fiches produit (avis WooCommerce)', 'bb-woo-review-request' ),
					self::DESTINATION_EXTERNAL => __( 'Lien externe (Google, Trustpilot, Avis Vérifiés…)', 'bb-woo-review-request' ),
				),
			),
			'external_url'     => array(
				'title'       => __( 'Lien externe', 'bb-woo-review-request' ),
				'type'        => 'url',
				'description' => __( 'Adresse de votre page d’avis (ex. lien « Écrire un avis » de votre fiche Google). Utilisé seulement si « Lien externe » est choisi.', 'bb-woo-review-request' ),
				'desc_tip'    => true,
				'placeholder' => 'https://',
				'default'     => '',
			),
			'button_label'     => array(
				'title'       => __( 'Libellé du bouton', 'bb-woo-review-request' ),
				'type'        => 'text',
				'placeholder' => __( 'Donner mon avis', 'bb-woo-review-request' ),
				'default'     => '',
			),
			'skip_reviewed'    => array(
				'title'   => __( 'Produits déjà notés', 'bb-woo-review-request' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ne pas redemander d’avis sur un produit que le client a déjà noté', 'bb-woo-review-request' ),
				'default' => 'yes',
			),
			'subject'          => $base['subject'],
			'heading'          => $base['heading'],
			'intro'            => array(
				'title'       => __( 'Texte d’introduction', 'bb-woo-review-request' ),
				'type'        => 'textarea',
				'css'         => 'width:400px; height:110px;',
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_intro(),
				'default'     => '',
			),
			'subject_reminder' => array(
				'title'       => __( 'Sujet de la relance', 'bb-woo-review-request' ),
				'type'        => 'text',
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_reminder_subject(),
				'default'     => '',
			),
			'intro_reminder'   => array(
				'title'       => __( 'Texte d’introduction de la relance', 'bb-woo-review-request' ),
				'type'        => 'textarea',
				'css'         => 'width:400px; height:90px;',
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_intro( true ),
				'default'     => '',
			),
		);

		// Champs standard restants (contenu additionnel, type d'e-mail, copies…), dans leur ordre.
		$this->form_fields = array_merge( $fields, array_diff_key( $base, $fields ) );
	}

	/**
	 * Lien externe : http(s) uniquement.
	 *
	 * @param string $key   Clé du champ.
	 * @param mixed  $value Valeur postée.
	 */
	public function validate_external_url_field( $key, $value ): string {
		return esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
	}

	/**
	 * Délais : entiers positifs.
	 *
	 * @param string $key   Clé du champ.
	 * @param mixed  $value Valeur postée.
	 */
	public function validate_delay_field( $key, $value ): string {
		return (string) max( 1, absint( $value ) );
	}

	/**
	 * Délai de relance : entier positif ou nul.
	 *
	 * @param string $key   Clé du champ.
	 * @param mixed  $value Valeur postée.
	 */
	public function validate_reminder_delay_field( $key, $value ): string {
		return (string) absint( $value );
	}
}

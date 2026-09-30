<?php
/**
 * Demande d'avis (client), version texte.
 *
 * Surchargeable par le thème : woocommerce/emails/plain/customer-review-request.php.
 *
 * @package BB\WooReviewRequest
 * @var WC_Order|null                       $order
 * @var string                              $email_heading
 * @var string                              $additional_content
 * @var WC_Email                            $email
 * @var string                              $intro      Texte brut, placeholders remplacés.
 * @var array{url:string,label:string}|null $button
 * @var WC_Product[]                        $products
 * @var string                              $optout_url
 */

use BB\WooReviewRequest\Reviews;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- version texte : aucune sortie HTML, contenu issu de texte brut.

echo '= ' . wp_strip_all_tags( $email_heading ) . " =\n\n";

echo $intro . "\n\n";

if ( $button ) {
	if ( 1 === count( $products ) ) {
		echo wp_strip_all_tags( $products[0]->get_name() ) . "\n";
	}
	echo $button['label'] . ' : ' . esc_url_raw( $button['url'] ) . "\n\n";
}

// Plusieurs produits : pas de bouton principal, un lien par produit.
if ( count( $products ) > 1 ) {
	foreach ( $products as $bb_product ) {
		echo '- ' . wp_strip_all_tags( $bb_product->get_name() ) . ' : ' . esc_url_raw( Reviews::review_url( $bb_product ) ) . "\n";
	}
	echo "\n";
}

if ( $additional_content ) {
	echo wp_strip_all_tags( wptexturize( $additional_content ) ) . "\n\n";
}

if ( $optout_url ) {
	echo __( 'Vous ne souhaitez plus recevoir de demandes d’avis ?', 'bb-woo-review-request' ) . ' ' . esc_url_raw( $optout_url ) . "\n\n";
}

echo "----------------------------------------\n\n";

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );

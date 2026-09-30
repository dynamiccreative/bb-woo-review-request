<?php
/**
 * Demande d'avis (client), version HTML.
 *
 * Surchargeable par le thème : woocommerce/emails/customer-review-request.php.
 * Avec BB Woo Mail Layout, l'intro et le bouton sont affichés par le layout ($layout vrai).
 *
 * Un seul produit : rappel du produit sous le bouton principal (qui y mène).
 * Plusieurs produits : pas de bouton principal, un bouton « Noter ce produit » par ligne.
 *
 * @package BB\WooReviewRequest
 * @var WC_Order|null                                   $order
 * @var string                                          $email_heading
 * @var string                                          $additional_content
 * @var bool                                            $sent_to_admin
 * @var bool                                            $plain_text
 * @var WC_Email                                        $email
 * @var string                                          $intro            Texte brut, placeholders remplacés.
 * @var array{url:string,label:string}|null             $button
 * @var WC_Product[]                                    $products         Produits à noter (vide avec un lien externe).
 * @var string                                          $optout_url
 * @var bool                                            $layout
 * @var array{button:string,border:string,muted:string} $colors           Charte du layout, sinon couleur WooCommerce.
 * @var bool                                            $optout_in_footer Lien d'opposition affiché par le pied de page du layout.
 */

use BB\WooReviewRequest\Reviews;

defined( 'ABSPATH' ) || exit;

$bb_color      = $colors['button'];
$bb_text_color = wc_light_or_dark( $bb_color, '#202020', '#ffffff' );
$bb_several    = count( $products ) > 1;

do_action( 'woocommerce_email_header', $email_heading, $email );

if ( ! $layout ) {
	echo wp_kses_post( wpautop( esc_html( $intro ) ) );

	if ( $button ) {
		?>
		<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
			<tr>
				<td bgcolor="<?php echo esc_attr( $bb_color ); ?>" style="border-radius: 4px; background: <?php echo esc_attr( $bb_color ); ?>;">
					<a href="<?php echo esc_url( $button['url'] ); ?>" target="_blank" style="display: inline-block; padding: 12px 24px; font-weight: bold; text-decoration: none; color: <?php echo esc_attr( $bb_text_color ); ?>;"><?php echo esc_html( $button['label'] ); ?></a>
				</td>
			</tr>
		</table>
		<?php
	}
}

if ( $products ) {
	?>
	<table class="bb-wrr-products" role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="width: 100%; margin: 8px 0 24px; border-collapse: collapse;">
		<?php
		foreach ( $products as $bb_product ) {
			$bb_image_id = $bb_product->get_image_id();
			// Vignette carrée recadrée côté serveur : object-fit est ignoré par Outlook et une partie de Gmail.
			$bb_image = $bb_image_id ? wp_get_attachment_image_url( (int) $bb_image_id, 'woocommerce_thumbnail' ) : '';
			$bb_image = $bb_image ? $bb_image : wc_placeholder_img_src( 'woocommerce_thumbnail' );
			$bb_url   = Reviews::review_url( $bb_product );
			$bb_cell  = 'padding: 12px 0; border-top: 1px solid ' . $colors['border'] . ';';
			?>
			<tr>
				<td width="96" valign="middle" style="width: 96px; <?php echo esc_attr( $bb_cell ); ?>">
					<a href="<?php echo esc_url( $bb_url ); ?>" target="_blank"><img src="<?php echo esc_url( $bb_image ); ?>" width="80" height="80" alt="" style="display: block; width: 80px; height: 80px; border: 0; border-radius: 4px;"></a>
				</td>
				<td valign="middle" style="<?php echo esc_attr( $bb_cell ); ?>">
					<a href="<?php echo esc_url( $bb_url ); ?>" target="_blank" style="color: inherit; font-weight: bold; text-decoration: none;"><?php echo esc_html( $bb_product->get_name() ); ?></a>
					<?php if ( $bb_several ) : ?>
						<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 8px 0 0;">
							<tr>
								<td style="border: 1px solid <?php echo esc_attr( $bb_color ); ?>; border-radius: 4px;">
									<a href="<?php echo esc_url( $bb_url ); ?>" target="_blank" style="display: inline-block; padding: 6px 14px; font-size: 13px; font-weight: normal; line-height: 1.2; text-decoration: none; color: <?php echo esc_attr( $bb_color ); ?>;"><?php esc_html_e( 'Noter ce produit', 'bb-woo-review-request' ); ?></a>
								</td>
							</tr>
						</table>
					<?php endif; ?>
				</td>
			</tr>
			<?php
		}
		?>
	</table>
	<?php
}

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

if ( $optout_url && ! $optout_in_footer ) {
	?>
	<p class="bb-wrr-optout" style="font-size: 12px; color: <?php echo esc_attr( $colors['muted'] ); ?>;">
		<?php esc_html_e( 'Vous ne souhaitez plus recevoir de demandes d’avis ?', 'bb-woo-review-request' ); ?>
		<a href="<?php echo esc_url( $optout_url ); ?>" target="_blank" style="color: <?php echo esc_attr( $colors['muted'] ); ?>;"><?php esc_html_e( 'Ne plus recevoir ces e-mails', 'bb-woo-review-request' ); ?></a>
	</p>
	<?php
}

do_action( 'woocommerce_email_footer', $email );

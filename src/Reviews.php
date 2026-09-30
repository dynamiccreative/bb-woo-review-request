<?php
/**
 * Produits d'une commande à faire noter.
 *
 * @package BB\WooReviewRequest
 */

namespace BB\WooReviewRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Sélection des produits et liens vers le formulaire d'avis WooCommerce.
 */
final class Reviews {

	/**
	 * Produits de la commande que le client peut noter : publiés, avis ouverts, dédoublonnés
	 * (une variation compte pour son produit parent), et par défaut pas encore notés par ce client.
	 *
	 * @param \WC_Order $order         Commande.
	 * @param bool      $skip_reviewed Exclure les produits déjà notés par l'e-mail de facturation.
	 * @return \WC_Product[]
	 */
	public static function products_to_review( \WC_Order $order, bool $skip_reviewed = true ): array {
		$products = array();

		if ( wc_reviews_enabled() ) {
			$billing_email = $order->get_billing_email();

			foreach ( $order->get_items() as $item ) {
				if ( ! $item instanceof \WC_Order_Item_Product ) {
					continue;
				}
				$product = $item->get_product();
				if ( $product instanceof \WC_Product && $product->is_type( 'variation' ) ) {
					$product = wc_get_product( $product->get_parent_id() );
				}
				if ( ! $product instanceof \WC_Product || isset( $products[ $product->get_id() ] ) ) {
					continue;
				}
				if ( 'publish' !== $product->get_status() || ! $product->get_reviews_allowed() ) {
					continue;
				}
				if ( $skip_reviewed && '' !== $billing_email && self::has_reviewed( $product->get_id(), $billing_email ) ) {
					continue;
				}
				$products[ $product->get_id() ] = $product;
			}
		}

		/**
		 * Produits proposés à la notation, dans l'ordre de la commande (vide = rien à noter).
		 *
		 * @param \WC_Product[] $products Produits.
		 * @param \WC_Order     $order    Commande.
		 *
		 * @since 0.1.0
		 */
		return array_values( (array) apply_filters( 'bb_wrr_products_to_review', array_values( $products ), $order ) );
	}

	/**
	 * Le client a-t-il déjà laissé un avis (publié ou en attente de modération) sur ce produit ?
	 *
	 * @param int    $product_id Produit.
	 * @param string $email      E-mail du client.
	 */
	public static function has_reviewed( int $product_id, string $email ): bool {
		$count = get_comments(
			array(
				'post_id'      => $product_id,
				'author_email' => $email,
				'type'         => 'review',
				'status'       => 'all',
				'count'        => true,
			)
		);
		return is_numeric( $count ) && (int) $count > 0;
	}

	/**
	 * Lien vers le formulaire d'avis de la fiche produit (l'ancre #reviews ouvre l'onglet « Avis »).
	 *
	 * @param \WC_Product $product Produit.
	 */
	public static function review_url( \WC_Product $product ): string {
		$url = (string) get_permalink( $product->get_id() ) . '#reviews';

		/**
		 * Lien « Donner mon avis » d'un produit.
		 *
		 * @param string      $url     URL.
		 * @param \WC_Product $product Produit.
		 *
		 * @since 0.1.0
		 */
		return (string) apply_filters( 'bb_wrr_review_url', $url, $product );
	}
}

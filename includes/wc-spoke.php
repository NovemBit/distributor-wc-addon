<?php
/**
 * Functions  performed in spoke
 *
 * @package distributor-wc
 */

namespace DT\NbAddon\WC\Spoke;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'init',
		function () {
			add_action( 'dt_process_distributor_attributes', __NAMESPACE__ . '\insert_variations', 10, 2 );
		}
	);
}



/**
 * Insert variations on initial push
 *
 * @param WP_Post         $post    Inserted or updated post object.
 * @param WP_REST_Request $request Request object.
 */
function insert_variations( $post, $request ) {
	if ( function_exists( '\wc_get_product' ) && isset( $request['distributor_product_variations'] ) && ! empty( $request['distributor_product_variations'] ) ) {
		$variations = $request['distributor_product_variations'];
		$product    = wc_get_product( $post->ID );
		foreach ( $variations as $variation_data ) {
			$inserted_id = \DT\NbAddon\WC\Utils\create_variation( $variation_data, $product );
			\DT\NbAddon\WC\Utils\set_variation_update( $variation_data, $post->ID, $inserted_id );
		}
	}
}

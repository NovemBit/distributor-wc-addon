<?php
/**
 * Register custom endpoints for product handling
 *
 * @package distributor-wc
 */

namespace DT\NbAddon\WC\RestApi;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'rest_api_init',
		__NAMESPACE__ . '\register_rest_routes'
	);
}

/**
 * Register REST routes
 */
function register_rest_routes() {
	register_rest_route(
		'wp/v2',
		'/distributor/wc/variations/insert',
		[
			'methods'             => 'POST',
			'args'                => [
				'post_id'        => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					},
				],
				'signature'      => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_string( $param );
					},
				],
				'variation_data' => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_array( $param );
					},
				],
			],
			'callback'            => __NAMESPACE__ . '\insert_variations',
			'permission_callback' => function () {
				return true;
			},
		]
	);
	register_rest_route(
		'wp/v2',
		'/distributor/wc/variations/receive',
		[
			'methods'             => 'POST',
			'args'                => [
				'post_id'        => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					},
				],
				'signature'      => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_string( $param );
					},
				],
				'variation_data' => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_array( $param );
					},
				],
			],
			'callback'            => __NAMESPACE__ . '\receive_variations',
			'permission_callback' => function () {
				return true;
			},
		]
	);
	register_rest_route(
		'wp/v2',
		'/distributor/wc/variations/delete',
		[
			'methods'             => 'POST',
			'args'                => [
				'post_id'   => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					},
				],
				'signature' => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_string( $param );
					},
				],
			],
			'callback'            => __NAMESPACE__ . '\delete_variations',
			'permission_callback' => function () {
				return true;
			},
		]
	);
}


/**
 * Insert variations on initial push
 *
 * @param \WP_REST_Request $request WP_REST_Request instance.
 */
function insert_variations( \WP_REST_Request $request ) {
	$post_id          = $request->get_param( 'post_id' );
	$signature        = $request->get_param( 'signature' );
	$variation_data   = $request->get_param( 'variation_data' );
	$is_valid_request = \DT\NbAddon\WC\Utils\validate_request( $post_id, $signature );
	if ( true !== $is_valid_request ) {
		return $is_valid_request;
	}
	$product = wc_get_product( $post_id );
	foreach ( $variation_data as $variation ) {
		$inserted_id = \DT\NbAddon\WC\Utils\create_variation( $variation, $product );
		$res         = \DT\NbAddon\WC\Utils\set_variation_update( $variation, $post_id, $inserted_id );
				/**
	 * Action triggered after variations initial insert in spoke
	 * 
	 * @param int $post_id Parent post ID.
	 */
	do_action('dt_variations_inserted', $post_id);
		return $res;
	}

}

/**
 * Receive variations updates
 *
 * @param \WP_REST_Request $request WP_REST_Request instance.
 * @return array
 */
function receive_variations( \WP_REST_Request $request ) {
	$post_id          = $request->get_param( 'post_id' );
	$signature        = $request->get_param( 'signature' );
	$variation_data   = $request->get_param( 'variation_data' );
	$is_valid_request = \DT\NbAddon\WC\Utils\validate_request( $post_id, $signature );
	if ( true !== $is_valid_request ) {
		return $is_valid_request;
	}

	if ( \DT\NbAddon\WC\Utils\is_assoc( $variation_data ) ) {
		\DT\NbAddon\WC\Utils\sync_variations( $post_id, $variation_data['current_variations'] );
		$res = \DT\NbAddon\WC\Utils\set_variation_update( $variation_data, $post_id );
	} else {
		\DT\NbAddon\WC\Utils\sync_variations( $post_id, $variation_data[0]['current_variations'] );
		$res = \DT\NbAddon\WC\Utils\set_variations_update( $variation_data, $post_id );
	}
		/**
	 * Action triggered after variations update in spoke
	 * 
	 * @param int $post_id Parent post ID.
	 */
	do_action('dt_variations_updated' ,$post_id);
	return $res;
}


/**
 * Delete variation that was deleted in source
 *
 * @param \WP_REST_Request $request WP_REST_Request instance.
 * @return array
 */
function delete_variations( \WP_REST_Request $request ) {
	$original_variation_id = $request->get_param( 'post_id' );
	$signature             = $request->get_param( 'signature' );
	$variation_id          = \DT\NbAddon\WC\Utils\get_variation_by_original_id( $original_variation_id );
	if ( empty( $variation_id ) ) {
		return new \WP_Error( 'rest_post_invalid_id', esc_html__( 'Invalid variation ID.', 'distributor-wc' ), array( 'status' => 404 ) );
	}
	$variation        = wc_get_product( $variation_id );
	$parent_id        = $variation->get_parent_id();
	$is_valid_request = \DT\NbAddon\WC\Utils\validate_request( $parent_id, $signature );
	if ( true !== $is_valid_request ) {
		return $is_valid_request;
	}
	$variation->delete( true );
	/**
	 * Action triggered after variation deleted in spoke
	 * 
	 * @param int $parent_id Parent post ID.
	 */
	do_action('dt_variation_deleted',$parent_id);
}



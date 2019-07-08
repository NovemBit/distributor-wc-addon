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
			add_action( 'dt_process_distributor_attributes', __NAMESPACE__ . '\push', 10, 2 );
			add_action( 'dt_process_subscription_attributes', __NAMESPACE__ . '\update', 10, 2 );
		}
	);
}



/**
 * Process inserted post, after initial push
 *
 * @param WP_Post         $post    Inserted or updated post object.
 * @param WP_REST_Request $request Request object.
 */
function push( $post, $request ) {
	// .... logic
}

/**
 * Process updated post
 *
 * @param WP_Post         $post    Inserted or updated post object.
 * @param WP_REST_Request $request Request object.
 */
function update( $post, $request ) {
	// .... logic
}

<?php
/**
 * Functions  performed in hub
 *
 * @package distributor-wc
 */

namespace DT\NbAddon\WC\Hub;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'init',
		function() {
			add_filter( 'dt_push_post_args', __NAMESPACE__ . '\push', 10, 2 );
			add_filter( 'dt_subscription_post_args', __NAMESPACE__ . '\update', 10, 2 );
		}
	);
}

/**
 * Filter post body before initial push
 *
 * @param array  $post_body Array of pushing post body.
 * @param object $post WP Post object.
 *
 * @return array
 */
function push( $post_body, $post ) {
	// .... logic
	return $post_body;
}

/**
 * Filter post body before sending update
 *
 * @param array  $post_body Array of pushing post body.
 * @param object $post WP Post object.
 *
 * @return array
 */
function update( $post_body, $post ) {
	// .... logic
	return $post_body;
}

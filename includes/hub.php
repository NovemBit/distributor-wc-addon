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
			add_action( 'dt_post_subscription_created', __NAMESPACE__ . '\push_variations', 10, 4 );
			add_action( 'delete_post', __NAMESPACE__ . '\on_variation_delete', 10, 1 );
			add_action( 'woocommerce_update_product_variation', __NAMESPACE__ . '\variation_update', 10, 2 );
			add_action( 'woocommerce_new_product_variation', __NAMESPACE__ . '\variation_update', 10, 2 );
		}
	);

	add_action(
		'rest_api_init',
		function() {
			$uri = $_SERVER['REQUEST_URI'];

			if ( preg_match( '~/wp-json/wc/v2/products/(\d+)~', $uri, $matches ) ) {
				add_action( 'updated_post_meta', __NAMESPACE__ . '\updated_post_meta', 10, 4 );
				add_action( 'added_term_relationship', __NAMESPACE__ . '\added_term_relationship', 10, 3 );
				add_action( 'deleted_term_relationships', __NAMESPACE__ . '\deleted_term_relationships', 10, 3 );
			}
		}
	);
}

/**
 * On initial push move all variations too
 *
 * @param int    $post_id Pushed post ID.
 * @param int    $remote_post_id Remote post ID.
 * @param string $signature Generated signature for subscription.
 * @param string $target_url Target url to push to.
 * @param bool   $allow_in_bg Allow apply filter to run function in background (for preventing infinite loop)
 *
 * @return array|void
 */
function push_variations( $post_id, $remote_post_id, $signature, $target_url, $allow_in_bg = true ) {
	$post                           = get_post( $post_id );
	$result                         = [];
	$result[$post_id]['target_url'] = $target_url;

	if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
		$_product = wc_get_product( $post->ID );
		if ( $_product && null !== $_product && ! is_wp_error( $_product ) ) {
			if ( $_product->is_type( 'variable' ) ) {
				$variations = $_product->get_children();

				if ( ! empty( $variations ) ) {
					if ( $allow_in_bg ) {
						/**
						 * Add possibility to send variation insert in background
						 *
						 * @param bool      true            Whether to run variation update.
						 * @param int    $post_id Pushed post ID.
						 * @param int    $remote_post_id Remote post ID.
						 * @param string $signature Generated signature for subscription.
						 * @param string $target_url Target url to push to.
						 */
						$allow_wc_variation_insert = apply_filters( 'dt_allow_wc_variations_insert', true, $post_id, $remote_post_id, $signature, $target_url );

						if ( false === $allow_wc_variation_insert ) {
							return;
						}
					}

					/**
					 * Action triggered before variations data is prepared.
					 */
					do_action( 'dt_before_variations_processing' );
					$variation_data = \DT\NbAddon\WC\Utils\prepare_bulk_variations_update( $variations );
					$post_body      = [
						'post_id'        => $remote_post_id,
						'signature'      => $signature,
						'variation_data' => $variation_data,
					];
					$request        = wp_remote_post(
						untrailingslashit( $target_url ) . '/wp/v2/distributor/wc/variations/insert',
						[
							'timeout' => 60,
							/**
							 * Filter the arguments sent to the remote server during a variations insert.
							 *
							 * @param  array  $post_body The request body to send.
							 * @param  int $post      Parent post id of variations that is being pushed.
							 */
							'body'    => apply_filters( 'dt_wc_variations_push_args', $post_body, $post_id ),
						]
					);

					if ( ! is_wp_error( $request ) ) {
						$response_code = wp_remote_retrieve_response_code( $request );
						$body          = wp_remote_retrieve_body( $request );

						$result[$post_id]['response']['code'] = $response_code;
						$result[$post_id]['response']['body'] = $body;
					} else {
						$result[$post_id]['response'] = $request;
					}
				}
			}
		}
	}

	return $result;
}


/**
 * Send update to destinations on variation update
 *
 * @param int $variation_id Updated variation ID.
 */
function variation_update( $variation_id ) {
	$variation      = wc_get_product( $variation_id );
	$parent_post_id = $variation->get_parent_id();
	$connection_map = get_post_meta( $parent_post_id, 'dt_connection_map', true );

	if ( empty( $connection_map ) ) {
		return;
	}

	if ( ! wp_doing_cron() ) { //phpcs:ignore
		/**
		 * Add possibility to send variation updates in background
		 *
		 * @param bool      true            Whether to run variation update or not.
		 * @param int       $parent_post_id Parent post ID.
		 * @param int       $variation_id   Updated variation ID.
		 */
		$allow_wc_variations_update = apply_filters( 'dt_allow_wc_variations_update', true, $parent_post_id, $variation_id );
		if ( false === $allow_wc_variations_update ) {
			return;
		}
	}

	process_variation_update( $parent_post_id, $variation_id );
	return;
}

/**
 * Process variation(s) update
 *
 * @param int       $post_id Parent post ID.
 * @param int|array $var Variation id or array containing all updated variations ids.
 *
 * @return array
 */
function process_variation_update( $post_id, $var ) {
	/**
	 * Action triggered before variations data is prepared.
	 */
	do_action( 'dt_before_variations_processing' );
	$update        = is_array( $var ) ? \DT\NbAddon\WC\Utils\prepare_bulk_variations_update( $var ) : \DT\NbAddon\WC\Utils\prepare_variation_update( $var );
	$subscriptions = get_post_meta( $post_id, 'dt_subscriptions', true );
	$result        = [];

	if ( empty( $subscriptions ) ) {
		$result['response'] = 'No subscriptions.';

		return $result;
	}

	foreach ( $subscriptions as $subscription_key => $subscription_id ) {
		$signature      = get_post_meta( $subscription_id, 'dt_subscription_signature', true );
		$remote_post_id = get_post_meta( $subscription_id, 'dt_subscription_remote_post_id', true );
		$target_url     = get_post_meta( $subscription_id, 'dt_subscription_target_url', true );

		$result[$subscription_key]['target_url'] = $target_url;

		if ( empty( $signature ) || empty( $remote_post_id ) || empty( $target_url ) ) {
			continue;
		}

		$post_body = [
			'post_id'        => $remote_post_id,
			'signature'      => $signature,
			'variation_data' => $update,
		];
		$request   = wp_remote_post(
			untrailingslashit( $target_url ) . '/wp/v2/distributor/wc/variations/receive',
			[
				'timeout' => 60,
				/**
				 * Filter the arguments sent to the remote server during a variation update.
				 *
				 * @param  array  $post_body The request body to send.
				 * @param  int $post      Parent post id of variation that is being pushed.
				 */
				'body'    => apply_filters( 'dt_wc_variation_post_args', $post_body, $post_id ),
			]
		);
		if ( ! is_wp_error( $request ) ) {
			$response_code = wp_remote_retrieve_response_code( $request );
			$body          = wp_remote_retrieve_body( $request );

			$result[$subscription_key]['response']['code'] = $response_code;
			$result[$subscription_key]['response']['body'] = $body;
		} else {
			$result[$subscription_key]['response'] = $request;
		}
	}
	return $result;

}

/**
 * Delete variations in destinations
 *
 * @param int $post_id Deleted variation ID.
 */
function on_variation_delete( $post_id ) {
	$post = get_post( $post_id );
	if ( 'product_variation' === $post->post_type ) {
		$variation     = wc_get_product( $post_id );
		$subscriptions = get_post_meta( $variation->get_parent_id(), 'dt_subscriptions', true );
		if ( empty( $subscriptions ) ) {
			return false;
		}
		$result = [];
		foreach ( $subscriptions as $subscription_key => $subscription_id ) {
			$signature  = get_post_meta( $subscription_id, 'dt_subscription_signature', true );
			$target_url = get_post_meta( $subscription_id, 'dt_subscription_target_url', true );

			if ( empty( $signature ) || empty( $target_url ) ) {
				continue;
			}
			$post_body = [
				'post_id'   => $post_id,
				'signature' => $signature,
			];
			$request   = wp_remote_post(
				untrailingslashit( $target_url ) . '/wp/v2/distributor/wc/variations/delete',
				[
					'timeout' => 60,
					/**
					 * Filter the arguments sent to the remote server during a variation update.
					 *
					 * @param  array  $post_body The request body to send.
					 * @param  int $post      Variation that is being deleted.
					 */
					'body'    => apply_filters( 'dt_wc_variation_delete_post_args', $post_body, $post_id ),
				]
			);
			if ( ! is_wp_error( $request ) ) {
				$response_code = wp_remote_retrieve_response_code( $request );
				$headers       = wp_remote_retrieve_headers( $request );

				$result[ $subscription_id ] = json_decode( wp_remote_retrieve_body( $request ) );
			} else {
				$result[ $subscription_id ] = $request;
			}
		}
	}
}

/**
 * Trigger notification when post meta updated
 *
 * @param int $meta_id
 * @param int $post_id
 * @param string $meta_key
 * @param string $meta_value
 *
 * @return array|false|void|\WP_Error
 */
function updated_post_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
	$blacklist_meta = ['_edit_lock'];
	$blacklist_meta = apply_filters( 'dt_product_updated/blacklist_meta', $blacklist_meta );

	if( in_array( $meta_key, $blacklist_meta ) || strpos( $meta_key, 'algolia_' ) === 0 ) {
		return;
	}

	$type = get_post_type( $post_id );

	if ( $type != 'product' ) {
		return;
	}

	static $distributed_product_ids = [];

	if ( in_array( $post_id, $distributed_product_ids ) ) {
		return;
	}

	$distributed_product_ids[] = $post_id;

	if ( function_exists( '\Distributor\Subscriptions\send_notifications' ) ) {
		return \Distributor\Subscriptions\send_notifications( $post_id );
	}
}

/**
 * Trigger notification when term relationship added
 *
 * @param int $object_id
 * @param int $tt_id
 * @param string $taxonomy
 */
function added_term_relationship( int $object_id, int $tt_id, string $taxonomy ) {
	updated_term_relationships( $object_id );
}

/**
 * Trigger notification when term relationship deleted
 *
 * @param int $object_id
 * @param array $tt_ids
 * @param string $taxonomy
 */
function deleted_term_relationships( int $object_id, array $tt_ids, string $taxonomy ) {
	updated_term_relationships( $object_id );
}

/**
 * @param int $object_id
 *
 * @return array|void
 */
function updated_term_relationships( int $object_id ) {
	$type = get_post_type( $object_id );

	if ( $type != 'product' ) {
		return;
	}

	static $distributed_product_ids = [];

	if ( in_array( $object_id, $distributed_product_ids ) ) {
		return;
	}

	$distributed_product_ids[] = $object_id;

	if ( function_exists( '\Distributor\Subscriptions\send_notifications' ) ) {
		return \Distributor\Subscriptions\send_notifications( $object_id );
	}
}

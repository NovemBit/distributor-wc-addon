<?php
/**
 * Helper functions
 *
 * @package distributor-wc
 */

namespace DT\NbAddon\WC\Utils;

/**
 * Prepare bulk variations update
 *
 * @param array $variations Updated variations ids.
 * @return array Prepared and formatted variations update array.
 */
function prepare_bulk_variations_update( $variations ) {
	$result = [];
	foreach ( $variations as $variation_id ) {
		$result[] = prepare_variation_update( $variation_id );
	}
	return $result;
}

/**
 * Prepare single variation update data
 *
 * @param int $variation_id Updated variation id.
 *
 * @return false|string Prepared and formatted variation update array in json format.
 */
function prepare_variation_update( $variation_id ) {
	$variation = wc_get_product( $variation_id );
	$image_id  = $variation->get_image_id();

	add_filter(
		'dt_blacklisted_meta',
		__NAMESPACE__ . '\change_variation_blacklisted_meta'
	);

	$meta = \Distributor\Utils\prepare_meta( $variation->get_id() );

	remove_filter(
		'dt_blacklisted_meta',
		__NAMESPACE__ . '\change_variation_blacklisted_meta'
	);

	$data = [
		'original_id'        => $variation->get_id(),
		'data'               => [
			'sku'               => $variation->get_sku(),
			'image'             => [
				'id'  => $image_id,
				'url' => empty( $image_id ) ? '' : wp_get_attachment_url( $image_id ),
			],
			'manage_stock'      => $variation->get_manage_stock(),
			'backorders'        => $variation->get_backorders(),
			'stock_quantity'    => $variation->get_stock_quantity(),
			'weight'            => $variation->get_weight(),
			'length'            => $variation->get_length(),
			'width'             => $variation->get_width(),
			'height'            => $variation->get_height(),
			'tax_class'         => $variation->get_tax_class(),
			'shipping_class_id' => $variation->get_shipping_class_id(),
			'purchase_note'     => $variation->get_purchase_note(),
			'status'            => $variation->variation_is_active(),
			'price'	            => $variation->get_price(),
			'regular_price'     => $variation->get_regular_price(),
			'sale_price'        => $variation->get_sale_price(),
		],
		'current_variations' => wc_get_product( $variation->get_parent_id() )->get_children(),
		'meta'               => $meta,
	];

	return json_encode( $data );
}

/**
 * Decode data received in json format
 *
 * @param $item
 *
 * @return array|mixed
 */
function decode($item) {
	if( is_array( $item ) ) {
		$res = [];

		foreach( $item as $i ) {
			$res[] = json_decode($i, true);
		}

		return $res;
	}

	return json_decode($item, true);
}

/**
 * Change default blacklisted meta for product variation
 *
 * @param array $blacklist
 *
 * @return array
 */
function change_variation_blacklisted_meta( $blacklist ) {
	$meta_to_skip = [
		'_sku',
		'_manage_stock',
		'_backorders',
		'_weight',
		'_length',
		'_width',
		'_height',
		'_tax_class',
		'_purchase_note',
		'_stock_status',
	];
	return array_merge( $blacklist, $meta_to_skip );
}

/**
 * Set variations updates
 *
 * @param array $variations Variations array to be updated
 * @param int   $post_id Parent post ID
 */
function set_variations_update( $variations, $post_id ) {
	$result = [];
	foreach ( $variations as $variation ) {
		$result[] = set_variation_update( $variation, $post_id );
	}
	return $result;
}

/**
 * Set variation updates
 *
 * @param array    $variation_data Variation data array to be updated.
 * @param int      $post_id Parent post ID.
 * @param int|null $variation_id Variation ID to be updated.
 */
function set_variation_update( $variation_data, $post_id, $variation_id = null ) {
	$original_id = $variation_data['original_id'];
	$update      = $variation_data['data'];
	$meta        = $variation_data['meta'];

	if ( empty( $variation_id ) ) {
		$variation_id = get_variation_by_original_id( $original_id );
	}
	if ( ! empty( $variation_id ) ) {
		\Distributor\Utils\set_meta( $variation_id, $meta );
		$variation = wc_get_product( $variation_id );

		if ( ! empty( $update['sku'] ) ) {
			$variation->set_sku( $update['sku'] );
		}

		$variation->set_manage_stock( $update['manage_stock'] );
		$variation->set_backorders( $update['backorders'] );
		if ( isset( $update['stock_quantity'] ) ) {
			$variation->set_stock_quantity( $update['stock_quantity'] );
		}
		$variation->set_weight( $update['weight'] );
		$variation->set_length( $update['length'] );
		$variation->set_width( $update['width'] );
		$variation->set_height( $update['height'] );
		$variation->set_tax_class( $update['tax_class'] );
		$variation->set_shipping_class_id( $update['shipping_class_id'] );
		$variation->set_regular_price( $update['regular_price'] );
		$variation->set_sale_price( $update['sale_price'] );
		$variation->set_price( $update['price'] );
		$current_image_id = $variation->get_image_id();
		if ( ! empty( $update['image']['url'] ) ) {
			$original_media_id = $update['image']['id'];
			$existing_image_id = get_existing_media( $variation_id, $original_media_id );
			if ( empty( $existing_image_id ) ) {
				$new_image_id = \Distributor\Utils\process_media( $update['image']['url'], $variation_id );
				wp_update_attachment_metadata( $new_image_id, wp_generate_attachment_metadata( $new_image_id, get_attached_file( $new_image_id ) ) );
				$variation->set_image_id( $new_image_id );
				update_post_meta( $new_image_id, 'dt_original_media_id', $original_media_id );
			} else {
				$variation->set_image_id( $existing_image_id );
			}
		} else {
			$variation->set_image_id( '' );
		}
		$variation->save();
		$status = $update['status'] ? 'publish' : 'private';
		wp_update_post(
			[
				'ID'          => $variation_id,
				'post_status' => $status,
			]
		);
		return [
			'status' => 'success',
			'data'   => [
				'variation_id'        => $original_id,
				'variation_remote_id' => $variation_id,
			],
		];
	} else {
		set_variation_update( $variation_data, $post_id, create_variation( $variation_data, wc_get_product( $post_id ) ) );
	}
}

/**
 * Get variation id from it's original id
 *
 * @param int $id Variation original ID.
 * @return int|null
 */
function get_variation_by_original_id( $id ) {
	global $wpdb;
	return $wpdb->get_var(
		$wpdb->prepare(
			"
				SELECT
				post_id
				FROM
				  `{$wpdb->postmeta}`
				WHERE meta_key = 'dt_original_variation_id' AND meta_value = %d ",
			$id
		)
	);
}

/**
 * Sync variations with source
 *
 * @param int   $post_id Parent post id in destination.
 * @param array $original_ids Array of all product variations ids in source.
 *
 * @return void
 */
function sync_variations( $post_id, $original_ids ) {
	$vars_to_remove = get_variations_to_remove( $post_id, $original_ids );
	foreach ( $vars_to_remove as $var_id ) {
		$var = wc_get_product( $var_id );
		if ( ! empty( $var ) && ! is_wp_error( $var ) ) {
			$var->delete( true );
		}
	}
}

/**
 * Get variations that must be removed to synchronize with source
 *
 * @param int   $post_id Parent post id in destination.
 * @param array $original_ids Array of all product variations ids in source.
 *
 * @return array
 */
function get_variations_to_remove( $post_id, $original_ids ) {
	global $wpdb;
	$escaped_ids     = empty( $original_ids ) ? '' : array_map(
		function( $v ) {
			return "'" . esc_sql( $v ) . "'";
		},
		$original_ids
	);
	$escaped_post_id = esc_sql( $post_id );

	$query   = "
				SELECT pm.post_id
				FROM {$wpdb->postmeta} AS pm
				INNER JOIN {$wpdb->posts} as p
				ON p.ID = pm.post_id
				WHERE p.post_parent ={$escaped_post_id}
				AND p.post_type='product_variation'
				AND pm.meta_key='dt_original_variation_id' AND meta_value NOT IN (" . implode( ',', $escaped_ids ) . ')';
	$results = $wpdb->get_results( $query );// phpcs:ignore
	if ( ! empty( $results ) ) {
		$result = [];
		foreach ( $results as $row ) {
			$result[] = $row->post_id;
		}
		return $result;
	}
	return [];
}

/**
 * Create new variation for product
 *
 * @param array  $variation_data Array of variation data.
 * @param object $product Product to add variation.
 *
 * @return int Created variation id.
 */
function create_variation( $variation_data, $product ) {
	$status         = $variation_data['data']['status'] ? 'publish' : 'private';
	$variation_post = array(
		'post_title'  => $product->get_title(),
		'post_name'   => 'product-' . $product->get_id() . '-variation',
		'post_status' => $status,
		'post_parent' => $product->get_id(),
		'post_type'   => 'product_variation',
		'guid'        => $product->get_permalink(),
	);
	$variation_id   = wp_insert_post( $variation_post );
		update_post_meta( $variation_id, 'dt_original_variation_id', $variation_data['original_id'] );
		return $variation_id;
}


/**
 * Check if array is associative
 *
 * @param array $arr Array to check
 * @return bool
 */
function is_assoc( array $arr ) {
	if ( array() === $arr ) { return false;
	}
	return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
}


/**
 * Get existing media by it's parent and original ids
 *
 * @param int $post_id Parent post ID.
 * @param int $media_id Original media id.
 *
 * @return int|null
 */
function get_existing_media( $post_id, $media_id ) {
	global $wpdb;
	return $wpdb->get_var(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} as p INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id WHERE p.post_type ='attachment' AND p.post_parent= %d AND pm.meta_key = 'dt_original_media_id' and pm.meta_value = %s",
			$post_id,
			$media_id
		)
	);
}


/**
 * Validate request by signature
 *
 * @param int    $post_id Parent post ID in destination.
 * @param string $signature Subscription signature for post.
 *
 * @return bool|WP_Error
 */
function validate_request( $post_id, $signature ) {
	$post = get_post( $post_id );
	if ( empty( $post ) ) {
		return new \WP_Error( 'rest_post_invalid_id', esc_html__( 'Invalid post ID.', 'distributor-wc' ), array( 'status' => 404 ) );
	}

	$valid_signature = get_post_meta( $post_id, 'dt_subscription_signature', true ) === $signature;
	if ( ! $valid_signature ) {
		return new \WP_Error( 'rest_post_invalid_subscription', esc_html__( 'No subscription for that post', 'distributor-wc' ), array( 'status' => 400 ) );
	}
	return true;
}

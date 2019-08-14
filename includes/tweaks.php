<?php
/**
 * Tweaks for WC addon
 *
 * @package distributor-wc-addon
 */

namespace DT\NbAddon\Comments\Tweaks;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'init',
		function () {
			add_filter( 'dt_blacklisted_meta', __NAMESPACE__ . '\blacklist_keys', 10, 1 );
			add_action( 'dt_post_term_hierarchy_saved', __NAMESPACE__ . '\replace_primary_cat', 10, 2 );
		}
	);
}


/**
 * Replace primary category term id with new one
 *
 * @param int   $post_id Post ID.
 * @param array $map The taxonomy term id mapping.
 */
function replace_primary_cat( $post_id, $map ) {
	$origin_id = get_post_meta( $post_id, '_primary_term_product_cat', true );
	if ( ! empty( $origin_id ) ) {
		$new_id = $origin_id;
		if ( isset( $map['product_cat'][ $origin_id ] ) ) {
			$new_id = $map['product_cat'][ $origin_id ];
		}
		update_post_meta( $post_id, '_primary_term_product_cat', $new_id );
	}
}

/**
 * Add keys to blacklisted keys array
 *
 * @param array $blacklisted Array of blacklisted keys.
 * @return array
 */
function blacklist_keys( $blacklisted ) {
	return array_merge(
		$blacklisted,
		array(
			'_wc_average_rating',
			'_wc_rating_count',
			'_wc_review_count',
		)
	);
}

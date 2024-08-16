<?php
/**
 *
 * Plugin Name:       WP RSS Block Addon - Additional Tags
 * Description:       Add additional tags WP Rest API and feed to the RSS block.
 * Version:           1.0
 * Author:            Kelvin Xu
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       rss-block-addon-additional-tags
 *
 * @package           rss-block-addon-additional-tags
 */

namespace UBC\CTLT\Block\RSS\AdditionalTags;

add_action( 'rss2_ns', __NAMESPACE__ . '\\add_rss2_ns' );
add_action( 'rss2_item', __NAMESPACE__ . '\\add_feature_image_to_rss2_item' );
add_action( 'rss2_item', __NAMESPACE__ . '\\add_custom_fields_to_rss2_item' );
add_filter( 'wpapi_filter_item_context', __NAMESPACE__ . '\\rss_block_read_images_from_feed', 10, 3 );
add_filter( 'wpapi_filter_item_context', __NAMESPACE__ . '\\rss_block_read_cf_from_feed', 10, 3 );
add_filter( 'wpapi_filter_supported_inner_blocks', __NAMESPACE__ . '\\rss_block_add_innerblocks_support', 10, 2 );

/*-----------------------------------------------------------------------------------*/

/**
 * Extending RSS Feed on websites where this plugin is activated.
 * Adding custom namespace to RSS feeds to group custom tags that we're going to add.
 */
function add_rss2_ns() {
	echo 'xmlns:wprssblock="https://cms.ubc.ca/"' . "\n";
}//end add_rss2_ns()

/**
 * Add featured image to RSS feeds under pre-defined custom namespace.
 * Including image source, title and alt text.
 */
function add_feature_image_to_rss2_item() {
	global $post;

	$image_id = get_post_thumbnail_id();

	echo '<wprssblock:image label="featured_image" alt="' . esc_textarea( get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) . '" title="' . esc_textarea( get_the_title( $image_id ) ) . '">' . esc_url( get_the_post_thumbnail_url( $post ) ) . '</wprssblock:image>';
}//end add_feature_image_to_rss2_item()

/**
 * Add available custom fields key/value pairs to RSS feeds under pre-defined custom namespace.
 */
function add_custom_fields_to_rss2_item() {
	global $post;

	$cf_keys = get_site_meta_keys();

	array_map(
		function ( $key ) use ( $post ) {
			echo '<wprssblock:cf key="' . esc_attr( $key ) . '">' . esc_html( get_post_meta( $post->ID, $key, true ) ) . '</wprssblock:cf>';
		},
		$cf_keys
	);
}//end add_custom_fields_to_rss2_item()

/**
 * Read images from RSS feed and merge them to the RSS block context.
 *
 * @param array     $context RSS block context before filtering.
 * @param string    $block_name name of the block for verification.
 * @param SimplePie $item RSS feed item.
 * @return array
 */
function rss_block_read_images_from_feed( $context, $block_name, $item ) {

	if ( 'ubc/ctlt-rss' !== $block_name ) {
		return;
	}

	$images = $item->get_item_tags( 'https://cms.ubc.ca/', 'image' );

	if ( $images ) {
		$images = array_map(
			function ( $image ) {
				return array(
					'label' => $image['attribs']['']['label'],
					'src'   => $image['data'],
					'alt'   => $image['attribs']['']['alt'],
					'title' => $image['attribs']['']['title'],
				);
			},
			$images
		);

		if ( array_key_exists( 'images', $context ) ) {
			$context['images'] = array_merge( $context['images'], $images );
		} else {
			$context['images'] = $images;
		}
	}

	return $context;
}//end rss_block_read_images_from_feed()

/**
 * Read custom fields from RSS feed and merge them to the RSS block context.
 *
 * @param array     $context RSS block context before filtering.
 * @param string    $block_name block block_name for verification.
 * @param SimplePie $item RSS feed item.
 * @return array
 */
function rss_block_read_cf_from_feed( $context, $block_name, $item ) {

	if ( 'ubc/ctlt-rss' !== $block_name ) {
		return;
	}

	$cfs = $item->get_item_tags( 'https://cms.ubc.ca/', 'cf' );

	if ( $cfs ) {
		$cfs = array_map(
			function ( $image ) {
				return array(
					'label' => $image['attribs']['']['key'],
					'value' => $image['data'],
				);
			},
			$cfs
		);

		if ( array_key_exists( 'custom', $context ) ) {
			$context['custom'] = array_merge( $context['custom'], $cfs );
		} else {
			$context['custom'] = $cfs;
		}
	}

	return $context;
}//end rss_block_read_cf_from_feed()

/**
 * Get current site meta keys.
 */
function get_site_meta_keys() {
	global $wpdb;

	$keys = get_transient( 'wp_metadata_get_keys' );

	if ( false !== $keys ) {
		return $keys;
	}

	$keys = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT meta_key
			FROM $wpdb->postmeta
			WHERE meta_key NOT BETWEEN '_' AND '_z'
			HAVING meta_key NOT LIKE %s
			ORDER BY meta_key",
			$wpdb->esc_like( '_' ) . '%'
		)
	);

	set_transient( 'wp_metadata_get_keys', $keys, HOUR_IN_SECONDS );

	wp_send_json_success( $keys );
}//end get_site_meta_keys()

/**
 * Add additional innerblocks supports to RSS block.
 *
 * @param array  $supported_innerblocks The list of supported innerblocks before filtering.
 * @param string $block_name The name of the block for verification.
 */
function rss_block_add_innerblocks_support( $supported_innerblocks, $block_name ) {

	if ( 'ubc/ctlt-rss' !== $block_name ) {
		return $supported_innerblocks;
	}

	$more_innerblocks = array(
		'ubc/api-image',
		'ubc/api-custom-field',
	);

	$supported_innerblocks = array_merge( $supported_innerblocks, $more_innerblocks );
	$supported_innerblocks = array_unique( $supported_innerblocks );

	return $supported_innerblocks;
}//end rss_block_add_innerblocks_support()

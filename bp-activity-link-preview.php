<?php
/**
 * BuddyPress Activity Link preview
 *
 * Plugin Name:       Activity Link Preview For BuddyPress
 * Plugin URI:        https://wbcomdesigns.com/downloads/buddypress-activity-link-preview/
 * Description:       BuddyPress activity link preview display as image title and description from the site. when links are used in activity posts
 * Version:           1.4.0
 * Author:            wbcomdesigns
 * Author URI:        https://wbcomdesigns.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       buddypress-activity-link-preview
 * Domain Path:       /languages
 *
 * @package           Buddypress-activity-link-preview
 * @link              https://wbcomdesigns.com/
 * @since             1.0.0
 */

define( 'BP_ACTIVITY_LINK_PREVIEW_URL', plugin_dir_url( __FILE__ ) );
define( 'BP_ACTIVITY_LINK_PREVIEW_PATH', plugin_dir_path( __FILE__ ) );

/** Bp_activity_link_preview_enqueue_scripts */
function bp_activity_link_preview_enqueue_scripts() {
	wp_enqueue_style( 'bp-activity-link-preview-css', BP_ACTIVITY_LINK_PREVIEW_URL . 'assets/css/bp-activity-link-preview.css', array(), '1.0.0', 'all' );
	wp_enqueue_script( 'bp-activity-link-preview-js', BP_ACTIVITY_LINK_PREVIEW_URL . 'assets/js/bp-activity-link-preview.js', array( 'jquery' ), '1.0.0' );
}
add_action( 'wp_enqueue_scripts', 'bp_activity_link_preview_enqueue_scripts' );

/** Bp_activity_parse_url_preview */
function bp_activity_parse_url_preview() {

	// Get URL.
	$url = ! empty( $_POST['url'] ) ? filter_var( $_POST['url'], FILTER_VALIDATE_URL ) : '';// phpcs:ignore

	// Check if URL is validated.
	if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
		wp_send_json( array( 'error' => __( 'URL is not valid.', 'buddypress-activity-link-preview' ) ) );
	}

	// Get URL parsed data.
	$parse_url_data = bp_activity_link_parse_url( $url );

	// If empty data then send error.
	if ( empty( $parse_url_data ) ) {
		wp_send_json( array( 'error' => __( 'Sorry! preview is not available right now. Please try again later.', 'buddypress-activity-link-preview' ) ) );
	}

	// send json success.
	wp_send_json( $parse_url_data );
}

add_action( 'wp_ajax_bp_activity_parse_url_preview', 'bp_activity_parse_url_preview' );
add_action( 'wp_ajax_nopriv_bp_activity_parse_url_preview', 'bp_activity_parse_url_preview' );

/**
 * Bp_activity_link_parse_url
 *
 * @param url $url url.
 */
function bp_activity_link_parse_url( $url ) {
	$cache_key = 'bp_activity_oembed_' . md5( serialize( $url ) );

	// get transient data for url.
	$parsed_url_data = get_transient( $cache_key );
	if ( ! empty( $parsed_url_data ) ) {
		return $parsed_url_data;
	}

	$parsed_url_data = array();

	// Fetch the oembed code for URL.
	$embed_code = wp_oembed_get( $url, array( 'discover' => false ) );
	if ( ! empty( $embed_code ) ) {
		$parsed_url_data['title']       = ' ';
		$parsed_url_data['description'] = $embed_code;
		$parsed_url_data['images']      = '';
		$parsed_url_data['error']       = '';
		$parsed_url_data['wp_embed']    = true;
	} else {

		// safely get URL and response body.
		$response = wp_safe_remote_get(
			$url,
			array(
				'user-agent' => '', // Default value being blocked by Cloudflare.
			)
		);
		$body     = wp_remote_retrieve_body( $response );

		// if response is not empty.
		if ( ! is_wp_error( $body ) && ! empty( $body ) ) {

			// Load HTML to DOM Object.
			$dom = new DOMDocument();
			@$dom->loadHTML( mb_convert_encoding( $body, 'HTML-ENTITIES', 'UTF-8' ) );

			$meta_tags   = array();
			$images      = array();
			$description = '';
			$title       = '';

			$xpath       = new DOMXPath( $dom );
			$query       = '//*/meta[starts-with(@property, \'og:\')]';
			$metas_query = $xpath->query( $query );
			foreach ( $metas_query as $meta ) {
				$property    = $meta->getAttribute( 'property' );
				$content     = $meta->getAttribute( 'content' );
				$meta_tags[] = array( $property, $content );
			}

			if ( is_array( $meta_tags ) && ! empty( $meta_tags ) ) {
				foreach ( $meta_tags as $tag ) {
					if ( is_array( $tag ) && ! empty( $tag ) ) {
						if ( 'og:title' === $tag[0] ) {
							$title = $tag[1];
						}
						if ( 'og:description' === $tag[0] || 'description' === strtolower( $tag[0] ) ) {
							$description = html_entity_decode( $tag[1], ENT_QUOTES, 'utf-8' );
						}
						if ( 'og:image' === $tag[0] ) {
							$images[] = $tag[1];
						}
					}
				}
			}

			// Parse DOM to get Title.
			if ( empty( $title ) ) {
				$nodes = $dom->getElementsByTagName( 'title' );
				$title = $nodes->item( 0 )->nodeValue;
			}

			// Parse DOM to get Meta Description.
			if ( empty( $description ) ) {
				$metas = $dom->getElementsByTagName( 'meta' );
				for ( $i = 0; $i < $metas->length; $i ++ ) {
					$meta = $metas->item( $i );
					if ( 'description' === $meta->getAttribute( 'name' ) ) {
						$description = $meta->getAttribute( 'content' );
						break;
					}
				}
			}

			// Parse DOM to get Images.
			$image_elements = $dom->getElementsByTagName( 'img' );
			for ( $i = 0; $i < $image_elements->length; $i ++ ) {
				$image = $image_elements->item( $i );
				$src   = $image->getAttribute( 'src' );

				if ( filter_var( $src, FILTER_VALIDATE_URL ) ) {
					$images[] = $src;
				}
			}

			if ( ! empty( $description ) && '' === trim( $title ) ) {
				$title = $description;
			}

			if ( ! empty( $title ) && '' === trim( $description ) ) {
				$description = $title;
			}

			if ( ! empty( $title ) ) {
				$parsed_url_data['title'] = $title;
			}

			if ( ! empty( $description ) ) {
				$parsed_url_data['description'] = $description;
			}

			if ( ! empty( $images ) ) {
				$parsed_url_data['images'] = $images;
			}

			if ( ! empty( $title ) || ! empty( $description ) || ! empty( $images ) ) {
				$parsed_url_data['error'] = '';
			}
		}
	}

	if ( ! empty( $parsed_url_data ) ) {
		// set the transient.
		set_transient( $cache_key, $parsed_url_data, DAY_IN_SECONDS );
	}

	/**
	 * Filters parsed URL data.
	 *
	 * @since BuddyBoss 1.0.0
	 * @param array $parsed_url_data Parse URL data.
	 */
	return apply_filters( 'bp_activity_link_parse_url', $parsed_url_data );
}


/**
 * Save link preview data into activity meta key "_bp_activity_link_preview_data"
 *
 * @since BuddyPress 1.0.0
 *
 * @param activity $activity activity.
 */
function bp_activity_link_preview_save_link_data( $activity ) {
	$bp_activity_nonce = isset( $_POST['_wpnonce_post_update'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce_post_update'] ) ) : '';
	// Check for nonce security.	
	if ( $bp_activity_nonce != '' &&  ! wp_verify_nonce( $bp_activity_nonce, 'post_update' ) ) {
		die( 'Busted!' );
	}
	if ( isset( $_POST['link_url'] ) && isset( $_POST['link_title'] ) && isset( $_POST['link_description'] ) && isset( $_POST['link_image'] ) ) {

		$link_url         = ! empty( $_POST['link_url'] ) ? sanitize_text_field( wp_unslash( $_POST['link_url'] ) ) : '';
		$link_title       = ! empty( $_POST['link_title'] ) ? filter_var( wp_unslash( $_POST['link_title'] ), FILTER_SANITIZE_STRING ) : '';
		$link_description = ! empty( $_POST['link_description'] ) ? filter_var( wp_unslash( $_POST['link_description'] ), FILTER_SANITIZE_STRING ) : '';
		$link_image       = ! empty( $_POST['link_image'] ) ? sanitize_text_field( wp_unslash( $_POST['link_image'] ) ) : '';

		$link_preview_data['url'] = $link_url;

		if ( ! empty( $link_image ) ) {
			$link_preview_data['image_url'] = $link_image;
		}

		if ( ! empty( $link_title ) ) {
			$link_preview_data['title'] = $link_title;
		}

		if ( ! empty( $link_description ) ) {
			$link_preview_data['description'] = $link_description;
		}

		bp_activity_update_meta( $activity->id, '_bp_activity_link_preview_data', $link_preview_data );
	}
}


add_action( 'bp_activity_after_save', 'bp_activity_link_preview_save_link_data', 10, 1 );

/**
 * Bp_activity_link_preview_content_body
 *
 * @since BuddyPress 1.0.0
 * @param content  $content content.
 * @param activity $activity activity.
 */
function bp_activity_link_preview_content_body( $content, $activity ) {

	$activity_id = $activity->id;

	$preview_data = bp_activity_get_meta( $activity_id, '_bp_activity_link_preview_data', true );
	$preview_data = bp_parse_args(
		$preview_data,
		array(
			'title'       => '',
			'description' => '',
		)
	);

	if ( empty( $preview_data['url'] ) || ( empty( trim( $preview_data['title'] ) ) && empty( trim( $preview_data['description'] ) ) ) ) {
		return $content;
	}

	$description = $preview_data['description'];
	$read_more   = ' &hellip; <a class="activity-link-preview-more" href="' . esc_url( $preview_data['url'] ) . '" target="_blank" rel="nofollow">' . __( 'Continue reading', 'buddypress-activity-link-preview' ) . '</a>';
	$description = wp_trim_words( $description, 40, $read_more );

	$content = make_clickable( $content );

	$content .= '<div class="activity-link-preview-container">';
	$content .= '<p class="activity-link-preview-title"><a href="' . esc_url( $preview_data['url'] ) . '" target="_blank" rel="nofollow">' . esc_html( $preview_data['title'] ) . '</a></p>';
	if ( ! empty( $preview_data['image_url'] ) ) {
		$content .= '<div class="activity-link-preview-image">';
		$content .= '<a href="' . esc_url( $preview_data['url'] ) . '" target="_blank"><img src="' . esc_url( $preview_data['image_url'] ) . '" /></a>';
		$content .= '</div>';
	}
	$content .= '<div class="activity-link-preview-excerpt"><p>' . $description . '</p></div>';
	$content .= '</div>';
	return $content;
}

add_filter( 'bp_get_activity_content_body', 'bp_activity_link_preview_content_body', 8, 2 );

/**
 *  Check if buddypress activate.
 */
function bp_activity_link_preview_requires_buddypress() {
	if ( ! class_exists( 'Buddypress' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		add_action( 'admin_notices', 'bp_activity_link_preview_required_plugin_admin_notice' );
		if ( null !== filter_input( INPUT_GET, 'activate' ) ) {
			$activate = filter_input( INPUT_GET, 'activate' );
			unset( $activate );
		}
	}
}

add_action( 'admin_init', 'bp_activity_link_preview_requires_buddypress' );
/**
 * Throw an Alert to tell the Admin why it didn't activate.
 *
 * @author wbcomdesigns
 * @since  1.2.0
 */
function bp_activity_link_preview_required_plugin_admin_notice() {
	$bpquotes_plugin = esc_html__( 'Activity Link Preview For BuddyPress', 'buddypress-activity-link-preview' );
	$bp_plugin       = esc_html__( 'BuddyPress', 'buddypress-activity-link-preview' );
	echo '<div class="error"><p>';
	/* translators: %s: */
	echo sprintf( esc_html__( '%1$s is ineffective as it requires %2$s to be installed and active.', 'buddypress-activity-link-preview' ), '<strong>' . esc_html( $bpquotes_plugin ) . '</strong>', '<strong>' . esc_html( $bp_plugin ) . '</strong>' );
	echo '</p></div>';
	if ( null !== filter_input( INPUT_GET, 'activate' ) ) {
		$activate = filter_input( INPUT_GET, 'activate' );
		unset( $activate );
	}
}

add_filter( 'bp_rest_activity_prepare_value', 'bp_activity_link_preview_data_embed_rest_api', 10, 3 );

/**
 * Embed bp activity link preview data in rest api activity endpoint.
 *
 * @param  object $response get response data.
 * @param  object $request get request data.
 * @param  array  $activity get activity data.
 * @return $response
 */
function bp_activity_link_preview_data_embed_rest_api( $response, $request, $activity ) {
	$bp_activity_link_data              = bp_activity_get_meta( $activity->id, '_bp_activity_link_preview_data', true );
	$response->data['bp_activity_link'] = $bp_activity_link_data;
	return $response;
}

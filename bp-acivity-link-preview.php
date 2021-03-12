<?php
/**
 *
 * @link              https://wbcomdesigns.com/
 * @since             1.0.0 
 *
 * @wordpress-plugin
 * Plugin Name:       BuddyPress Acivity Link Preview
 * Plugin URI:        https://wbcomdesigns.com/downloads/buddypress-auto-friends/
 * Description:       BuddyPress activity link preview display as imag title and description from the site. when links are used in activity posts 
 * Version:           1.0.0
 * Author:            wbcomdesigns
 * Author URI:        https://wbcomdesigns.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       buddypress-activity-link-preview
 * Domain Path:       /languages
 */
 
 

define( 'BP_ACTIVITY_LINK_PREVIEW_URL', plugin_dir_url( __FILE__ ) );
define( 'BP_ACTIVITY_LINK_PREVIEW_PATH', plugin_dir_path( __FILE__ ) );
 
function bp_activity_link_preview_enqueue_scripts() {
	wp_enqueue_style( 'bp-acivity-link-preview-css',BP_ACTIVITY_LINK_PREVIEW_URL . 'assets/css/bp-acivity-link-preview.css' );
	
	wp_enqueue_script( 'bp-acivity-link-preview-js',BP_ACTIVITY_LINK_PREVIEW_URL . 'assets/js/bp-acivity-link-preview.js', array( 'jquery' ), false );
}
add_action( 'wp_enqueue_scripts', 'bp_activity_link_preview_enqueue_scripts' );

function bp_activity_parse_url_preview(){

	// Get URL.
	$url = $_POST['url'];

	// Check if URL is validated.
	if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
		wp_send_json( array( 'error' => __( 'URL is not valid.', 'buddyboss' ) ) );
	}

	// Get URL parsed data.
	$parse_url_data = bp_activity_link_parse_url( $url );

	// If empty data then send error.
	if ( empty( $parse_url_data ) ) {
		wp_send_json( array( 'error' => __( 'Sorry! preview is not available right now. Please try again later.', 'buddyboss' ) ) );
	}

	// send json success.
	wp_send_json( $parse_url_data );
}

add_action('wp_ajax_bp_activity_parse_url_preview', 'bp_activity_parse_url_preview');
add_action('wp_ajax_nopriv_bp_activity_parse_url_preview', 'bp_activity_parse_url_preview');



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
				'user-agent' => '', // Default value being blocked by Cloudflare
			)
		);
		$body     = wp_remote_retrieve_body( $response );

		// if response is not empty
		if ( ! is_wp_error( $body ) && ! empty( $body ) ) {

			// Load HTML to DOM Object
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
						if ( $tag[0] == 'og:title' ) {
							$title = $tag[1];
						}
						if ( $tag[0] == 'og:description' || 'description' === strtolower( $tag[0] ) ) {
							$description = html_entity_decode( $tag[1], ENT_QUOTES, 'utf-8' );
						}
						if ( $tag[0] == 'og:image' ) {
							$images[] = $tag[1];
						}
					}
				}
			}

			// Parse DOM to get Title
			if ( empty( $title ) ) {
				$nodes = $dom->getElementsByTagName( 'title' );
				$title = $nodes->item( 0 )->nodeValue;
			}

			// Parse DOM to get Meta Description
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

			// Parse DOM to get Images
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
	 * @since BuddyBoss 1.3.2
	 * @param array $parsed_url_data Parse URL data.
	 */
	return apply_filters( 'bp_activity_link_parse_url', $parsed_url_data );
}

(function($) {
  'use strict';

	var loadURLAjax	= null;
	var loadedURLs	= [];
	var scrap_URL = function(inputurlText){
		var urlString = '';

		if ( inputurlText === null ) {
			return;
		}

		if ( inputurlText.indexOf( '<img' ) >= 0 ) {
			inputurlText = inputurlText.replace( /<img .*?>/g, '' );
		}

		if ( inputurlText.indexOf( 'http://' ) >= 0 ) {
			urlString = getURL( 'http://', inputurlText );
		} else if ( inputurlText.indexOf( 'https://' ) >= 0 ) {
			urlString = getURL( 'https://', inputurlText );
		} else if ( inputurlText.indexOf( 'www.' ) >= 0 ) {
			urlString = getURL( 'www', inputurlText );
		}

		if ( urlString !== '' ) {
			// check if the url of any of the excluded video oembeds.
			var url_a    = document.createElement( 'a' );
			url_a.href   = urlString;
			var hostname = url_a.hostname;

		}

		if ( '' !== urlString ) {
			loadLinkPreview( urlString );
		}
	}

	var loadLinkPreview = function ( url ) {

		var regexp = /^(http:\/\/www\.|https:\/\/www\.|http:\/\/|https:\/\/)?[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,24}(:[0-9]{1,5})?(\/.*)?$/;
		url        = $.trim( url );
		if ( regexp.test( url ) ) {


			var urlResponse = false;
			if ( loadedURLs.length ) {
				$.each(
					loadedURLs,
					function ( index, urlObj ) {
						if ( urlObj.url == url ) {
							urlResponse = urlObj.response;
							return false;
						}
					}
				);
			}

			if ( loadURLAjax != null ) {
				loadURLAjax.abort();
			}



			if ( ! urlResponse ) {
				loadURLAjax = jQuery.post( ajaxurl, {
						action: 'bp_activity_parse_url_preview',
						'url': url,
					},
					function(response){
						setURLResponse( response, url );
					});


			}
		}
	}
	var getLinkPreviewStorage = function( type, property ) {
		var store = sessionStorage.getItem( type );

		if ( store ) {
			store = JSON.parse( store );
		} else {
			store = {};
		}

		if ( undefined !== property ) {
			return store[property] || false;
		}

		return store;
	}
	var setLinkPreviewStorage = function ( type, property, value ) {
		var store = getLinkPreviewStorage( type );

		if ( undefined === value && undefined !== store[ property ] ) {
			delete store[ property ];
		} else {
			// Set property.
			store[ property ] = value;
		}

		sessionStorage.setItem( type, JSON.stringify( store ) );

		return sessionStorage.getItem( type ) !== null;
	}
	var setURLResponse = function(response, url) {
		if ( $( '#whats-new-attachments' ).length === 0 ) {
			$( '#whats-new-content').after( '<div id="whats-new-attachments"></div>');
		}

		var title 		= response.title;
		var description = response.description;
		var image 		= ( response.images ) ? response.images[0] : '';
		var image_count = ( response.images ) ? response.images.length : 0;

		
		setLinkPreviewStorage( 'bp-activity-link-preview','link-preview',{
							link_success		: true,
							link_url			: url,
							link_title			: response.title,
							link_description	: response.description,
							link_images			: response.images,
							link_image_index	: 0,
						});
						
		
		var image_nav = '';
		if ( image_count === 0) {
			image_nav= 'display:none;';
		}		
		var link_preview = '<div class="activity-url-scrapper-container error"><div class="activity-link-preview-container"><p class="activity-link-preview-title">' + title + '</p><div id="activity-url-scrapper-img-holder" style="' + image_nav +'"><div class="activity-link-preview-image"><img src="' + image + '"><a title="Cancel Preview Image" href="#" id="activity-link-preview-close-image"><i class="dashicons dashicons-no-alt"></i></a></div><div class="activity-url-thumb-nav"><button type="button" id="activity-url-prevPicButton"><span class="dashicons dashicons-arrow-left-alt2"></span></button><button type="button" id="activity-url-nextPicButton"><span class="dashicons dashicons-arrow-right-alt2"></span></button><div id="activity-url-scrapper-img-count">Image 1&nbsp;of&nbsp;' + image_count + '</div></div></div><div class="activity-link-preview-excerpt"><p>' + description + '</p></div><a title="Cancel Preview" href="#" id="activity-close-link-suggestion"><i class="dashicons dashicons-no-alt"></i></a></div><div class="bp-link-preview-hidden"><input type="hidden" name="link_url" value="' + url + '" /><input type="hidden" name="link_title" value="' + title + '" /><input type="hidden" name="link_description" value="' + escapeHtml(description) + '" /><input type="hidden" name="link_image" value="' + image + '" /></div></div>';

		$( '#whats-new-attachments .activity-url-scrapper-container' ).remove();
		$( '#whats-new-attachments' ).append(link_preview);
	}
	var escapeHtml = function(text) {
		  return text
			  .replace(/&/g, "&amp;")
			  .replace(/</g, "&lt;")
			  .replace(/>/g, "&gt;")
			  .replace(/"/g, "&quot;")
			  .replace(/'/g, "&#039;");
		}
	var getURL = function ( prefix, urlText ) {
		var urlString   = '';
		var startIndex  = urlText.indexOf( prefix );
		var responseUrl = '';

		if ( typeof $( $.parseHTML( urlText ) ).attr( 'href' )  !== 'undefined' ) {
			urlString = $( urlText ).attr( 'href' );
		} else {
			for ( var i = startIndex; i < urlText.length; i++ ) {
				if ( urlText[i] === ' ' || urlText[i] === '\n' ) {
					break;
				} else {
					urlString += urlText[i];
				}
			}
			if ( prefix === 'www' ) {
				prefix    = 'http://';
				urlString = prefix + urlString;
			}
		}

		var div       = document.createElement( 'div' );
		div.innerHTML = urlString;
		var elements  = div.getElementsByTagName( '*' );

		while ( elements[0] ) {
			elements[0].parentNode.removeChild( elements[0] );
		}

		if ( div.innerHTML.length > 0 ) {
			responseUrl = div.innerHTML;
		}

		return responseUrl;
	}

	var setURLNextPreviousResponse = function() {
		if ( $( '#whats-new-attachments' ).length === 0 ) {
			$( '#whats-new-content').after( '<div id="whats-new-attachments"></div>');
		}


		var bp_activity_link_preview = getLinkPreviewStorage( 'bp-activity-link-preview','link-preview' );

		var link_image_index 	= bp_activity_link_preview.link_image_index;
		var url     			= bp_activity_link_preview.link_url;
		var title 				= bp_activity_link_preview.link_title;
		var description 		= bp_activity_link_preview.link_description;
		var image 				= bp_activity_link_preview.link_images[link_image_index];
		var image_count 		= bp_activity_link_preview.link_images.length;


		var link_preview = '<div class="activity-url-scrapper-container error"><div class="activity-link-preview-container"><p class="activity-link-preview-title">' + title + '</p><div id="activity-url-scrapper-img-holder"><div class="activity-link-preview-image"><img src="' + image + '"><a title="Cancel Preview Image" href="#" id="activity-link-preview-close-image"><i class="dashicons dashicons-no-alt"></i></a></div><div class="activity-url-thumb-nav"><button type="button" id="activity-url-prevPicButton"><span class="dashicons dashicons-arrow-left-alt2"></span></button><button type="button" id="activity-url-nextPicButton"><span class="dashicons dashicons-arrow-right-alt2"></span></button><div id="activity-url-scrapper-img-count">Image ' + (link_image_index + 1) + '&nbsp;of&nbsp;' + image_count + '</div></div></div><div class="activity-link-preview-excerpt"><p>' + description + '</p></div><a title="Cancel Preview" href="#" id="activity-close-link-suggestion"><i class="dashicons dashicons-no-alt"></i></a></div><div class="bp-link-preview-hidden"><input type="hidden" name="link_url" value="' + url + '" /><input type="hidden" name="link_title" value="' + title + '" /><input type="hidden" name="link_description" value="' + escapeHtml(description) + '" /><input type="hidden" name="link_image" value="' + image + '" /></div></div>';

		$( '#whats-new-attachments .activity-url-scrapper-container' ).remove();
		$( '#whats-new-attachments' ).append(link_preview);
	}

	$(document).ready(function() {

		$(document).on('keyup', '#whats-new', function() {
			setTimeout(
					function () {
						scrap_URL( $('#whats-new').val() );
					},
					500
				);
		});
		$(document).on('click', '#activity-url-prevPicButton', function() {
			var bp_activity_link_preview = getLinkPreviewStorage( 'bp-activity-link-preview','link-preview' );
			var imageIndex 			= bp_activity_link_preview.link_image_index;
			var images     			= bp_activity_link_preview.link_images;
			var url     			= bp_activity_link_preview.link_url;
			var link_success     	= bp_activity_link_preview.link_success;
			var link_title     		= bp_activity_link_preview.link_title;
			var link_description	= bp_activity_link_preview.link_description;

			if ( imageIndex > 0 ) {
				
				setLinkPreviewStorage( 'bp-activity-link-preview','link-preview',{
							link_success		: true,
							link_url			: url,
							link_title			: link_title,
							link_description	: link_description,
							link_images			: images,
							link_image_index	: imageIndex - 1,
						});
				
				setURLNextPreviousResponse();
			}
		});

		$(document).on('click', '#activity-url-nextPicButton', function() {
			var bp_activity_link_preview = getLinkPreviewStorage( 'bp-activity-link-preview','link-preview' );
			
			var imageIndex 			= bp_activity_link_preview.link_image_index;
			var images     			= bp_activity_link_preview.link_images;
			var url     			= bp_activity_link_preview.link_url;

			var link_success     	= bp_activity_link_preview.link_success;
			var link_title     		= bp_activity_link_preview.link_title;
			var link_description	= bp_activity_link_preview.link_description;

			if ( imageIndex < images.length - 1 ) {
				
				setLinkPreviewStorage( 'bp-activity-link-preview','link-preview',{
							link_success		: true,
							link_url			: url,
							link_title			: link_title,
							link_description	: link_description,
							link_images			: images,
							link_image_index	: imageIndex + 1,
						});
				
				setURLNextPreviousResponse();

			}
		});
		$(document).on('click', '#buddypress #aw-whats-new-submit', function() {			
			setTimeout(
					function () {						
						$('.activity-url-scrapper-container').remove();
					},
					500
				);
		});

		$(document).on('click', '#activity-close-link-suggestion', function() {
			$('.activity-url-scrapper-container').remove();
		});
	});
})(jQuery);
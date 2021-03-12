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
				loadURLAjax = bp.ajax.post( 'bp_activity_parse_url_preview', { url: url } ).always(
					function ( response ) {
						setURLResponse( response, url );
					}
				);
			} 
		}
	}
	var setURLResponse = function(response, url) {
		if ( $( '#whats-new-attachments' ).length === 0 ) {
			$( '#whats-new-content').after( '<div id="whats-new-attachments"></div>');
		}
		
		var title 		= response.title;
		var description = response.description;
		var image 		= response.images[0];
		var image_count = response.images.length;
		
		var link_preview = '<div class="activity-url-scrapper-container"><div class="activity-link-preview-container"><p class="activity-link-preview-title">' + title + '</p><div id="activity-url-scrapper-img-holder"><div class="activity-link-preview-image"><img src="' + image + '"><a title="Cancel Preview Image" href="#" id="activity-link-preview-close-image"><i class="bb-icons bb-icon-close"></i></a></div><div class="activity-url-thumb-nav"><button type="button" id="activity-url-prevPicButton"><span class="bb-icons bb-icon-angle-left"></span></button><button type="button" id="activity-url-nextPicButton"><span class="bb-icons bb-icon-angle-right"></span></button><div id="activity-url-scrapper-img-count">Image 1&nbsp;of&nbsp;' + image_count + '</div></div></div><div class="activity-link-preview-excerpt"><p>' + description + '</p></div><a title="Cancel Preview" href="#" id="activity-close-link-suggestion"><i class="bb-icons bb-icon-close"></i></a></div></div>';
		
		$( '#whats-new-attachments .activity-url-scrapper-container' ).remove();
		$( '#whats-new-attachments' ).append(link_preview);
	}
	var getURL = function ( prefix, urlText ) {
		var urlString   = '';
		var startIndex  = urlText.indexOf( prefix );
		var responseUrl = '';

		if ( ! _.isUndefined( $( $.parseHTML( urlText ) ).attr( 'href' ) ) ) {
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
	$(document).ready(function() {
		$(document).on('keyup', '#whats-new', function() {
			
			setTimeout(
					function () {
						console.log($('#whats-new').val());
						scrap_URL( $('#whats-new').val() );
					},
					500
				);
		});
	});
})(jQuery);
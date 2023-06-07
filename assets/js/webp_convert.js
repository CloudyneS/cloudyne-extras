function disableConversionButtons(){
    jQuery('.convert-all-images, .convert-missing-images').attr('disabled', 'disabled');
    jQuery('#notice-save-first').show();
}

jQuery(document).ready(function($){
    $('input, textarea').on('change', function() {
        disableConversionButtons();
    });

	$('#jstree').jstree({
		core: {
		    data: {
				url: ajaxurl,
				type: 'POST',
				data: function( node ){
					return {
						action: 'cywebp_list_directories',
						security: $('[name=_sec_wpnonce]').val(),
						_wp_http_referer: $('[name=_wp_http_referer]').val(),
						folder: node.id
					};
				}
			},
			themes: {
				variant: 'large'
			}
		},
		checkbox: {
			keep_selected_style: false
		},
		plugins: [ 'wholerow', 'checkbox' ]
	});
    
	$('.convert-all-images, .convert-missing-images').click(function(event){
		event.preventDefault();
		window.selected_folders = $('#jstree').jstree().get_top_checked();
		if( selected_folders.length ){
            let CONVERT_MISSING = $(this).hasClass('convert-missing-images');
            console.log("Only converting missing images: " + CONVERT_MISSING)
			$('#transparency_status_message span').text( transparency_status_message );
			$('#transparency_status_message').show();
			$('#hide-on-convert').hide();
			$('#show-on-convert').prepend('<span>Loading all subdirectories...</span>');
			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'cywebp_list_subdirectories_recursive',
                    security: $('[name=_sec_wpnonce]').val(),
					_wp_http_referer: $('[name=_wp_http_referer]').val(),
					folders: selected_folders
				}
			})
			.done(function( response, statusText, xhr ){
				$('#show-on-convert').prepend('<span>Directories loaded successfully.</span><br>');
				window.selected_folders = response;
                console.log("Missing images: " + CONVERT_MISSING);
				convert_media_library( CONVERT_MISSING ? 1 : 0 );
			})
			.fail(function( xhr, textStatus ){
				$('#show-on-convert').prepend( '<span>' + xhr.status + '</span><br>' );
				console.log( xhr, textStatus );
			});
		}
	});

	function convert_media_library( only_missing, folder ){
		if( selected_folders.length || folder ){
			var folder = folder || selected_folders.shift();
			$('#show-on-convert').prepend('<div>' + folder + '/ ... </div>');
			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'cywebp_convert_media_library',
                    security: $('[name=_sec_wpnonce]').val(),
					_wp_http_referer: $('[name=_wp_http_referer]').val(),
					only_missing: only_missing,
					folder: folder
				}
			})
			.done(function( response, statusText, xhr ){
                console.log(response);
				$('#show-on-convert div').first().append( response );
				convert_media_library( only_missing );
			})
			.fail(function( xhr, textStatus ){
				$('#show-on-convert div').first().append( error_message.replace( '{{ERROR}}', xhr.status ) );
				convert_media_library( 1, folder );
			});
		}else{
			$('#transparency_status_message').hide();
			$('#show-on-convert').prepend('<div>DONE.</div>');
		}
	}
});

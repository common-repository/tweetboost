
/**
 * On page load perform JS events
 */
jQuery(document).ready(function() {

    /* check for set tab */
    var link_tab = window.location.hash.substr(1);
    var original_url = window.location.href;

    /* remove deep link from original_url variable if found */
    if (original_url.indexOf("#")) {
        var parts = original_url.split("#");
        original_url = parts[0];
    }

    /* if tab is set then click tab on page load */
    if(link_tab){
        setTimeout(function() {
            jQuery( '.nav-tab[data-tab-id="' + link_tab + '"]').click();
            console.log('here .nav-tab[data-tab-id="' + link_tab + '"]')
        } , 500 );

    } else {
        jQuery( '#tab-welcome' ).show();
    }

    /* add event listener to handle tab switching */
    jQuery(".nav-tab").on("click", function(e) {

        /* hide all displayed tabs */
        jQuery( '.tab-container' ).hide();

        /* remove active status of all tabs */
        jQuery(".nav-tab").removeClass("tb-tab-active");

        /* add active status to selected tab */
        jQuery(this).addClass("tb-tab-active");

        /* get tab id from clicked tab */
        var tab_id = jQuery(this).attr('data-tab-id');
        console.log(tab_id);

        /* display paired tab container */
        jQuery('#tab-' + tab_id).show();

        history.pushState({}, "", original_url + '#' + tab_id );

        /* hide save feature from welcome screen */
        switch (tab_id) {
            case 'welcome':
                jQuery( '.tab-footer' ).hide();
                break;
            default:
                jQuery( '.tab-footer' ).show();
                break;
        }

        jQuery( '#tab-footer' ).show();

    });


    /**
     * Adds the media upload functionality to the settings page on page load.
     * Copied with minor modification from: https://codex.wordpress.org/Javascript_Reference/wp.media
     **/
    function addTwitterAvatarUploadFunctionality(){

        // Set all variables to be used in scope
        var frame,
        metaBox = jQuery('#twitter-avatar-uploader'),
        addImgLink = metaBox.find('.upload-avatar-image'),
        delImgLink = metaBox.find( '.remove-avatar-image'),
        imgContainer = metaBox.find( '.twitter-avatar-container'),
        imgIdInput = metaBox.find( '.twitter_avatar_id' );

        // ADD IMAGE LINK
        addImgLink.on( 'click', function( event ){

            event.preventDefault();

            // If the media frame already exists, reopen it.
            if ( frame ) {
                frame.open();
                return;
            }

            // Create a new media frame
            frame = wp.media({
                title: 'Select or Upload Media',
                button: {
                    text: 'Select'
                },
                multiple: false  // Set to true to allow multiple files to be selected
            });


            // When an image is selected in the media frame...
            frame.on( 'select', function() {

                // Get media attachment details from the frame state
                var attachment = frame.state().get('selection').first().toJSON();

                // Send the attachment URL to our custom image input field.
                imgContainer.append( '<img src="'+attachment.url+'" alt="" style="max-width:45px;"/>' );

                // Send the attachment id to our hidden input
                imgIdInput.val( attachment.id );

                // Hide the add image link
                addImgLink.addClass( 'hidden' );

                // Unhide the remove image link
                delImgLink.removeClass( 'hidden' );
            });

            // Finally, open the modal on click
            frame.open();
        });
          
          
        // DELETE IMAGE LINK
        delImgLink.on( 'click', function( event ){

        event.preventDefault();

        // Clear out the preview image
        imgContainer.html( '' );

        // Un-hide the add image link
        addImgLink.removeClass( 'hidden' );

        // Hide the delete image link
        delImgLink.addClass( 'hidden' );

        // Delete the image id from the hidden input
        imgIdInput.val( '' );

        });
    }

    /* make the call */
    addTwitterAvatarUploadFunctionality();


    /* listen for setting saves commands  */
    jQuery('#save_settings').click(function() {

        /* set temp saving message */
        jQuery('#save_settings').text(tweetBoostSettingsVars.saveButton.saving);

        /* get all form data */
        var tbdata = jQuery('#tweet-boost-settings').serialize();

        /* grab all input data */
        jQuery.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                action: 'save_tweetboost_settings',
                settings: tbdata,
            },
            dataType: 'json',
            timeout: 20000,
            success: function (response) {
                console.log(response);
                /* handle actions here */
                jQuery('#save_settings').text(tweetBoostSettingsVars.saveButton.saved);
                setTimeout(function() {
                    jQuery('#save_settings').text(tweetBoostSettingsVars.saveButton.save);
                } , 700 );
            },
            error: function (request, status, err) {
                console.log(err);
                /* handle error here */
                jQuery('#save_settings').text(tweetBoostSettingsVars.saveButton.error);
            }
        });
    });

});

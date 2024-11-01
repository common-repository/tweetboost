jQuery(window).on('load', function(){

    /**
     * Displays the tweet's status message if one is availible
     **/
    function displayTweetStatusMessage(){
        /* get the post's tweet */
        var tweet = jQuery('#acf-group_5989061655cc1 .acf-fields').get();
        /* try getting the tweet's status data */
        var storedData = jQuery(tweet).find('input#acf-field_59af60df2fbb0').attr('data-tweet-status');
        /* if there is stored data parse the JSON */
        if(isJson(storedData)){

            storedData = JSON.parse(storedData);
            /* if there is a message to show */
            if(storedData.displayed_message){
                /* get the message field */
                var messageField = jQuery(tweet).find('.acf-field.acf-field-message.acf-field-599743247e1b1 div.acf-input');
                /* if the user hasn't set the tooltip to display */
                if(tweetBoostAdminScriptsVars.simplifyStatusMessage == 0){
                    /* display the full status message */
                    messageField.html('<p>' + storedData.displayed_message + '</p>');
                }else{
                    /** If the user has opted to turn the status messages into tooltips,
                     *  do some figuring to determine what kind of tooltip to show. **/
                    if(!storedData.stop_processing){
                        /**
                         * if the tweet has a status message but hasn't been attempted yet,
                         * it must be scheduled and just waiting to go. **/
                        if(storedData['tweet-not-sent'] === undefined){
                            /* get the distance between the time at page load and when the tweet is supposed to go out */
                            var timeArray = jQuery(tweet).find('.acf-field.acf-field-598906629db22 div.acf-input #acf-field_598906629db22').val().split('-').join(',').split(':').join(',').split(' ').join(',').split(',');
                            var tweetTimestamp = (Date.UTC(timeArray[0],(timeArray[1].replace('0', '') -1),timeArray[2],timeArray[3],timeArray[4],timeArray[5])/1000);
                            var timeDifference = tweetTimestamp - tweetBoostAdminScriptsVars.currentTime;

                            /* if the tweet is going to be made in less than 10 minutes,
                             * create a paper airplane icon to let the user know that the tweet is going out shortly */
                            if(timeDifference < 600){
                                /* use the correct message for the numberof minutes left before publishing */
                                if(timeDifference <= 60){
                                    messageField.html('<p class="tippy-status-arrow-tooltip tweet-schedule-status-tooltip tweet-scheduled-status-tooltip" title="' + tweetBoostAdminScriptsVars.tweetComingSoonMsgs.singular.replace(/"/g, '&quot;') + '"><i class="fa fa-paper-plane-o"></i></p>');
                                }else{
                                    messageField.html('<p class="tippy-status-arrow-tooltip tweet-schedule-status-tooltip tweet-scheduled-status-tooltip" title="' + tweetBoostAdminScriptsVars.tweetComingSoonMsgs.plural.replace(/"/g, '&quot;').replace('{{X}}', Math.ceil(timeDifference/60)) + '"><i class="fa fa-paper-plane-o"></i></p>');
                                }
                            }else{
                                /* if the tweet is scheduled and and it's going out later than 10 mins from now, create a tweet scheduled icon */
                                messageField.html('<p class="tippy-status-arrow-tooltip tweet-schedule-status-tooltip tweet-scheduled-status-tooltip" title="' + storedData.displayed_message.replace(/"/g, '&quot;') + '"><i class="fa fa-calendar-check-o"></i></p>');
                            }
                        }else{
                            /* if no status is given, create a notice icon */
                            messageField.html('<p class="tippy-status-arrow-tooltip tweet-schedule-status-tooltip notice-status-tooltip" title="' + storedData.displayed_message.replace(/"/g, '&quot;') + '"><i class="fa fa-exclamation-triangle"></i></p>');
                        }
                    }else if(storedData.stop_processing_reason && storedData.stop_processing_reason === 'success'){
                        /* if the tweet has been successfully made, create a success! icon */
                        messageField.html('<p class="tippy-status-arrow-tooltip tweet-schedule-status-tooltip success-status-tooltip" title="' + storedData.displayed_message.replace(/"/g, '&quot;') + '"><i class="fa fa-smile-o"></i></p>');

                    }else if(storedData.stop_processing_reason && (storedData.stop_processing_reason === '400_error' || storedData.stop_processing_reason === '500_error')){
                        /* if the tweet had a run stopping 400 or 500 error, create an error icon */
                        messageField.html('<p class="tippy-status-arrow-tooltip tweet-schedule-status-tooltip error-status-tooltip" title="' + storedData.displayed_message.replace(/"/g, '&quot;') + '"><i class="fa fa-times-circle-o"></i></p>');

                    }else if(storedData.stop_processing_reason && storedData.stop_processing_reason === 'schedule_paused'){
                        /* if the schedule has been paused, create a pause icon */
                        messageField.html('<p class="tippy-status-arrow-tooltip tweet-schedule-status-tooltip pause-status-tooltip" title="' + storedData.displayed_message.replace(/"/g, '&quot;') + '"><i class="fa fa-pause-circle-o"></i></p>');

                    }else if(storedData.stop_processing_reason && storedData.stop_processing_reason === 'tweet_expired'){
                        /* if the schedule has been paused, create a pause icon */
                        messageField.html('<p class="tippy-status-arrow-tooltip tweet-schedule-status-tooltip expired-status-tooltip" title="' + storedData.displayed_message.replace(/"/g, '&quot;') + '"><i class="fa fa-calendar-times-o"></i></p>');

                    }else{
                        /* if no stop processing reason was given, or we've come across an error that isn't accounted for here, create a fallback notice icon */
                        messageField.html('<p class="tippy-status-arrow-tooltip tweet-schedule-status-tooltip notice-status-tooltip" title="' + storedData.displayed_message.replace(/"/g, '&quot;') + '"><i class="fa fa-exclamation-triangle"></i></p>');

                    }

                    /* add the tooltip to the status icon */
                    tippy('.tippy-status-arrow-tooltip', {'position': 'top', 'arrow': true, 'followCursor': true});
                }
            }

            /* if the tweet isn't supposed to be processed, and the reason is because of one of the normal errors */
            if( storedData.stop_processing_reason &&
               (storedData.stop_processing_reason === '400_error' ||
                storedData.stop_processing_reason === '500_error' ||
                storedData.stop_processing_reason === 'no_curl_error' ||
                storedData.stop_processing_reason === 'no_connect_error'))
            {

                /* and set the tweet error class for the tweet status field */
                jQuery(tweet).find('.acf-field.acf-field-599743247e1b1').addClass('tweet-has-error');
            }
        }
    }
    /* make the call */
    displayTweetStatusMessage();

    /** 
     * Disables the tweet boost status reset button if there isn't an error with its tweet 
     **/
    function disableTweetStatusResetButton(){
        /* get the tweet's fields */
        var tweet = jQuery('#acf-group_5989061655cc1 .acf-fields').get();
        /* find the reset button for the tweet */
        var button = jQuery(tweet).find('.acf-field-59d2b549ed77d input[type=button]')[0];
        /* get the tweet status data */
        var statusData = jQuery('input#acf-field_59af60df2fbb0').attr('data-tweet-status');
        /* if there is status data stored as json */
        if(isJson(statusData)){
            /* parse the data */
            statusData = JSON.parse(statusData);
            /* if there is a message to display, and the tweet hasn't been successfully made, paused or expired */
            if(statusData.displayed_message && statusData['200'] == undefined && statusData.stop_processing_reason != 'schedule_paused' && statusData.stop_processing_reason !== 'tweet_expired'){
                /* remove the acf disabled class and enable the button if it isn't already */
                jQuery(button).removeClass('acf-disabled').prop('disabled', false);
                /* add the reset button click and touch handlers */
                jQuery(button)                
                    .off('touchstart.mobile-tweet-boost-status-reset') /* todo make sure this works on mobile */
                    .on('touchstart.mobile-tweet-boost-status-reset', tweetStatusResetButtonHandler)
                    .off('click.desktop-tweet-boost-status-reset')
                    .on('click.desktop-tweet-boost-status-reset', tweetStatusResetButtonHandler);
            }else{
            /* if there isn't a message to display or the tweet has been made successfully, disable the button */
                jQuery(button).prop('disabled', true).addClass('tweet-boost-reset-status-button-disabled');
            }
        }else{
        /* if there isn't any status data disable the button */    
            jQuery(button).prop('disabled', true).addClass('tweet-boost-reset-status-button-disabled');
        }
    }
    
    /* make the call */
    disableTweetStatusResetButton();
    
    /** 
     * Creates the popup requestion confirmation to reset the tweet status data.
     * If the user confirms, it makes the call to an ajax function to delete the status data
     **/
    function tweetStatusResetButtonHandler(event){
        var postId = jQuery('#post_ID').val();
        var messageField = jQuery(jQuery(event.target).parents('.acf-fields')[0]).find('.acf-field-599743247e1b1 .acf-input')[0];

        /** create the sweetalert confirm popup **/
        swal({
            title: tweetBoostAdminScriptsVars.confirmPopup.title,
            text: tweetBoostAdminScriptsVars.confirmPopup.text,
            type: 'info',
            showCancelButton: true,
            confirmButtonColor: "#2ea2cc",
            confirmButtonText: tweetBoostAdminScriptsVars.confirmPopup.buttonText,
            closeOnConfirm: false
        }, function () {
        /* if the user confirms, call ajaxEraseTweetStatusData to erase the data */
            ajaxEraseTweetStatusData(postId, messageField, event.target);
            /* immediately after the call is made, show the waiting popup */
            swal({
                title: tweetBoostAdminScriptsVars.waitPopup.title,
                text: tweetBoostAdminScriptsVars.waitPopup.text,
                imageUrl: tweetBoostAdminScriptsVars.waitPopup.waitingGif
            });
        });        
    }
    
    /**
     * Makes the ajax call to delete the tweet status data for the current tweet.
     * Also updates the user on the success or failure of the call
     **/
    function ajaxEraseTweetStatusData(postId, messageField, button){
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tweet_boost_reset_tweet_status',
                post_id: postId,
                nonce: tweetBoostAdminScriptsVars.eraseStatusNonce                
            },
            success: function(response){
                /* if the data was successfully deleted, tell the user about it and update the admin screen */
                if(response.success){
                    /* create a success message! */
                    swal({type: 'success', title: response.title, text: response.success});
                    /* clear the message field */
                    jQuery(messageField).empty().parents('td').removeClass('tweet-has-error');
                    /* clear the status data field */
                    jQuery('#acf-group_5989061655cc1 .acf-field-59af60df2fbb0 .tweet-boost-hidden-data-field').attr('data-tweet-status', '');
                    /* refresh the calendar */
                    jQuery('#acf-group_5989061655cc1 .acf-field-59d2b549ed77d .acf-input .button-primary').trigger('resetButtonPressed.all-tweet-boost-calendar-tweet-status-reset-listener');
                    /* disable the reset button */
                    jQuery(button).prop('disabled', true).addClass('tweet-boost-reset-status-button-disabled');
                    /* and reset the status message */
                    displayTweetStatusMessage();

                }else if(response.error){
                /* if there was an error in trying to delete the status data */
                    /* create an error message telling the user that deletion has failed and suggesting that he reload the page in case the error was related to that */
                    swal({type: 'error', title: response.title, text: response.error});
                }
            }
        });        
    }

    /**
     * Creates a button that allows users to add a link-token to their tweet,
     * and creates a tooltip that tells the user if the tweet doesn't have a link in it.
     * Tokens make the tweet content neater and function if the user changes the permalink structure.
     **/
    function createTweetLinkUIElements(){

        /* if the tweet content container doesn't have a "Add Post Link" button */
        if(jQuery('#acf-group_5989061655cc1').find('.acf-field-textarea.acf-field-598906cf9db23 input.add-post-link-button').length === 0){

            /* create the "Add Post Link" button */
            jQuery('#acf-group_5989061655cc1').find('.acf-field-textarea.acf-field-598906cf9db23').append(
                '<div class="add-post-link-button-container">\
                    <input type="button" class="add-post-link-button button button-primary button-large" value="' + tweetBoostAdminScriptsVars.postLinkTokenButton.text + '" title="'+ tweetBoostAdminScriptsVars.postLinkTokenButton.title +'">\
                </div>');
        }
        
        /* add the tooltip telling the user what the "Add Post Link" button does */
        tippy('.add-post-link-button', {'position': 'top', 'arrow': true, 'followCursor': true});
    }
    
    /* make the call */
    createTweetLinkUIElements();
    
    /**
     * Listens for the user clicking on the add link token button
     **/
    function addPostLinkButtonListeners(){
        jQuery('.add-post-link-button')                
            .off('touchstart.mobile-tweet-boost-add-post-link') /* todo make sure this works on mobile */
            .on('touchstart.mobile-tweet-boost-add-post-link', addPostLinkToken)
            .off('click.desktop-tweet-boost-add-post-link')
            .on('click.desktop-tweet-boost-add-post-link', addPostLinkToken);
    }

    /* make the call */
    addPostLinkButtonListeners();

    /**
     * Adds a post link token to the tweet content whent the user clicks on the button
     **/
    function addPostLinkToken(event){
        /* get the tweet's content field and get the tweet content */
        var tweetContentField = jQuery(event.target).parents('.acf-field.acf-field-textarea.acf-field-598906cf9db23').find('textarea');
        var tweetContentText = jQuery(tweetContentField).val();

        /* if adding the post link won't push the tweet over the char limit */
        if((tweetContentField[0].textLength + 23) < tweetContentField[0].maxLength){
            /* append the post link token to the tweet content */
            jQuery(tweetContentField).val(tweetContentText + '{post-link}');
            
            /* fire the keyup event to activate the link accounter */
            jQuery(tweetContentField).trigger('keyup.desktop-tweet-boost-url-listener');
        }else{
        /* if adding the link will push the tweet over the limit, output a message telling the user about it */
            swal({
                title: tweetBoostAdminScriptsVars.addPostLinkLengthWarningPopup.title,
                text: tweetBoostAdminScriptsVars.addPostLinkLengthWarningPopup.text,
                type: 'error',
                confirmButtonColor: "#2ea2cc",
                confirmButtonText: tweetBoostAdminScriptsVars.addPostLinkLengthWarningPopup.buttonText,
                closeOnConfirm: false
            });
            
        }
    }

    /** 
     * Attaches the url listener and the post link listener to the tweet content field.
     * 
     * The url listener increases the tweet content area's char limit when links are added to the tweet to
     * simulate the twitter link shortener.
     * 
     * The post link listener adds a highlight to the content area if there isn't a {post-link} token in the content.
     **/
    function attachUrlListener(){
            /* get the content field */
            var contentField = jQuery(jQuery('#acf-group_5989061655cc1 .acf-fields').get()).find('.acf-field-textarea.acf-field-598906cf9db23 textarea')[0];
            /* first clear the url listener from the event list to avoid duplicate events, then add the url listener */
            jQuery(contentField)
                .off('keyup.desktop-tweet-boost-url-listener')
                .on('keyup.desktop-tweet-boost-url-listener', urlListener)
                .off('blur.tweet-boost-tweet-content-deselect')
                .on('blur.tweet-boost-tweet-content-deselect', checkForTweetLinks); // todo make sure that the url listener works on mobile

            /* trigger the URL accounter to setup the char limit */
            jQuery(contentField).trigger('keyup.desktop-tweet-boost-url-listener'); //todo make sure these don't do anything weird on mobile
            /* trigger the link checker to highlight tweets without links */
            jQuery(contentField).trigger('blur.tweet-boost-tweet-content-deselect'); //todo make sure these don't do anything weird on mobile
    }
    
    /* make the call */
    attachUrlListener();

    /**
     * Listens for urls and post link tokens being added into tweets.
     * If a url longer than 23 chars is added, it increases tweet content's max length to accomodate the url.
     * If a post link token is added, the content's max size is reduced to make sure the minified link can fit in the tweet
     **/
    function urlListener(event){
        var urlCheck = /((?:(http|https|Http|Https|rtsp|Rtsp):\/\/(?:(?:[a-zA-Z0-9\$\-\_\.\+\!\*\'\(\)\,\;\?\&\=]|(?:\%[a-fA-F0-9]{2})){1,64}(?:\:(?:[a-zA-Z0-9\$\-\_\.\+\!\*\'\(\)\,\;\?\&\=]|(?:\%[a-fA-F0-9]{2})){1,25})?\@)?)?((?:(?:[a-zA-Z0-9][a-zA-Z0-9\-]{0,64}\.)+(?:(?:aero|arpa|asia|a[cdefgilmnoqrstuwxz])|(?:biz|b[abdefghijmnorstvwyz])|(?:cat|com|coop|c[acdfghiklmnoruvxyz])|d[ejkmoz]|(?:edu|e[cegrstu])|f[ijkmor]|(?:gov|g[abdefghilmnpqrstuwy])|h[kmnrtu]|(?:info|int|i[delmnoqrst])|(?:jobs|j[emop])|k[eghimnrwyz]|l[abcikrstuvy]|(?:mil|mobi|museum|m[acdghklmnopqrstuvwxyz])|(?:name|net|n[acefgilopruz])|(?:org|om)|(?:pro|p[aefghklmnrstwy])|qa|r[eouw]|s[abcdeghijklmnortuvyz]|(?:tel|travel|t[cdfghjklmnoprtvwz])|u[agkmsyz]|v[aceginu]|w[fs]|y[etu]|z[amw]))|(?:(?:25[0-5]|2[0-4][0-9]|[0-1][0-9]{2}|[1-9][0-9]|[1-9])\.(?:25[0-5]|2[0-4][0-9]|[0-1][0-9]{2}|[1-9][0-9]|[1-9]|0)\.(?:25[0-5]|2[0-4][0-9]|[0-1][0-9]{2}|[1-9][0-9]|[1-9]|0)\.(?:25[0-5]|2[0-4][0-9]|[0-1][0-9]{2}|[1-9][0-9]|[0-9])))(?:\:\d{1,5})?)(\/(?:(?:[a-zA-Z0-9\;\/\?\:\@\&\=\#\~\-\.\+\!\*\'\(\)\,\_])|(?:\%[a-fA-F0-9]{2}))*)?(?:\b|$)/gi;
        var tweetContentField = jQuery(event.target).val();
        var tweetContentUrls = tweetContentField.match(urlCheck);
        var postLinkTokenCheck = /{post-link}/g;
        var tweetContentPostLinkTokens = tweetContentField.match(postLinkTokenCheck);
        var maxLength = 280;
        
        /* if there are urls in the content */
        if(tweetContentUrls){
            /* loop through each url */
            for(var i = 0; i < tweetContentUrls.length; i++){
                var linkLength = tweetContentUrls[i].length;
                /* if the url is longer than 23 */
                if(linkLength > 23){
                    /* increase the max length stat of the tweet content field by
                     * the link length minus the 23 used by the twitter link shortener */
                    maxLength += (linkLength - 23);
                }
            }
            
            /* call checkForTweetLinks to clear the "No Link" notification */
            checkForTweetLinks(event);
        }

        /* if there are post link tokens in the content */
        if(tweetContentPostLinkTokens){
            /* loop through each token */
            for(var i = 0; i < tweetContentPostLinkTokens.length; i++){
                /* and shorten the max length of the content field to
                 * account for the link that will be put in place of the token */
                maxLength -= 12;
            }
            /* then find the textarea's parent table cell */
            var tweetContentParent = jQuery(event.target).parents('.acf-field.acf-field-textarea')[0];
            /* and from it, find the add link token button and disable it */
            jQuery(tweetContentParent).find('.add-post-link-button').prop('disabled', true).addClass('acf-disabled tweet-boost-add-link-button-disabled');
            /* call checkForTweetLinks to clear the "No Link" notification */
            checkForTweetLinks(event);
        }else{
        /* if there aren't any post link tokens in the content */
            /* find the textarea's parent table cell */
            var tweetContentParent = jQuery(event.target).parents('.acf-field.acf-field-textarea')[0];
            /* then find the post link token button and make sure it's enabled */
            jQuery(tweetContentParent).find('.add-post-link-button').prop('disabled', false).removeClass('acf-disabled tweet-boost-add-link-button-disabled');            
        }

        /* update the tweet content field maxlength */
        jQuery(event.target).prop('maxlength', maxLength);
    }

    /**
     * Checks for links when the tweet content area is deselected.
     * If there aren't links in the tweet content, checkForTweetLinks adds some css to notify the user about it
     **/
    function checkForTweetLinks(event){
        /* find the links and link tokens in the tweet content */
        var urlCheck = /((?:(http|https|Http|Https|rtsp|Rtsp):\/\/(?:(?:[a-zA-Z0-9\$\-\_\.\+\!\*\'\(\)\,\;\?\&\=]|(?:\%[a-fA-F0-9]{2})){1,64}(?:\:(?:[a-zA-Z0-9\$\-\_\.\+\!\*\'\(\)\,\;\?\&\=]|(?:\%[a-fA-F0-9]{2})){1,25})?\@)?)?((?:(?:[a-zA-Z0-9][a-zA-Z0-9\-]{0,64}\.)+(?:(?:aero|arpa|asia|a[cdefgilmnoqrstuwxz])|(?:biz|b[abdefghijmnorstvwyz])|(?:cat|com|coop|c[acdfghiklmnoruvxyz])|d[ejkmoz]|(?:edu|e[cegrstu])|f[ijkmor]|(?:gov|g[abdefghilmnpqrstuwy])|h[kmnrtu]|(?:info|int|i[delmnoqrst])|(?:jobs|j[emop])|k[eghimnrwyz]|l[abcikrstuvy]|(?:mil|mobi|museum|m[acdghklmnopqrstuvwxyz])|(?:name|net|n[acefgilopruz])|(?:org|om)|(?:pro|p[aefghklmnrstwy])|qa|r[eouw]|s[abcdeghijklmnortuvyz]|(?:tel|travel|t[cdfghjklmnoprtvwz])|u[agkmsyz]|v[aceginu]|w[fs]|y[etu]|z[amw]))|(?:(?:25[0-5]|2[0-4][0-9]|[0-1][0-9]{2}|[1-9][0-9]|[1-9])\.(?:25[0-5]|2[0-4][0-9]|[0-1][0-9]{2}|[1-9][0-9]|[1-9]|0)\.(?:25[0-5]|2[0-4][0-9]|[0-1][0-9]{2}|[1-9][0-9]|[1-9]|0)\.(?:25[0-5]|2[0-4][0-9]|[0-1][0-9]{2}|[1-9][0-9]|[0-9])))(?:\:\d{1,5})?)(\/(?:(?:[a-zA-Z0-9\;\/\?\:\@\&\=\#\~\-\.\+\!\*\'\(\)\,\_])|(?:\%[a-fA-F0-9]{2}))*)?(?:\b|$)/gi;
        var tweetContentField = jQuery(event.target).val();
        var tweetContentUrls = tweetContentField.match(urlCheck);
        var postLinkTokenCheck = /{post-link}/g;
        var tweetContentPostLinkTokens = tweetContentField.match(postLinkTokenCheck);        

        /* if there is content in the textarea */
        if(tweetContentField){
            /* if there aren't any links or tokens in the tweet content  */
            if(tweetContentUrls === null && tweetContentPostLinkTokens === null){
                /* set the css classes to indicate there isn't a link in the content */
                jQuery(event.target).parent().addClass('doesnt-have-tweet-link').removeClass('has-tweet-link').parent().addClass('doesnt-have-tweet-link').removeClass('has-tweet-link').removeClass('hide-link-tooltip');
            }else{
            /* if there are links or tokens in the tweet content */
                /* set the css classes to indicate there is a link in the content */
                jQuery(event.target).parent().addClass('has-tweet-link').removeClass('doesnt-have-tweet-link').parent().addClass('has-tweet-link').removeClass('doesnt-have-tweet-link');
            }
        }else{
        /* if there isn't any tweet content */
            /* remove the "No Link" notification and set the css classes to hide the tooltip that would tell the user that there isn't a link */
            jQuery(event.target).parent().removeClass('doesnt-have-tweet-link').parent().removeClass('doesnt-have-tweet-link').addClass('hide-link-tooltip');            
        }
    }

    /** helper function for testing if a string is JSON **/
    function isJson(str) {
        if (str == 'null') {
            return false;
        }
        try {
            JSON.parse(str);
        } catch (e) {
            return false;
        }
        return true;
    }
    
});

jQuery(document).ready(function(){
    /* create an array for the stored tweet data */
    var globalStoredTweetData = [];
    /* if possible, create an object for the stored tweet data */
    if(isJson(jQuery('#tweet-boost-ms-schedule-data').attr('data-schedule-data'))){
        var globalStoredTweetScheduleData = JSON.parse(jQuery('#tweet-boost-ms-schedule-data').attr('data-schedule-data'));
    }else{
        var globalStoredTweetScheduleData = {};
    }

    /* create a timeout for touch devices to reduce multi actions */
    var touchWait;

    /* initialize tooltips */
    tippy('.tippy' , {
        position: 'bottom',
        animation: 'shift',
        duration: 200,
        arrow: true,
        followCursor: true
    });

    /* load tweet status oembed */
    var oembed_opened = [];
    jQuery('.view-tweet').click(function(e) {
        /* prevent default link behavior */
        e.preventDefault();

        /* get tweet id */
        var tweet_id = jQuery(this).attr('data-tweet-id');
        var tweet_container = document.getElementById( tweet_id );

        /* hide all other tweets */
        jQuery('.tweet-oembed').hide();

        /* Render oembed if not rendered yet */
        if (jQuery.inArray(tweet_id, oembed_opened) === -1) {
            /* render oembed */
            twttr.widgets.createTweet( tweet_id, tweet_container, {
                //conversation : 'none',    // or all
                //cards        : 'hidden',  // or visible
                //linkColor    : '#cc0000', // default is blue
                //theme        : 'light'    // or dark
            });

            /* add tweet id to omebed_opened */;
            oembed_opened.push(tweet_id)
        }

        /* show tweet */
        jQuery('#tweet-' + tweet_id).show();
    });

    /**
     * Configures the Tweet Management widget's "Status" section and it's attendent icon to
     * show the user an overall picture of how the site's tweets are doing.
     **/
    function setupTweetScheduleManagementWidget(){
        var tweets = jQuery('#tweet-boost-schedule-management-table .tweet-boost-schedule-management-row').not('.campaign-controls');
        var repeatText = tweetBoostDashboardWidgetVars.scheduleManagementWidgetRepeatTimes;

        for(var i = 0; i < tweets.length; i++){
            var post = jQuery(tweets[i]).attr('data-post');
            var stopProcessing = globalStoredTweetScheduleData[post].tweet_status_data.stop_processing;
            var stopProcessingReason = (globalStoredTweetScheduleData[post].tweet_status_data.stop_processing_reason !== undefined) ? globalStoredTweetScheduleData[post].tweet_status_data.stop_processing_reason : '';

            /* if the tweet has been paused */
            if(stopProcessing == true && stopProcessingReason == 'schedule_paused'){
                /* set the tweet status to "Paused" */
                jQuery(tweets[i]).find('.tweet-boost-schedule-status').empty().append('<div>' + repeatText[3] + '</div>');
                /* set the helpful icon to a yellow pause sign */
                jQuery(tweets[i]).find('.tweet-boost-schedule-status-icon').empty().append('<i style="font-size: 18px; color: #efde1a" class="fa fa-pause-circle-o"></i>');

            }else if(stopProcessing == true && stopProcessingReason == 'success'){
            /* if the tweet is set to not process, and the reason is because of a successful tweet */
                /* set the tweet status to "Tweeted!" */
                jQuery(tweets[i]).find('.tweet-boost-schedule-status').empty().append('<div>' + repeatText[1] + '</div>');
                /* set the helpful icon to a blue checkmark */
                jQuery(tweets[i]).find('.tweet-boost-schedule-status-icon').empty().append('<i style="font-size: 18px; color: #1aaaef" class="fa fa-check-circle-o"></i>');

            }else if(stopProcessing == true && stopProcessingReason != 'success'){
            /* if the tweet is set to not process, and the reason is anything other than a success, the tweet must have had an error */
                /* set the tweet status to "Has Error" */
                jQuery(tweets[i]).find('.tweet-boost-schedule-status').empty().append('<div>' + repeatText[2] + '</div>');
                /* set the helpful icon to an orange X mark */
                jQuery(tweets[i]).find('.tweet-boost-schedule-status-icon').empty().append('<i style="font-size: 18px; color: #ffb715" class="fa fa-times-circle-o"></i>');

            }else{
            /* if the schedule isn't stopped, it must be running */
                /* set the tweet status to "In Queue" */
                jQuery(tweets[i]).find('.tweet-boost-schedule-status').empty().append('<div>' + repeatText[0] + '</div>');
                /* set the helpful icon to a green calendar with check */
                jQuery(tweets[i]).find('.tweet-boost-schedule-status-icon').empty().append('<i style="font-size: 18px; color: #2fdc2d" class="fa fa-calendar-check-o"></i>');
            }
        }
    }

    /* make the call */
    setupTweetScheduleManagementWidget();

    /**
     * Attaches the listeners to the "Select" radio buttons in the Tweet Management widget
     **/
    function attachScheduleSelectorListeners(){
        jQuery('.tweet-boost-schedule-selector [name="tweet-boost-schedule-management-selector"]')
                        .off('touchend.mobile-tweet-boost-schedule-select')
                        .on('touchend.mobile-tweet-boost-schedule-select', touchDisplaySelectedScheduleDataHandler)
                        .off('click.desktop-tweet-boost-schedule-select')
                        .on('click.desktop-tweet-boost-schedule-select', displaySelectedScheduleData);

    }

    /* make the call */
    attachScheduleSelectorListeners();

    /**
     * Adds a delay to the displaying of the tweet in the Tweet Management widget to
     * prevent a touch event from registering multiple opens.
     **/
    function touchDisplaySelectedScheduleDataHandler(event){
        /* clear the touch waiter */
        clearTimeout(touchWait);
        touchWait = setTimeout(function(){ displaySelectedScheduleData(event); }, 500);
    }

    /**
     * Displays information about a tweet based on which radio button the user has selected in the Tweet Management widget
     **/
    function displaySelectedScheduleData(event = null){
        /* get the post id from the input when the user clicks it.
         * If the function is called by schedulePlayPauseControl, get the checked input directly */
        var postID = (event) ? event.target.value : parseInt(jQuery('.tweet-boost-schedule-selector [name="tweet-boost-schedule-management-selector"]:checked')[0].value);
        var tweetCompleted = '';
        var tweetMessage = '';

        /* display the tweet information and control boxes */
        jQuery('.tweet-boost-smw-content-container').css({'display': 'block'});

        /* set which tweet controls to display */
        displayPlayPauseButtons(postID);

        /* erase any existing tweet status message from the viewer when the function is called */
        jQuery('#tweet-boost-smw-detail-viewer').empty();

        /* create the elements of the tweet status message */
        tweetCompleted = '<div id="tweet-boost-smw-detail-stop-date">' + globalStoredTweetScheduleData[postID].processing_tense + globalStoredTweetScheduleData[postID].schedule_stopped + '</div>';
        tweetMessage    = '<div id="tweet-boost-smw-detail-message">' + globalStoredTweetScheduleData[postID].tweet_status_message + '</div>';

        /* append the tweet status message into the status viewer */
        jQuery('#tweet-boost-smw-detail-viewer').append('<div id="tweet-boost-smw-detail-container">' + tweetMessage + tweetCompleted + '</div>');

        /* move tweet controls under selected table row */
        var parent = jQuery('.tweet-boost-schedule-selector [name="tweet-boost-schedule-management-selector"]:checked').parent().parent(); /* gets <tr> of selected radio item */
        var controls = jQuery('#tweet-boost-smw-details-and-controls-container').detach();
        jQuery('.campaign-controls').remove();

        var new_tr = jQuery('<tr></tr>').addClass('campaign-controls');
        var new_td = jQuery('<td></td>').attr('colspan','4');

        new_td.append(controls);
        new_tr.append(new_td);

        new_tr.insertAfter(parent);

    }

    /**
     * Attaches the tweet play/pause listeners to the "Start Tweet" and
     * "Pause Tweet" buttons on page load
     **/
    function attachSchedulePlayPauseListeners(){
        jQuery('.tweet-boost-smw-play-pause-button')
                    .off('touchend.mobile-tweet-boost-schedule-play-pause')
                    .on('touchend.mobile-tweet-boost-schedule-play-pause', touchSchedulePlayPauseControlHandler)
                    .off('click.desktop-tweet-boost-schedule-play-pause')
                    .on('click.desktop-tweet-boost-schedule-play-pause', schedulePlayPauseControl);
    }

    /* make the call */
    attachSchedulePlayPauseListeners();

    /**
     * Adds a delay to the firing of the play/pause function so a touch doesn't register multiple play/pause events.
     **/
    function touchSchedulePlayPauseControlHandler(event){
        /* clear the touch waiter */
        clearTimeout(touchWait);
        touchWait = setTimeout(function(){ schedulePlayPauseControl(event); }, 500);
    }

    /**
     * Sets a post's tweet to play or pause,
     * depending on what the user selects in the Tweet Management widget
     **/
    function schedulePlayPauseControl(event){
        var postID = jQuery('.tweet-boost-schedule-selector [name="tweet-boost-schedule-management-selector"]:checked');
        var scheduleStatus = jQuery(event.target).attr('data-schedule-status');

        /* if a post isn't selected, exit */
        if(postID == undefined){
            return;
        }

        /* if the tweet is to be paused */
        if(scheduleStatus == 'pause'){
            /* load the pause popup text */
            var popupTitle  = tweetBoostDashboardWidgetVars.confirmPausePopup.title;
            var popupText   = tweetBoostDashboardWidgetVars.confirmPausePopup.text;
            var popupButton = tweetBoostDashboardWidgetVars.confirmPausePopup.buttonText;
        }else{
            /* if the tweet is to be played, load the play popup text */
            var popupTitle  = tweetBoostDashboardWidgetVars.confirmPlayPopup.title;
            var popupText   = tweetBoostDashboardWidgetVars.confirmPlayPopup.text;
            var popupButton = tweetBoostDashboardWidgetVars.confirmPlayPopup.buttonText;
        }

        /* get the post id of the selected tweet */
        postID = postID[0].value;

        /** create the sweetalert confirm popup **/
        swal({
            title: popupTitle,
            text: popupText,
            type: 'info',
            showCancelButton: true,
            confirmButtonColor: "#2ea2cc",
            confirmButtonText: popupButton,
            closeOnConfirm: false
        }, function () {
        /* if the user confirms, make an ajax call to either pause or play the tweet */
            jQuery.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    action: 'tweet_schedule_play_pause',
                    schedule_status: scheduleStatus,
                    post_id: postID,
                    nonce: globalStoredTweetScheduleData[postID].nonce,
                },
                success: function(response){
                    /* if the tweet has been successfully started/paused */
                    if(response.success){
                        /* if the tweet couldn't be played/paused */
                        if(response.tweet_schedule_status.error){
                            /* create an error message saying that the tweet couldn't be played/paused */
                            swal({type: 'error', title: response.tweet_schedule_status.title, text: response.tweet_schedule_status.error});
                            /* and exit */
                            return;
                        }

                        /* create a success message! */
                        swal({type: 'success', title: response.title, text: response.success});

                        /* if the tweet has been paused... */
                        if(response.tweet_schedule_status.stop_processing_reason == 'schedule_paused' && response.tweet_schedule_status.stop_processing == true){

                            /* set the time that the tweet was paused */
                            globalStoredTweetScheduleData[postID].schedule_stopped = response.tweet_schedule_status.schedule_paused_on;
                        }else{
                            /* set the time that the tweet stops */
                            globalStoredTweetScheduleData[postID].schedule_stopped = response.tweet_schedule_status.schedule_stops_on;

                        }

                        /* set the "Tweet Status" message */
                        globalStoredTweetScheduleData[postID].tweet_status_message = response.tweet_schedule_status.tweet_status_message;

                        /* if there is a message about the schedule processing state */
                        if(response.processing_tense){
                            /* set the processing state message */
                            globalStoredTweetScheduleData[postID].processing_tense = response.processing_tense;
                        }

                        /* update the stored tweet data so setupTweetScheduleManagementWidget knows what to do */
                        globalStoredTweetScheduleData[postID].tweet_status_data = response.tweet_schedule_status;

                        /* reset the tweet tweet table */
                        setupTweetScheduleManagementWidget();

                        /* update the tweet status text */
                        displaySelectedScheduleData();

                        /* update the calendar */
                        jQuery('#tweet-boost-tweet-calendar-widget').trigger('refreshCalendarDisplayedTweets');

                    }else if(response.info){
                    /* if the user didn't change the number of repeats */
                        /* create an info message telling the user that the repeat count hasn't changed */
                        swal({type: 'info', title: response.title, text: response.info});
                    }else if(response.error){
                    /* if there was an error in trying to delete the status data */
                        /* create an error message telling the user that there was an error and suggesting a solution */
                        swal({type: 'error', title: response.title, text: response.error});
                    }
                }
            });
            /* immediately after the call is made, show the waiting popup.
             * This appears _before_ the sweet alert inside of the "success" function that handles the response */
            swal({
                title: tweetBoostDashboardWidgetVars.waitPopup.title,
                text: tweetBoostDashboardWidgetVars.waitPopup.text,
                imageUrl: tweetBoostDashboardWidgetVars.waitPopup.waitingGif
            });
        });
    }

    /**
     * Listens for the reset tweet command from the Action Log widget
     **/
    function addListenerForTweetReset(){

        jQuery('.fa-repeat').click(function(){
            var post_id = jQuery(this).attr('data-post-id');

            /** create the sweetalert confirm popup **/
            swal({
                title: tweetBoostDashboardWidgetVars.confirmTweetRepeat.title,
                text: tweetBoostDashboardWidgetVars.confirmTweetRepeat.text,
                type: 'info',
                showCancelButton: true,
                confirmButtonColor: "#2ea2cc",
                confirmButtonText: tweetBoostDashboardWidgetVars.confirmTweetRepeat.buttonText,
                closeOnConfirm: false
            }, function () {
                jQuery.ajax({
                    type: 'POST',
                    url: ajaxurl,
                    data: {
                        action: 'reset_tweet',
                        post_id: post_id
                    },
                    success: function(response){

                        swal({
                            title: tweetBoostDashboardWidgetVars.confirmTweetRepeatSuccess.title,
                            text: tweetBoostDashboardWidgetVars.confirmTweetRepeatSuccess.text,
                            imageUrl: tweetBoostDashboardWidgetVars.confirmTweetRepeatSuccess.waitingGif
                        });
                    },
                    error: function(MLHttpRequest, textStatus, errorThrown){
                        console.log(MLHttpRequest);
                        console.log(textStatus);
                        console.log(errorThrown);
                    }
                });

                /* immediately after the ajax call is made, show the waiting popup */
                swal({
                    title: tweetBoostDashboardWidgetVars.waitPopup.title,
                    text: tweetBoostDashboardWidgetVars.waitPopup.text,
                    imageUrl: tweetBoostDashboardWidgetVars.waitPopup.waitingGif
                });
            });
        });
    }
    addListenerForTweetReset();

    /**
     * Picks which tweet controls to display based on the play/pause status of
     * the tweet selected in the Tweet Management widget.
     **/
    function displayPlayPauseButtons(postID){

        /* hide any currently visible inputs */
        jQuery('.tweet-boost-smw-schedule-controller').css({'display': 'none'});

        /* if the tweet is running */
        if(globalStoredTweetScheduleData[postID].tweet_status_data.stop_processing == undefined && globalStoredTweetScheduleData[postID].tweet_status_data.stop_processing_reason == undefined){
            /* show the "pause" button */
            jQuery('#tweet-boost-smw-pause-schedule-controller').css({'display': 'block'});

        }else if(globalStoredTweetScheduleData[postID].tweet_status_data.stop_processing == true && globalStoredTweetScheduleData[postID].tweet_status_data.stop_processing_reason == 'schedule_paused'){
        /* if the tweet is paused, show the "play" button */
            jQuery('#tweet-boost-smw-continue-schedule-controller').css({'display': 'block'});

        }else{
        /* if the tweet is not running or paused, don't show anything */

        }
    }

    /** helper function for testing if a string is JSON **/
    function isJson(str) {
        try {
            JSON.parse(str);
        } catch (e) {
            return false;
        }
        return true;
    }
});

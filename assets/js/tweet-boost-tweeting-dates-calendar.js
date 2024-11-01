/**
 * Deps:
 * Tippy tooltips (tippy.js)
 * CSS Element Queries (ElementQueries.js && ResizeSensor.js)
 * **/
jQuery(window).on('load', function(){
    /* create a global variable to hold the returned encoded array of tweets */
    var globalUnparsedResponse = '';
    /* create a global variable for the tweets to not process */
    var globalUnparsedDontProcessTweets = '';
    /* create a global variable for the tweets and the projected tweets */
    var globalUnparsedCurrentTweets = '';
    /* create an array of all the dates that are going to have tweets going out */
    var globalTweetingDatesList = [];
    /* create a global date click tracker */
    var globalCalendarDateClickTracker = 1;
    /* create an event tracker to keep the calendar events straight */
    var globalCalendarLastEventTracker = '';
    /* create a timeout for touch devices to reduce multi actions */
    var touchWait;
    /* create a flag for when the initial ajax call is completed. */
    var hasCompletedInitialAjaxCall = false;

    /* initialize the calendar */
    jQuery('#tweet-boost-upcoming-tweets-calendar').datepicker({
        dateFormat: 'y',
        inline: true,
        firstDay: 0,
        showOtherMonths: true,
        prevText: '',
        nextText: '',
        beforeShowDay: function(date){
            /* enter the js timestring for each date into the date's title.
             * We use this to index the upcoming tweets */
            return [true, 'tweet-boost-upcoming-tweets-calendar-date', date.toString()];
        }
    });

    /**
     * Retrieves the upcoming tweets on page load.
     * If there are tweets to be made, it processes the tweets and sets up the initial display of the calendar
     **/
    function retrieveUpcomingTweets(){
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                action: 'get_tweets_by_account',

            },
            success: function(response){
                /* mark that the ajax call has been completed */
                hasCompletedInitialAjaxCall = true;

                /* parse the response */
                response = JSON.parse(response);

                /* if there was an error, console.log it.
                 * Most of the time, this should be that no tweets were found */
                if(response.error != undefined && response.error){
                    console.log(response.error);
                }else{
                    /* set the global response variable */
                    globalUnparsedResponse = JSON.stringify(response.success);

                    /* also set the dont process global variable */
                    globalUnparsedDontProcessTweets = JSON.stringify(response.dont_process_tweets);
                }

                /* create the twitter account selector interface */
                createTwitterAccountSelectInterface();

                /* get the tweets formatted by date */
                var selectedTweets = getTweetsByAccount(null, true);

                /* create the the tweet tooltips */
                createTweetCalendarTooltips(selectedTweets);

                /* attach the calendar page change listener */
                addCalendarChangeListeners();

                /* add an event to use for refreshing the calendar from outside of this script */
                refreshCalendarDisplayedTweets();
            }
        });
    }

    /* make the call */
    retrieveUpcomingTweets();

    /**
     * Creates the interface for selecting which twitter accounts the calendar shows tweets for.
     **/
    function createTwitterAccountSelectInterface(){
        /* clear any existing account select buttons */
        jQuery('#tweet-boost-upcoming-tweets-calendar-select-account-button-container').empty();

        /** setup the tweet count range number overlay **/
        if(tweetBoostCalendarVars.tweetDateHeatRangeLimit > 20){
            var divisor = Math.floor(tweetBoostCalendarVars.tweetDateHeatRangeLimit / 10);
        }else{
            var divisor = 1;
        }
        /* create the displayed numbers in the range */
        var rangeNumbers = '';
        for(var h = 1; h <= tweetBoostCalendarVars.tweetDateHeatRangeLimit; h++){
            if(h % divisor === 0){
                rangeNumbers += '<span>' + h + '</span>';
            }
        }

        /* create the tweet color range label */
        var label = '<label for="calendar-tweet-count-range" id="calendar-tweet-count-range-label">' + tweetBoostCalendarVars.calendarHeatRangeText[0] + '</label>';

        /* enter the numbers in the color range */
        jQuery('#tweet-boost-upcoming-tweets-calendar-select-account-button-container').append(label + '<div id="calendar-tweet-count-range">' + rangeNumbers + '</div>');

        /* loop through each existing twitter accounts */
        for(var index in tweetBoostCalendarVars.availibleTwitterAccountData){
            var twitterAccount = tweetBoostCalendarVars.availibleTwitterAccountData[index]['username'];
            var twitterAvatar = '';
            var twitterAvatarThumb = '';

            /* if there is a full sized avatar image */
            if(tweetBoostCalendarVars.availibleTwitterAccountData[index]['twitter_avatar']){
                twitterAvatar = '<img src="' + tweetBoostCalendarVars.availibleTwitterAccountData[index]['twitter_avatar'] + '" alt="' + tweetBoostCalendarVars.calendarPleaseAddTwitterAvatar + '" title="' + twitterAccount + '" class="twitter-account-select-button-image twitter-avatar-full-size">';
            }

            if(tweetBoostCalendarVars.availibleTwitterAccountData[index]['twitter_avatar_thumb']){
                twitterAvatarThumb = '<img src="' + tweetBoostCalendarVars.availibleTwitterAccountData[index]['twitter_avatar_thumb'] + '" alt="' + tweetBoostCalendarVars.calendarPleaseAddTwitterAvatar + '" title="' + twitterAccount + '" class="twitter-account-select-button-image twitter-avatar-thumb">';
            }

            /* create the account select button and set it as selected */
            jQuery('#tweet-boost-upcoming-tweets-calendar-select-account-button-container').append('<div id="twitter-account-button-' + index + '" class="twitter-account-select-button selected account-button-number-' + index + '" data-account-id="' + index + '" data-twitter-account="' + twitterAccount + '">' + twitterAvatar + twitterAvatarThumb + '</div>');
        }

        /* add free version call to action */
        if (typeof index != 'undefined') {
            addTweetBoostCallToAction(index);
        }

        /* when the buttons are all setup, add the button click listeners */
        addTwitterAccountSelectButtonListeners();

        /* add the tippy tooltips to the account buttons */
        tippy('.twitter-account-select-button-image', {'position': 'top', 'arrow': true, 'followCursor': true});
    }

    function addTweetBoostCallToAction(index) {

        var addAccountCTA = '<i class="fa fa-plus tweet-boost-add-new-account" alt="Upgrade to TweetBoostPRO to support multiple accounts. Click here to learn more." title="TweetBoostPRO supports multiple accounts. Click here to learn more." >';

        /* create the account select button and set it as selected */
        jQuery('#tweet-boost-upcoming-tweets-calendar-select-account-button-container').append('<div id="twitter-account-button-' + index + '" class="twitter-account-add-new selected account-button-number-' + index + '" data-account-id="' + index + '" ><a href="https://wptweetboost.com/?utm_source=tweetboostfree-calendar-widget" target="_blank">' + addAccountCTA  + '</a></div>');

        /* add tippy */
        tippy('.tweet-boost-add-new-account', {'position': 'top', 'arrow': true, 'followCursor': true});

        /* setup mouseover listener */
        jQuery('.tweet-boost-add-new-account').on('mouseover' , function() {
            jQuery(this).removeClass('fa-plus').addClass('fa-lock ');
            jQuery('.twitter-account-add-new').addClass('twitter-account-add-new-hover');
        });
        /* setup mouseout listener */
        jQuery('.tweet-boost-add-new-account').on('mouseout' , function() {
            jQuery(this).removeClass('fa-lock').addClass('fa-plus');
            jQuery('.twitter-account-add-new').removeClass('twitter-account-add-new-hover');
        });
    }

    /**
     * Adds the button press listeners to the account buttons in account selection interface.
     **/
    function addTwitterAccountSelectButtonListeners(){
        jQuery('.twitter-account-select-button')
            .off('touchend.mobile-tweet-boost-calendar-select-twitter-account')
            .on('touchend.mobile-tweet-boost-calendar-select-twitter-account', setupSelectedAccountTweetDisplay)
            .off('click.desktop-tweet-boost-calendar-select-twitter-account')
            .on('click.desktop-tweet-boost-calendar-select-twitter-account', setupSelectedAccountTweetDisplay);
    }

    /**
     * Adds a handle for refreshing the calendar from outside the script
     **/
    function refreshCalendarDisplayedTweets(){
        jQuery('#tweet-boost-tweet-calendar-widget')
            .off('refreshCalendarDisplayedTweets')
            .on('refreshCalendarDisplayedTweets', function(){ 
                /* if we're in the dashboard, re-grab the tweets */
                if(jQuery('#tweet-boost-admin-schedule-management-widget').length){ 
                    retrieveUpcomingTweets(); 
                }else{ 
                    /* otheriwse, just refresh the calendar normally */
                    setupSelectedAccountTweetDisplay(); 
                } 
            });
    }

    /**
     * Manages the display of tweets in the calendar by the selected twitter accounts
     **/
    function setupSelectedAccountTweetDisplay(event = null, loadCalculatedTweets = false){

        /* if the function was called because one of the twitter account buttons was clicked */
        if(event && event.type !== 'refreshCalendarDisplayedTweets' && event.target){

            /* if instead of the button, we have the image inside the button. */
            if(event.target.tagName === 'IMG'){
                /* get the button as the parent */
                var button = jQuery(event.target.parentNode);
            }else{
                /* get the button */
                var button = jQuery(event.target);
            }

            /* if the button is already selected */
            if(button.hasClass('selected')){
                /* unselect it */
                button.removeClass('selected').addClass('unselected');
            }else{
                /* if the button isn't selected, select it */
                button.removeClass('unselected').addClass('selected');
            }
        }

        /* get all the selected buttons */
        var selectedButtons = document.getElementsByClassName('twitter-account-select-button selected');
        /* create an array for the selected accounts */
        var selectedAccounts = [];
        /* loop through all the selected buttons and add their account values to the selected account array */
        for(var i = 0; i < selectedButtons.length; i++){
            selectedAccounts.push(selectedButtons[i].getAttribute('data-twitter-account'));
        }

        /* set the selected tweets variable to contain tweets from accounts the user has selected */
        var selectedTweets = getTweetsByAccount(selectedAccounts, false, loadCalculatedTweets);

        /* create the new tooltips */
        createTweetCalendarTooltips(selectedTweets);

        /* reset the click tracker */
        resetCalendarDateClickTracker();
    }

    /**
     * Creates the upcoming tweets popup tooltips.
     * Also positions them, either above the date or beneath.
     * And it calculates the date's tweet heat value
     **/
    function createTweetCalendarTooltips(selectedTweets = null){
        /* move the date data out of the calendar date's titles and into data attributes */
        formatCalendarDateAttr();
        /* make sure all dates with tweets have the 'has-tweets' class */
        updateTweetingDates();
        /* erase the upcoming tweet tooltips from prior calendar views */
        jQuery('.tweet-boost-upcoming-tweet-wrapper').remove();
        /* get the calendar dates that have tweets going out */
        var tweetingDates = jQuery('.tweet-boost-tweet-on-date').not('.ui-datepicker-unselectable');
        /* get the calendar height to calculate the tweet tooltip position */
        var calendarHeight = (jQuery('.ui-datepicker-calendar').height() - jQuery('.ui-datepicker-calendar thead').height());
        /* get the calendar header height */
        var headerHeight = jQuery('.tweet-boost-upcoming-tweets-calendar-wrapper .ui-datepicker-header').outerHeight();
        /* get the height of the button */
        var buttonHeight = jQuery('td.tweet-boost-upcoming-tweets-calendar-date').height();
        /* get the padding on the top of the calendar row */
        var rowHeight = (jQuery('.tweet-boost-upcoming-tweets-calendar-wrapper table.ui-datepicker-calendar tbody tr').outerHeight() - buttonHeight) / 2;

        /* if there aren't any dates with tweets or any tweets supplied, exit */
        if(tweetingDates == undefined || tweetingDates.length == 0 || selectedTweets === null){
            return;
        }

        /* loop through each date tagged as having tweets */
        for(var i = 0; i < tweetingDates.length; i++){

            /* if the title still holds the tweet date index */
            if(tweetingDates[i].title){
                /* get the date index from the title and */
                var dateIndex = tweetingDates[i].title;
                /* move the date index to a data attribute */
                tweetingDates[i].setAttribute('title', '');
                tweetingDates[i].setAttribute('data-tweet-date', dateIndex);
            }else if(tweetingDates[i].getAttribute('data-tweet-date')){
                /* if the title isn't set but the tweet date data attribute is, get the date from that */
                var dateIndex = tweetingDates[i].getAttribute('data-tweet-date');
            }

            /* if the current date isn't in the selected tweets object, remove any background color and skip to the next one */
            if(selectedTweets[dateIndex] === undefined){
                tweetingDates[i].getElementsByTagName('a')[0].style.removeProperty('background-color');
                continue;
            }

            /* if the tweet date is less than halfway down the calendar */
            if((calendarHeight * 0.5) > jQuery(tweetingDates[i])[0].offsetTop){
                /* position the tooltip below the date */
                var position = 'tweet-boost-tweet-tooltip-bottom-aligned';
            }else{
                /* position the tooltip above the date */
                var position = 'tweet-boost-tweet-tooltip-top-aligned';
            }

            /* append the tweet wrapper to the date. Also set the positioning */
            jQuery(tweetingDates[i]).append('<div id="upcoming-tweet-wrapper-' + i + '" class="tweet-boost-upcoming-tweet-wrapper ' + position + '"></div>');

            /* get the number of tweets */
            var tweetCount = selectedTweets[dateIndex].length;

            /* setup a container for the number of tweets an individual account has */
            var tweetsPerAccount = {};

            /* loop through the returned tweets */
            for(var j = 0; j < tweetCount; j++){
                /* simplify the specific tweet we're looking at for readability */
                var tweet = selectedTweets[dateIndex][j];

                /* get the account button id for the current tweet */
                var buttonAccountId = document.querySelector('[data-twitter-account="' + tweet.twitter_account + '"]').getAttribute('data-account-id');

                /* get the current account data */
                var currentAccountData = tweetBoostCalendarVars.availibleTwitterAccountData[buttonAccountId];

                /* append the displayed tweet to the wrapper */
                jQuery('#upcoming-tweet-wrapper-' + i)
                    .append('<div class="tweet-boost-upcoming-tweet-container account-button-number-' + buttonAccountId + '" style="height:' + buttonHeight + 'px;">' +
                        '<div class="tweet-boost-upcoming-tweet-account-avatar tweet-calendar-tooltip-data-element">' + (currentAccountData['twitter_avatar'] ? '<img src="' + currentAccountData['twitter_avatar'] + '" style="height:100%; width:100%;">' : '') + '</div>' +
                        '<div class="tweet-boost-upcoming-tweet-content tweet-calendar-tooltip-data-element">' +
                        '<div class="tweet-boost-upcoming-tweet-image">' + (tweet.image ? tweet.image : '') + '</div>' +
                        '<div class="tweet-boost-upcoming-tweet-excerpt">' + (tweet.excerpt ? tweet.excerpt : '') + '</div>' +
                        '</div>' +
                        '<div class="tweet-boost-upcoming-tweet-date tweet-calendar-tooltip-data-element">' + (tweet.tweet_time_of_day ? tweet.tweet_time_of_day : '') + '</div>' +
                        '<div class="tweet-boost-upcoming-tweet-post tweet-calendar-tooltip-data-element">' +
                        '<a href="' + tweet.post_link + '" >' + (tweet.post_title ? tweet.post_title : '') + '</a>' +
                        '</div>' +
                        '</div>');

                if(j < 4 && position == 'tweet-boost-tweet-tooltip-top-aligned'){
                    /* if there's been fewer than 5 loops and the tooltip is set to be top aligned */
                    /* set the position of the upcoming tweet box to be above the calendar item, and set the size of the container box to display upto 4 tweets */
                    jQuery('#upcoming-tweet-wrapper-' + i).css({'top': ( (jQuery(tweetingDates[i])[0].offsetTop + headerHeight + rowHeight) - (buttonHeight + (j * buttonHeight)) ) + 'px', 'height': (buttonHeight + (j * buttonHeight)) + 'px', 'overflow': 'auto'});
                }else if(j < 4 && position == 'tweet-boost-tweet-tooltip-bottom-aligned'){
                    /* if there's fewer than 5 loops and tooltip is set to bottom aligned */
                    /* set the height of the upcoming tweets container box to display upto 4 tweets */
                    jQuery('#upcoming-tweet-wrapper-' + i).css({'height': (buttonHeight + (j * buttonHeight)) + 'px', 'overflow': 'auto'});
                }
            }

            /* set the total number of tweets on the date */
            jQuery('#upcoming-tweet-wrapper-' + i).attr('data-tweet-count', j);

            /** calculate the calendar date's heat rating **/
            /* get the current date */
            var span = jQuery('#upcoming-tweet-wrapper-' + i).siblings('a.ui-state-default');

            /* set the heat val for the number of tweets that go out on the current date */
            var heatVal = j;

            /* if the number of tweets is higher than the limit */
            if (heatVal > tweetBoostCalendarVars.tweetDateHeatRangeLimit){
                /* set the heat val to the limit */
                heatVal = tweetBoostCalendarVars.tweetDateHeatRangeLimit;
            }
            else if (heatVal < 0) {
                /* if the heat val is less than 0, set it to 0 */
                heatVal = 0;
            }
            /* get the color range hue */
            var hue = Math.floor((tweetBoostCalendarVars.tweetDateHeatRangeLimit - heatVal) * 120 / tweetBoostCalendarVars.tweetDateHeatRangeLimit);
            /* and color the date with the correct heat color */
            span.css({
                backgroundColor: 'hsl(' + hue + ', 80%, 50%)'
            });
        }

        /* add the handler for when a user clicks on a post link in a tooltip */
        jQuery('.tweet-boost-upcoming-tweet-container .tweet-boost-upcoming-tweet-post a').off('click').on('click', handleCalendarPostLinkClicks);

    }

    /**
     * Makes the call to populate the calendar and update the heat mapping when the calendar month is changed.
     * Also resets the tooltip click tracker, reapplies the refresh calendar event and reattaches itsself when the month is changed.
     **/
    function addCalendarChangeListeners(event){
        jQuery('#tweet-boost-tweet-calendar-widget .ui-datepicker-prev, \
                #tweet-boost-tweet-calendar-widget .ui-datepicker-next')
            .off('touchend.mobile-tweet-boost-calendar-month-change')
            .on('touchend.mobile-tweet-boost-calendar-month-change', setupSelectedAccountTweetDisplay(null, true)) // pass true to update the calendar with existing data since only the displayed month changed
            .on('touchend.mobile-tweet-boost-calendar-month-change', resetCalendarDateClickTracker)
            .on('touchend.mobile-tweet-boost-calendar-month-change', addCalendarChangeListeners)
            .off('click.desktop-tweet-boost-calendar-month-change')
            .on('click.desktop-tweet-boost-calendar-month-change', setupSelectedAccountTweetDisplay(null, true)) // pass true to update the calendar with existing data since only the displayed month changed
            .on('click.desktop-tweet-boost-calendar-month-change', resetCalendarDateClickTracker)
            .on('click.desktop-tweet-boost-calendar-month-change', addCalendarChangeListeners);
    }

    /**
     * Attaches listeners that display the tweet tooltips when a date is interacted with.
     **/
    function addCalendarDateInteractListeners(){
        /* remove the default jqui datepicker click behavior */
        jQuery('td.tweet-boost-upcoming-tweets-calendar-date').off('click').on('click', function(event){event.preventDefault()});

        /* add calendar interaction listeners to the calendar dates */
        jQuery('#tweet-boost-tweet-calendar-widget .tweet-boost-tweet-on-date')
            .off('touchend.mobile-tweet-boost-calendar-date-interact')
            .on('touchend.mobile-tweet-boost-calendar-date-interact', displayCalendarTooltip)
            .off('click.desktop-tweet-boost-calendar-date-interact')
            .on('click.desktop-tweet-boost-calendar-date-interact', displayCalendarTooltip)
            .off('mouseenter.desktop-tweet-boost-calendar-date-interact')
            .on('mouseenter.desktop-tweet-boost-calendar-date-interact', displayCalendarTooltipHoverWait)
            .off('mouseleave.desktop-tweet-boost-calendar-date-interact')
            .on('mouseleave.desktop-tweet-boost-calendar-date-interact', displayCalendarTooltipHoverWait);
    }

    /**
     * Delays the display of the calendar tooltips so the user can navigate the calendar more easily.
     * Without it the tooltips pop up instantly, which can cover up other dates
     **/
    var displayTooltipWait;
    function displayCalendarTooltipHoverWait(event){
        /* exit if the event isn't set */
        if(!isVal(event) || !isVal(event.type)){
            return;
        }

        /* clear the tooltip display waiter */
        clearTimeout(displayTooltipWait);

        /* if the mouse pointer left the date */
        if(event.type === 'mouseleave'){
            /* call the tooltip function immediately */
            displayCalendarTooltip(event)
        }else if(event.type === 'mouseenter' || event.type === 'mouseover'){
            /* if the mouse pointer just entered the date, set the waiter to call the tooltip function.
             * That way we know the user wants to see the tweets */
            displayTooltipWait = setTimeout(function(){displayCalendarTooltip(event)}, 750);
        }
    }

    /**
     * Displays the tweet popup tooltip when a user mouses over it, clicks it, or touches it
     **/
    function displayCalendarTooltip(event){

        /* if an unknown event occured, exit */
        if(event == undefined){
            return;
        }

        /**
         * If this is the first time around, set what kind of event activates the calendar tooltip.
         **/
        if(globalCalendarLastEventTracker == ''){
            globalCalendarLastEventTracker = event.type;
        }else if(globalCalendarLastEventTracker == 'touchend' && event.type != 'touchend'){
            /** If the tooltip was activated by touchend, only listen for that **/
            return;
        }

        /* if the user moved the mouse onto a date, or clicked it, or touched it on a mobile device */
        if(event.type == 'mouseenter' || event.type == 'mouseover' || event.type == 'click' || event.type == 'touchend'){
            /* if the user clicked the date or touched it */
            if(event.type == 'click' || event.type == 'touchend'){
                /* check to see if the date has been clicked or touched, for a toggle on/off effect */
                if(globalCalendarDateClickTracker % 2){
                    /* if it was just clicked "on", show the tooltip */
                    jQuery(event.target).parent('td.tweet-boost-upcoming-tweets-calendar-date').find('.tweet-boost-upcoming-tweet-wrapper').css({'display': 'inline-block'});
                }else{
                    /* if it was clicked "off", hide the tooltip */
                    jQuery(event.target).parent('td.tweet-boost-upcoming-tweets-calendar-date').find('.tweet-boost-upcoming-tweet-wrapper').css({'display': 'none'});
                }
                /* increment the click tracker to keep track off whether the tooltip is "on" or "off" */
                globalCalendarDateClickTracker++;
            }else{
                /* if user moved the mouse over the date and the click tracker hasn't been clicked "on", show the tooltip */
                if(globalCalendarDateClickTracker % 2){
                    /* if the user moused over the date table cell, and the js picked up the mouse moving over the table cell */
                    if(jQuery(event.target).hasClass('tweet-boost-tweet-on-date')){
                        jQuery(event.target).find('.tweet-boost-upcoming-tweet-wrapper').css({'display': 'inline-block'});
                    }else{
                        /* else, if the user moved the mouse fast enough that the js didn't catch the event until the mouse was over the link.
                         * find the parent cell, then show the tooltip */
                        jQuery(event.target).parent('.tweet-boost-tweet-on-date').find('.tweet-boost-upcoming-tweet-wrapper').css({'display': 'inline-block'});
                    }
                }else{
                    /* if the user moved the mouse over the button with the click tracker being set to "on", don't do anything */
                }
            }
        }else if(event.type == 'mouseleave'){
            /* if the user moved the mouse off of the date, and the date hasn't been clicked */
            if(globalCalendarDateClickTracker % 2){
                /* hide the tooltip */
                jQuery('.tweet-boost-upcoming-tweet-wrapper').css({'display': 'none'});
            }
        }else{
            /* if some event other than clicks, mouseovers or touches occured, hide the tooltip */
            jQuery('.tweet-boost-upcoming-tweet-wrapper').css({'display': 'none'});
        }
    }

    /**
     * Listens for the user changing the data in the schedule's tweets
     **/
    function listenForTweetDataChanges(){
        /* listen for tweet time changes */
        jQuery( '#acf-group_5989061655cc1 .acf-date-time-picker input.input.hasDatepicker')
            .off('change.all-tweet-boost-calendar-tweet-time-listener') // todo make sure this works on mobile. and that it doesn't do anything weird
            .on('change.all-tweet-boost-calendar-tweet-time-listener', updateCalendarCoolDown);

        /* listen for tweet photo changes */
        jQuery( '#acf-group_5989061655cc1 .acf-field-598907a39db24 a[data-name="add"],\
                 #acf-group_5989061655cc1 .acf-field-598907a39db24 a[data-name="edit"],\
                 #acf-group_5989061655cc1 .acf-field-598907a39db24 a[data-name="remove"]')
            .off('click.desktop-tweet-boost-calendar-tweet-photo-listener') // todo make sure this works on mobile. and that it doesn't do anything weird
            .on('click.desktop-tweet-boost-calendar-tweet-photo-listener', function(){ updateCalendarWatcher(event, 'image'); })
            .off('touchend.mobile-tweet-boost-calendar-tweet-photo-listener') // todo make sure this works on mobile. and that it doesn't do anything weird
            .on('touchend.mobile-tweet-boost-calendar-tweet-photo-listener', function(){ updateCalendarWatcher(event, 'image'); });

        /* listen for the content changing */
        jQuery('#acf-group_5989061655cc1 .acf-field-598906cf9db23 textarea,\
                #acf-group_5989061655cc1 .acf-field-598906cf9db23 .add-post-link-button-container .add-post-link-button')
            .off('blur.all-tweet-boost-calendar-tweet-content-listener') // todo make sure this works on mobile. and that it doesn't do anything weird
            .on('blur.all-tweet-boost-calendar-tweet-content-listener', updateCalendarCoolDown);

        /* listen for twitter account changes */
        jQuery('#acf-group_5989061655cc1 .acf-field-598ce868fb0c4 select')
            .off('change.all-tweet-boost-calendar-tweet-account-listener') // todo make sure this works on mobile. and that it doesn't do anything weird
            .on('change.all-tweet-boost-calendar-tweet-account-listener', updateCalendarCoolDown);

        /* listen for twitter status resets */
        jQuery('#acf-group_5989061655cc1 .acf-field-59d2b549ed77d .acf-input .button-primary')
            .off('resetButtonPressed.all-tweet-boost-calendar-tweet-status-reset-listener') // todo make sure this works on mobile. and that it doesn't do anything weird
            .on('resetButtonPressed.all-tweet-boost-calendar-tweet-status-reset-listener', updateCalendarCoolDown);
    }

    /* make the call */
    listenForTweetDataChanges();

    /**
     * Provides a timeout so only one change event is processed at a time
     **/
    var updateCalendarWait;
    function updateCalendarCoolDown(event){
        /* clear the time change waiter */
        clearTimeout(updateCalendarWait);
        updateCalendarWait = setTimeout(function(){
            /* if the datepicker was updated */
            if(event.target.className === 'input hasDatepicker'){
                /* and if the datepicker calendar is closed */
                if(!jQuery('.acf-ui-datepicker #ui-datepicker-div .ui-datepicker-calendar').is(':visible')){
                    /* update the calendar */
                    updateTweetCalendar(event);
                    /* then clear the tweet status message, disable the reset button and clear the status data */
                    var row = jQuery(event.target).parents('tr.acf-row');
                    row.find('td.acf-field-599743247e1b1').removeClass('tweet-has-error').find('.acf-input').empty();
                    row.find('.acf-field-59d2b549ed77d input.button-primary').prop('disabled', true).addClass('tweet-boost-reset-status-button-disabled');
                    row.find('.acf-field-59af60df2fbb0 .tweet-boost-hidden-data-field').attr('data-tweet-status', '');
                }
            }else{
                /* in other cases, update the calendar */
                updateTweetCalendar(event);
                /* and clear the tweet status message and disable the reset button */
                var row = jQuery(event.target).parents('tr.acf-row');
                row.find('td.acf-field-599743247e1b1').removeClass('tweet-has-error').find('.acf-input').empty();
                row.find('.acf-field-59d2b549ed77d input.button-primary').prop('disabled', true).addClass('tweet-boost-reset-status-button-disabled');
                row.find('.acf-field-59af60df2fbb0 .tweet-boost-hidden-data-field').attr('data-tweet-status', '');
            }
        }, 300);
    }

    /**
     * Watches elements for content changes.
     * When they do change, makes a call to updateTweetCalendar to update the calendar with the new content
     **/
    var updateCalendarWatch;
    var calendarWatchData = {'counter': 0, 'element': '', 'watching': ''};
    function updateCalendarWatcher(event, type = null){
        /* clear the existing watcher if one's running */
        clearInterval(updateCalendarWatch);
        /* reset the loop count */
        calendarWatchData.counter = 0;

        /* if we're going to watch for an image change */
        if(type === 'image'){
            /* find the image element we're going to watch */
            calendarWatchData.element = jQuery(event.target).parents('.acf-field-598907a39db24').find('img[data-name="image"]')[0];
            /* get the source of the image we're watching so we can watch to see if it changes */
            calendarWatchData.watching = calendarWatchData.element.src;
        }

        /* setup the watcher */
        updateCalendarWatch = setInterval(function(){
            /* if the watcher has made over a 1000 passes, quit watching */
            if(calendarWatchData.counter > 1000){
                clearInterval(updateCalendarWatch);
            }

            /* if the watched element has changed */
            if(calendarWatchData.element.src !== calendarWatchData.watching){
                /* update the calendar */
                updateTweetCalendar(event);
                /* and quit watching the element */
                clearInterval(updateCalendarWatch);
                /* then clear the tweet status message and disable the reset button */
                var row = jQuery(event.target).parents('tr.acf-row');
                row.find('td.acf-field-599743247e1b1').removeClass('tweet-has-error').find('.acf-input').empty();
                row.find('.acf-field-59d2b549ed77d input.button-primary').prop('disabled', true).addClass('tweet-boost-reset-status-button-disabled');
                row.find('.acf-field-59af60df2fbb0 .tweet-boost-hidden-data-field').attr('data-tweet-status', '');
            }

            /* increase the watch counter if the element hasn't changed */
            calendarWatchData.counter++;

        }, 500);
    }

    /**
     * Finds out what kind of update the calendar needs when the tweet schedule changes.
     * It makes calls to add new tweets to, update, or remove tweets from the calendar
     **/
    function updateTweetCalendar(event){
        /* if the ajax call to setup the calendar hasn't finished yet, exit */
        if(hasCompletedInitialAjaxCall === false){
            return;
        }

        /* find out if the tweet is in the calendar */
        var tweet = findTweetInCalendar();

        /* if the tweet doesn't already exist in the calendar, add it */
        if(tweet === false){
            addTweetToCalendar();
        }else if(tweet === true){
            /* if the tweet is in the calendar, check to make sure it has a time set */
            var inputtedDate = document.getElementById('acf-field_598906629db22').value;

            /* if it does have a time */
            if(isVal(inputtedDate)){
                /* update it with the new data */
                updateExistingTweet();
            }else{
                /* if it doesn't have a time, remove it */
                removeTweetFromCalendar();
            }
        }
    }

    /**
     * Searches the response to see if the tweet is already logged.
     * Returns false if the tweet isn't in the calendar
     **/
    function findTweetInCalendar(){
        var parsedResponse = (globalUnparsedResponse !== '' && globalUnparsedResponse !== '[]') ? JSON.parse(globalUnparsedResponse) : {};
        var postId = document.getElementById('post_ID').value;
        var tweetInCalendar = false;

        /* loop through the tweets in the response */
        for(var account in parsedResponse){
            for(var j = 0; j < parsedResponse[account].length; j++){
                /* if we find the tweet in the response */
                if(parsedResponse[account][j].post_id == postId){
                    /* return true */
                    return true;
                }
            }
        }

        /** if we haven't found the tweet in the active tweets, check to see if it's in the dont process tweets **/
        if(tweetInCalendar === false){
            /* get the list of dont process tweets */
            parsedResponse = (globalUnparsedDontProcessTweets !== '') ? JSON.parse(globalUnparsedDontProcessTweets) : {};
            /* loop through the tweets in the response */
            for(var account in parsedResponse){
                for(var j = 0; j < parsedResponse[account].length; j++){
                    /* if we find the tweet in the response */
                    if(parsedResponse[account][j].post_id == postId){
                        /* return true */
                        return true;
                    }
                }
            }
        }

        /* return the result of our search for the tweet */
        return tweetInCalendar;
    }

    /**
     * Removes tweets from the calendar when a user deletes it from the schedule
     **/
    function removeTweetFromCalendar(){
        var parsedResponse = (globalUnparsedResponse !== '' && globalUnparsedResponse !== '[]') ? JSON.parse(globalUnparsedResponse) : {};
        var postId = document.getElementById('post_ID').value;
        var foundTweet = false;

        /* loop through the tweets in the response */
        for(var i in parsedResponse){
            for(var j = 0; j < parsedResponse[i].length; j++){
                /* if we find the tweet in the response */
                if(parsedResponse[i][j].post_id == postId){
                    /* remove the tweet from the response */
                    parsedResponse[i].splice(j, 1);

                    /* say that we've found the tweet */
                    foundTweet = true;

                    /* try stringifying the parsed response */
                    try{
                        var updatedResponse = JSON.stringify(parsedResponse);
                    }catch(e){
                        /* if there was an error, exit so the global response doesn't get overwritten */
                        return false;
                    }

                    /* update the globalResponse */
                    globalUnparsedResponse = updatedResponse;

                    /* exit the loop */
                    break;
                }
            }
        }

        /** if we haven't found the tweet in the list of tweets, check the don't process tweets **/
        if(foundTweet === false){
            /* get the list of dont process tweets */
            var parsedResponse = (globalUnparsedDontProcessTweets !== '') ? JSON.parse(globalUnparsedDontProcessTweets) : {};
            /* loop through the tweets in the response */
            for(var i in parsedResponse){
                for(var j = 0; j < parsedResponse[i].length; j++){
                    /* if we find the tweet in the response */
                    if(parsedResponse[i][j].post_id == postId){
                        /* remove the tweet from the response */
                        parsedResponse[i].splice(j, 1);
                        /* say that we've found the tweet */
                        foundTweet = true;
                        /* exit the loop */
                        break;
                    }
                }
            }

            /* if we've found the tweet */
            if(foundTweet){
                /* try stringifying the parsed response */
                try{
                    var updatedResponse = JSON.stringify(parsedResponse);
                }catch(e){
                    /* if there was an error, exit so the global response doesn't get overwritten */
                    return false;
                }

                /* update the globalResponse */
                globalUnparsedDontProcessTweets = updatedResponse;
            }
        }

        /* activate the tweet display system to refresh the calendar */
        setupSelectedAccountTweetDisplay();
    }

    /**
     * Adds a new tweet to the calendar listing when the user creates a new tweet
     **/
    function addTweetToCalendar(){
        /* create the new tweet from the data in the page */
        var newTweet = createTweetDataForCalendar();

        /* if the tweet wasn't successfully assembled, exit */
        if(!isVal(newTweet)){
            return;
        }

        /* get the original response list of tweets */
        var originalResponse = (globalUnparsedResponse !== '' && globalUnparsedResponse !== '[]') ? JSON.parse(globalUnparsedResponse) : {};

        /* if the account isn't in the list, add it */
        if(!isVal(originalResponse[newTweet[0]])){
            originalResponse[newTweet[0]] = [];
            console.log(newTweet[0]);
        }

        /* add the new tweet to the account that's going to make it */
        originalResponse[newTweet[0]].push(newTweet[1]);

        /* try stringifying the updated list */
        try {
            var updatedResponse = JSON.stringify(originalResponse);
        } catch (e) {
            console.log(e);
            /* if there was an error, exit so the global response doesn't get overwritten */
            return false;
        }

        /* update the globalResponse */
        globalUnparsedResponse = updatedResponse;

        /* get midnight of the day that the tweet is to be made */
        var tweetDay = new Date(new Date(newTweet[1].tweet_date).setHours(0,0,0,0)).toString();
        /* add the tweet's date to the list of tweeting dates */
        globalTweetingDatesList = globalTweetingDatesList.push(tweetDay);

        /* activate the tweet display system to refresh the calendar */
        setupSelectedAccountTweetDisplay();
    }

    /**
     * Updates the calendar listing of a tweet from the current (currently being edited) schedule when its data is updated.
     **/
    function updateExistingTweet(){
        var parsedResponse = (globalUnparsedResponse !== '' && globalUnparsedResponse !== '[]') ? JSON.parse(globalUnparsedResponse) : {};
        var postId = document.getElementById('post_ID').value;
        var tweetUpdated = false;
        var newTweet = createTweetDataForCalendar();

        /* if the new tweet couldn't be created, exit */
        if(!isVal(newTweet)){
            return;
        }

        /* loop through the tweets in the response */
        for(var account in parsedResponse){
            for(var j = 0; j < parsedResponse[account].length; j++){
                /* put the tweet in a variable so it's easier to read */
                var tweet = parsedResponse[account][j];

                /* if we find the tweet in the response */
                if(tweet.post_id == postId){
                    /* if the twitter account making the tweet is different from the one in storage */
                    if(account !== tweet.twitter_account){
                        /* remove the tweet from the account */
                        parsedResponse[account].splice(j, 1);

                        /* check to see if the account it goes to is in the list */
                        if(isVal(parsedResponse[newTweet[0]])){
                            /* if it is, add the tweet to the acount */
                            parsedResponse[newTweet[0]].push(newTweet[1]);
                        }else{
                            /* if the account isn't in the list, add the acount */
                            parsedResponse[newTweet[0]] = [];
                            /* then add the tweet */
                            parsedResponse[newTweet[0]].push(newTweet[1]);
                        }
                    }else{
                        /* if the twitter account hasn't changed, update the listing with the new tweet data */
                        parsedResponse[account][j] = newTweet[1];
                    }

                    /** now that we've found the tweet, and presumably updated it **/
                    /* try stringifying the updated list */
                    try {
                        var updatedResponse = JSON.stringify(parsedResponse);
                    } catch (e) {
                        /* if there was an error, exit so the global response doesn't get overwritten */
                        return false;
                    }

                    /* update the globalResponse */
                    globalUnparsedResponse = updatedResponse;

                    /* say that we've updated the list */
                    tweetUpdate = true;

                    /* break out of the loop since we've updated the tweet list */
                    break;
                }
            }
        }

        /** if we haven't been able to update the tweet yet, check to see if the tweet is in the dont process tweets **/
        if(tweetUpdated === false){
            /* get the list of dont process tweets */
            parsedResponse = (globalUnparsedDontProcessTweets !== '') ? JSON.parse(globalUnparsedDontProcessTweets) : {};
            /* loop through the tweets in the response */
            for(var account in parsedResponse){
                for(var j = 0; j < parsedResponse[account].length; j++){
                    /* put the tweet in a variable so it's easier to read */
                    var tweet = parsedResponse[account][j];

                    /* if we find the tweet in the response */
                    if(tweet.post_id == postId){
                        /* if the twitter account making the tweet is different from the one in storage */
                        if(account !== tweet.twitter_account){
                            /* remove the tweet from the account */
                            parsedResponse[account].splice(j, 1);

                            /* check to see if the account it goes to is in the list */
                            if(isVal(parsedResponse[newTweet[0]])){
                                /* if it is, add the tweet to the acount */
                                parsedResponse[newTweet[0]].push(newTweet[1]);
                            }else{
                                /* if the account isn't in the list, add the acount */
                                parsedResponse[newTweet[0]] = [];
                                /* then add the tweet */
                                parsedResponse[newTweet[0]].push(newTweet[1]);
                            }
                        }else{
                            /* if the twitter account hasn't changed, update the listing with the new tweet data */
                            parsedResponse[account][j] = newTweet[1];
                        }

                        /** now that we've found the tweet, and presumably updated it **/
                        /* try stringifying the updated list */
                        try {
                            var updatedResponse = JSON.stringify(parsedResponse);
                        } catch (e) {
                            /* if there was an error, exit so the global response doesn't get overwritten */
                            return false;
                        }

                        /* update the globalResponse */
                        globalUnparsedDontProcessTweets = updatedResponse;

                        /* break out of the loop since we've updated the tweet list */
                        break;
                    }
                }
            }
        }

        /* activate the tweet display system to refresh the calendar */
        setupSelectedAccountTweetDisplay();
    }

    /**
     * Creates the data needed to add a new tweet to the calendar
     **/
    function createTweetDataForCalendar(){

        /* get the date from the tweet date input */
        var inputtedDate = document.getElementById('acf-field_598906629db22').value;

        /* if there isn't a date, exit */
        if(!isVal(inputtedDate)){
            return;
        }

        /** create the new tweet object **/
        var newTweet = {};
        /* put together the basic tweet data */
        var twitterAccount = tweetBoostCalendarVars.availibleTwitterAccountData[0].username;
        var inputtedDate = document.getElementById('acf-field_598906629db22').value;
        var tweetContent = document.getElementById('acf-field_598906cf9db23').value;
        var tweetImageSrc = jQuery('.acf-field-598907a39db24 .show-if-value img')[0].src;
        var postId = document.getElementById('post_ID').value;
        var postLink = 'tweet-boost';
        var postTitle = (isVal(document.getElementById('original_post_title'))) ? document.getElementById('original_post_title').value : (isVal(document.getElementById('title'))) ? document.getElementById('title').value : postId ; // first tries to get the original title for consistancy. Then tries to get the current title from the title box. If neither works, it enters the post id

        /** put together the time data needed to setup the tweet **/
        var dateObj = new Date(inputtedDate);
        /* create the tweet date for indexing the tweet */
        var tweetDate = tweetBoostCalendarVars.dateTranslationObject.months[dateObj.getMonth()] + ' ' + dateObj.getDate() + ', ' + dateObj.getFullYear() + ' ' + changeHour(dateObj.getHours()) + ":" + addZero(dateObj.getMinutes()) + ' ';
        tweetDate += (dateObj.getHours() >= 12) ? tweetBoostCalendarVars.dateTranslationObject.timePeriod[1] : tweetBoostCalendarVars.dateTranslationObject.timePeriod[0];

        /* create the time of day the tweet is going to be made on //12:00pm, 1:30am etc */
        var timeOfDay = changeHour(dateObj.getHours()) + ":" + addZero(dateObj.getMinutes()) + ' ';
        timeOfDay += (dateObj.getHours() >= 12) ? tweetBoostCalendarVars.dateTranslationObject.timePeriod[1] : tweetBoostCalendarVars.dateTranslationObject.timePeriod[0];

        /* create the timestamp for sorting the tweet */
        var timeArray = inputtedDate.split('-').join(',').split(':').join(',').split(' ').join(',').split(',');
        var timestamp = (Date.UTC(timeArray[0],(timeArray[1].replace('0', '') -1),timeArray[2],timeArray[3],timeArray[4],timeArray[5])/1000);

        /* create the new tweet from the information in the page */
        newTweet['excerpt']           = (tweetContent) ? tweetContent : '';
        newTweet['image']             = (tweetImageSrc) ? '<img width="32" height="32" src="' + tweetImageSrc + '" class="attachment-32x32 size-32x32" alt="" />' : '';
        newTweet['post_id']           = postId;
        newTweet['post_link']         = postLink;
        newTweet['post_title']        = postTitle;
        newTweet['tweet_date']        = (tweetDate) ? tweetDate : false;
        newTweet['tweet_time_of_day'] = (timeOfDay) ? timeOfDay : false; // the X o'clock of the tweet shown in the calendar
        newTweet['tweet_timestamp']   = (timestamp) ? timestamp : false;
        newTweet['twitter_account']   = (twitterAccount) ? twitterAccount : false;

        return [twitterAccount, newTweet];
    }

    /**
     * Retrieves all tweets for the selected twitter accounts, and sorts them by the time of day they're being made.
     **/
    function getTweetsByAccount(selectedAccounts = null, pageLoad = false, loadCalculatedTweets = false){
        /* if the data in the calendar hasn't changed */
        if(loadCalculatedTweets && globalUnparsedCurrentTweets !== ''){
            /* try loading the previously created tweets to try to save some resources */
            try {
                var tweets = JSON.parse(globalUnparsedCurrentTweets);
            } catch (e) {
                /* if that didn't work, calculate the tweets now */
                var tweets = getTweetsFromResponse();
            }
        }else{
            /* get the tweets from the ajax response */
            var tweets = getTweetsFromResponse();
        }

        /* setup an array for selected tweets */
        var selectedTweets = [];
        /* if there aren't any accounts supplied, and the page has just loaded, flatten all the tweets */
        if(selectedAccounts === null && pageLoad === true){
            /* loop through the twitter accounts */
            for(var twitterAccount in tweets){
                /* loop through the tweets in the account */
                for(var tweetDate in tweets[twitterAccount]){
                    /* if the tweet date doesn't already have tweets */
                    if(selectedTweets[tweetDate] === undefined){
                        /* add the tweets directly to the selected tweets array */
                        selectedTweets[tweetDate] = tweets[twitterAccount][tweetDate];
                    }else{
                        /* if the date already has tweets, add the tweets individually to the selected tweets array */
                        for(var index in tweets[twitterAccount][tweetDate]){
                            selectedTweets[tweetDate][selectedTweets[tweetDate].length] = tweets[twitterAccount][tweetDate][index];
                        }
                    }
                }
            }
        }else{
            if(typeof selectedAccounts === 'object' && jQuery.isArray(selectedAccounts)){
                /* if the selectedAccounts are an array */
                /* loop through the twitter accounts */
                for(var twitterAccount in tweets){
                    /* if the current account isn't one of the supplied accounts, skip to the next one */
                    if(selectedAccounts.indexOf(twitterAccount) === -1){
                        continue;
                    }
                    /* loop through the tweets in the account */
                    for(var tweetDate in tweets[twitterAccount]){
                        /* if the tweet date doesn't already have tweets */
                        if(selectedTweets[tweetDate] === undefined){
                            /* add the tweets directly to the selected tweets array */
                            selectedTweets[tweetDate] = tweets[twitterAccount][tweetDate];
                        }else{
                            /* if the date already has tweets, add the tweets individually to the selected tweets array */
                            for(var index in tweets[twitterAccount][tweetDate]){
                                selectedTweets[tweetDate][selectedTweets[tweetDate].length] = tweets[twitterAccount][tweetDate][index];
                            }
                        }
                    }
                }
            }else if(typeof selectedAccounts === 'object' && !jQuery.isArray(selectedAccounts)){
                /* if the selected account object is an "object" */
                /* loop through the given twitter accounts */
                for(var twitterAccount in selectedAccounts){
                    /* if the current account isn't one of the supplied accounts, skip to the next one */
                    if(selectedAccounts.indexOf(twitterAccount) === -1){
                        continue;
                    }
                    /* loop through the tweets in the account */
                    for(var tweetDate in tweets[twitterAccount]){
                        /* if the tweet date doesn't already have tweets */
                        if(selectedTweets[tweetDate] === undefined){
                            /* add the tweets directly to the selected tweets array */
                            selectedTweets[tweetDate] = tweets[twitterAccount][tweetDate];
                        }else{
                            /* if the date already has tweets, add the tweets individually to the selected tweets array */
                            for(var index in tweets[twitterAccount][tweetDate]){
                                selectedTweets[tweetDate][selectedTweets[tweetDate].length] = tweets[twitterAccount][tweetDate][index];
                            }
                        }
                    }
                }
            }
        }

        /* then sort the individual tweets by the time of day that they're to go out */
        for(var date in selectedTweets){
            selectedTweets[date].sort(function(a, b){
                return a.tweet_timestamp - b.tweet_timestamp;
            });
        }

        /* return the selected tweets */
        return selectedTweets;
    }

    /**
     * Retrieves tweets from the stringified response given by the initial ajax call, or tweets with their projections.
     * Then it formats the tweets slightly, and indexes the tweets by the date they are to be made
     **/
    function getTweetsFromResponse(){
        /* create an array for the calendar index dates that the tweets go out on */
        var tweetingDates = [];

        /* get the raw tweets from the response if there are any. If there aren't any tweets, set rawTweets to an empty object to format the var */
        var rawTweets = (globalUnparsedResponse !== '' && globalUnparsedResponse !== '[]') ? JSON.parse(globalUnparsedResponse) : {};

        /* create the tweet data array */
        var tweetData = {};
        /* loop through the twitter accounts the response object. If it's empty, the loop isn't run */
        for(var twitterAccount in rawTweets){
            /* set the twitter account index value to an array since this should be the first time the it's run */
            if(tweetData[twitterAccount] === undefined){
                tweetData[twitterAccount] = {};
            }

            /* loop through the tweets in the account */
            for(var tweet in rawTweets[twitterAccount]){

                /* get midnight of the day that the tweet is to be made */
                var tweetDay = new Date(new Date(rawTweets[twitterAccount][tweet].tweet_date).setHours(0,0,0,0)).toString();

                /* set the individual tweet's day to midnight of that day for indexing purposes */
                rawTweets[twitterAccount][tweet]['tweetDay'] = tweetDay;

                /* if there the tweet day index is already set */
                if(tweetData[twitterAccount][tweetDay]){
                    /* push the tweet data to the index */
                    tweetData[twitterAccount][tweetDay].push(rawTweets[twitterAccount][tweet]);
                }else{
                    /* if the tweet day index isn't set, set it to be an array */
                    tweetData[twitterAccount][tweetDay] = [];
                    /* then push the tweet data to the index */
                    tweetData[twitterAccount][tweetDay].push(rawTweets[twitterAccount][tweet]);
                }

                /* if the date isn't already in the date list */
                if(tweetingDates.indexOf(tweetDay) === -1){
                    /* push the date to the tweeting dates list */
                    tweetingDates.push(tweetDay);
                }
            }
        }

        /* if we've got any tweeting dates */
        if(tweetingDates){
            /* set the global tweeting dates list to this local one */
            globalTweetingDatesList = tweetingDates;

            /* and update the global list of these tweets in case they're useful later on */
            globalUnparsedCurrentTweets = JSON.stringify(tweetData);
        }

        /* return the array of parsed and dated tweets */
        return tweetData;
    }

    /**
     * Resets the date click tracker when the calendar month is changed.
     **/
    function resetCalendarDateClickTracker(){
        globalCalendarDateClickTracker = 1;
    }

    /**
     * Processes date information stored in calendar date titles and moves the
     * info into data attributes.
     * A createTweetCalendarTooltips helper function
     **/
    function formatCalendarDateAttr(){
        /* get the current month's calendar dates  */
        var tweetCalendarDates = jQuery('.tweet-boost-upcoming-tweets-calendar-date').not('.ui-datepicker-unselectable');

        /* loop through each date */
        for(var i = 0; i < tweetCalendarDates.length; i++){
            /* if the title holds the tweet date index */
            if(tweetCalendarDates[i].title){
                /* get the date index from the title and */
                var dateIndex = tweetCalendarDates[i].title;
                /* move the date index to a data attribute */
                tweetCalendarDates[i].setAttribute('title', '');
                tweetCalendarDates[i].setAttribute('data-tweet-date', dateIndex);
            }
        }
    }

    /**
     * Updates the current calendar dates with a class to indicate there's tweets going out on it.
     * Also removes the class from the date if there aren't tweets going out that day.
     * And removes the titles from dates that aren't on the current month.
     * A createTweetCalendarTooltips helper function
     **/
    function updateTweetingDates(){
        /* if there aren't any accounts yet, exit */
        if(tweetBoostCalendarVars.availibleTwitterAccountData.length === 0){
            return;
        }

        /* get all the dates in the current month */
        var dates = document.getElementsByClassName('tweet-boost-upcoming-tweets-calendar-date');
        /* loop through each of the calendar's dates */
        for(var i = 0; i < dates.length; i++){
            /* if the current date is from outside of the current month, erase the title and skip to the next date */
            if(dates[i].className.indexOf('ui-datepicker-unselectable') !== -1){
                dates[i].title = '';
                continue;
            }
            /* if the current date is in the global list of tweeting dates */
            if(globalTweetingDatesList.indexOf(dates[i].dataset.tweetDate) !== -1){
                /* and it doesn't already have the 'has-tweets' class */
                if(!jQuery(dates[i]).hasClass('tweet-boost-tweet-on-date')){
                    /* add the class */
                    jQuery(dates[i]).addClass('tweet-boost-tweet-on-date');
                }
            }else if(jQuery(dates[i]).hasClass('tweet-boost-tweet-on-date')){
                /* if the current date isn't in the list and it has the 'has-tweets' class,
                 *  remove the class and change the background to the initial color */
                jQuery(dates[i]).removeClass('tweet-boost-tweet-on-date');
                dates[i].children[0].style.backgroundColor = '';
            }
        }
        /* fire the calendar interact listeners to allow the tooltips to be displayed on any new dates */
        addCalendarDateInteractListeners();
    }

    /**
     * Handles when the user clicks on a post link inside a tweet tooltip.
     * If the link is to a tweet on the same page, it navigates the browser over to it.
     * If it's on a different page, it redirects the user to it.
     * A createTweetCalendarTooltips helper function
     **/
    function handleCalendarPostLinkClicks(event){
        /* get the link's url */
        var link = event.target.href;

        /* if there's no link, exit */
        if(!link){
            return;
        }else if(event.target.outerHTML.indexOf('href="tweet-boost"') !== -1 || event.target.baseURI === link){
            /* if the post link is for a newly created tweet or an existing tweet on this page, just navigate over to it */
            var tweetRowId = event.target.dataset.tweetRowId;
            jQuery('html, body').animate({
                scrollTop: jQuery('#acf-group_5989061655cc1').offset().top - 50
            }, 500);

            /* if the calendar tooltip was activated by a touch on mobile */
            if(globalCalendarLastEventTracker === 'touchend'){
                /* set the event to a touch event */
                var eventHandle = 'touchend.mobile-tweet-boost-upcoming-calendar-date-interact';
            }else{
                /* if it wasn't by mobile, set the event to a click one */
                var eventHandle = 'click.desktop-tweet-boost-upcoming-calendar-date-interact';
            }

            /* activate the event on the tooltip's parent date to hide the tooltip */
            jQuery(event.target).parents('td.tweet-boost-upcoming-tweets-calendar-date').trigger(eventHandle);

        }else{
            /* if the link is to a different schedule, redirect the user over there */
            window.location.replace(link);
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

    /** helper function that adds a zero to time strings that are single digits **/
    function addZero(i) {
        if (i < 10) {
            i = '0' + i;
        }
        return i;
    }

    /** helper function that removes 12 from the hour count **/
    function changeHour(hour){
        if(hour > 12){
            hour = hour - 12;
        }

        if(hour === 0){
            hour = 12;
        }

        return hour;
    }

    /** helper function to check for undefined, null, empty stings and false **/
    function isVal(value){
        if(value === undefined || value === '' || value === false || value === null){
            return false;
        }else{
            return true;
        }
    }
});

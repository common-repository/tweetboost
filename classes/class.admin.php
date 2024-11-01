<?php

if(!class_exists('Tweet_Boost_Admin_Screens')){

    class Tweet_Boost_Admin_Screens {

        static $tweet_boost; /*tweetboost settings array */

        public function __construct(){
            self::load_hooks();

        }

        public static function load_hooks(){

            /* register the TweetBoost settings area */
            add_action('admin_menu', array(__CLASS__, 'register_tweet_boost_menu'));

            /* enqueues the admin side styles */
            add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_styles'));

            /* enqueue the admin side scripts */
            add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));

            /* enqueue the calendar scripts */
            add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_calendar_scripts'));

            /* add the TweetBoost admin widgets */
            add_action('wp_dashboard_setup', array(__CLASS__, 'add_tweet_boost_dashboard_widgets'));

            /* add the target for the js based calendar to create the scheduled tweets calendar */
            add_action('add_meta_boxes', array(__CLASS__, 'create_scheduled_tweets_calendar'));

            /* save the time that the tweet is going to be made in the tweet's status data */
            add_action('acf/save_post', array(__CLASS__, 'save_tweet_time_in_status_data'), 10, 1);

            /* save the tweet status on post save as "Tweet Scheduled!" for display in the status message field */
            add_action('acf/save_post', array(__CLASS__, 'save_tweet_status_in_status_data'), 11, 1);

            /* if the tweet time changes, clear the status data */
            add_action('acf/update_value/key=field_598906629db22', array(__CLASS__, 'change_tweet_processing_status'), 10, 3);

            /* if the tweet photo changes, clear the status data */
            add_action('acf/update_value/key=field_598907a39db24', array(__CLASS__, 'change_tweet_processing_status'), 10, 3);

            /* if the tweet content changes, clear the status data */
            add_action('acf/update_value/key=field_598906cf9db23', array(__CLASS__, 'change_tweet_processing_status'), 10, 3);

            /* keep the tweet status from being changed when the post is saved */
            add_action('acf/update_value/key=field_59af60df2fbb0', array(__CLASS__, 'preserve_tweet_status_data'), 10, 3);

            /* listen for manual reset commands */
            add_action('wp_ajax_reset_tweet', array(__CLASS__, 'ajax_reset_tweet'));

            /* listen for tweet boost settings save requests */
            add_action('wp_ajax_save_tweetboost_settings', array(__CLASS__, 'ajax_save_settings'));

        }


        /**
         * Registers the TweetBoost Menu
         **/
        public static function register_tweet_boost_menu(){

            add_menu_page(
                '',
                __('TweetBoost','tweet-boost'),
                'activate_plugins',
                'tweet-boost-tweet-scheduler-settings',
                array(__CLASS__ , 'display_settings_page'),
                'dashicons-twitter'
            );
        }

        /**
         * Displays the TweetBoost Settings page "see: admin.php?page=tweet-boost-tweet-scheduler-settings"
         */
        public static function display_settings_page() {

            self::$tweet_boost = Tweet_Boost_Utilities::get_tweet_boost_settings();

            /* enqueue wp media for the benefit of the image uploader */
            wp_enqueue_media();

            /* Render hard coded UI elements */
            echo '<form id="tweet-boost-settings">';

            self::display_nav_tabs();
            self::display_welcome_screen();
            self::display_account_setup();
            self::display_global_settings();
            self::display_save_feature();

            /* allow others to hook here */
            do_action('tweet-boost/settings/display' , self::$tweet_boost );

            echo '</form>';

        }

        /**
         * Creates the index for the setting tabs and allows for the adding of additional setting tabs.
         */
        public static function display_nav_tabs() {

            $tab_items = apply_filters('tweet-boost/settings/tab' , array(
                'welcome' => __('Welcome Page' , 'tweet-boost' ),
                'account' => __('Account Setup' , 'tweet-boost' ),
                'settings' => __('Global Settings' , 'tweet-boost' )
            ));

            $active = (isset($_GET['tb-tab'])) ? $_GET['tb-tab'] : 'welcome';

            ?>
            <h2 class="nav-tab-wrapper">
                <?php

                foreach ( $tab_items as $id=>$label ) {
                    ?>
                    <li class="nav-tab <?php echo ($active == $id ) ? "tb-tab-active" : ""; ?>" data-tab-id="<?php echo $id; ?>"><?php echo $label; ?></li>
                    <?php
                }
                ?>
            </h2>
            <?php

        }

        /**
         * Displays Welcome Screen HTML
         */
        public static function display_welcome_screen() {
            ?>
            <div class="tab-container" id="tab-welcome">
                <br>
                <div class="centeredPrompt">
                    <div class="centeredPrompt__item centeredPromptIcon">
                        <div class="icon fa fa-twitter"></div>
                    </div>
                    <div class="centeredPrompt__item centeredPromptLabel"><?php _e('Welcome to TweetBoost!' , 'tweet-boost'); ?></div>
                    <div class="centeredPrompt__item centeredPromptDetails"><?php _e('A simple way to schedule tweets...' , 'tweet-boost' ); ?></div>
                    <!--
                    <a href="<?php echo TWEET_BOOST_STORE_URL; ?>" class="centeredPrompt__item button"><?php _e('Go Pro.' , 'tweet-boost' ); ?></a>
                    -->
                </div>
                <br>
                <!--- card --->
                <div class="cardGroup">
                    <div class="card cardGroup__card">
                        <div class="card__description cardGroup__cardDescription">
                            <a href="https://codeable.io/?ref=9LHaD">
                                <img src='https://referoo.co/creatives/2/asset.png' />
                            </a>
                        </div>
                    </div>
                    <div class="card cardGroup__card">
                        <!-- feature box -->
                        <div class="featureListItem">
                            <div class="featureListItem__icon">
                                <div class="icon fa fa-calendar"></div>
                            </div>
                            <div class="featureListItem__description"><b><?php _e('Did you Know?','tweet-boost'); ?></b> <?php _e('TweetBoostPro uses schedule automation to repeat publishing cycles.','tweet-boost'); ?></div>
                        </div>
                        <div class="featureListItem featureListItem--reverse">
                            <div class="featureListItem__icon">
                                <div class="icon fa fa-dashboard"></div>
                            </div>
                            <div class="featureListItem__description"><b><?php __('Did you Know?','tweet-boost'); ?></b> <?php _e('TweetBoostPro will let you create multiple tweets for each posts. Use variation techniques to boost trafic!' , 'tweet-boost'); ?></div>
                        </div>
                        <div class="featureListItem">
                            <div class="featureListItem__icon">
                                <div class="icon fa fa-dollar"></div>
                            </div>
                            <div class="featureListItem__description"><?php echo sprintf(__('TweetBoostPro Rocks, %ssign up now!%s' , 'tweet-boost') , '<a href="'.TWEET_BOOST_STORE_URL.'?wps=1">' , '</a>'); ?></div>
                        </div>
                    </div>
                    <div class="card cardGroup__card">
                        <a target="_blank" href="https://shareasale.com/r.cfm?b=1141247&amp;u=1647095&amp;m=41388&amp;urllink=&amp;afftrack="><img src="http://static.shareasale.com/image/41388/PremiumWordPressHosting300x250.png" border="0" style="width:100%" /></a>
                    </div>
                </div>
                <!--- testimony -->
            </div>
            <?php
        }

        /**
         * Displays Account Setup HTML
         */
        public static function display_account_setup(){
            ?>
            <div class="tab-container" id="tab-account">
                <p class="description notice notice-info">
                    <?php
                    echo sprintf(__('If you\'re new to setting up Twitter Apps, please see our guide on how to create your first Twitter App here: %sHow to add Twitter Accounts%s' , 'tweet-boost' )  , '<a href="https://wptweetboost.com/docs/add-twitter-accounts-tweet-boost-pro/" target="_blank">' , '</a>');
                    ?>
                </p>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Twitter Username' , 'tweet-boost' ); ?>
                        </th>
                        <td>
                            <input type="text" name="username" value="<?php echo  self::$tweet_boost['username']; ?>" placeholder="">
                        </td>
                        <td>
                            <span class="description">
                                <?php _e('Enter the Twitter username that the Twitter App belongs to.' , 'tweet-boost' ); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Twitter App Consumer Key (API Key)' , 'tweet-boost' ); ?>
                        </th>
                        <td>
                            <input type="text" name="consumer_key" value="<?php echo (strlen(self::$tweet_boost['consumer_key']) >= 8) ? substr_replace(self::$tweet_boost['consumer_key'], '**************************', 8) : self::$tweet_boost['consumer_key']; ?>">
                        </td>
                        <td>
                            <span class="description">
                                 <?php _e('Enter the Twitter App\'s Consumer Key in this field' , 'tweet-boost' ); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Twitter App Consumer Secret (API Secret)' , 'tweet-boost' ); ?>
                        </th>
                        <td>
                            <input type="text" name="consumer_secret" value="<?php echo (strlen(self::$tweet_boost['consumer_secret']) >= 8) ? substr_replace(self::$tweet_boost['consumer_secret'], '**************************', 8) : self::$tweet_boost['consumer_secret']; ?>">
                        </td>
                        <td>
                            <span class="description">
                                 <?php _e('Enter the Twitter App\'s Consumer Secret in this field' , 'tweet-boost' ); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Twitter App Access Token' , 'tweet-boost' ); ?>
                        </th>
                        <td>
                            <input type="text" name="access_token" value="<?php echo (strlen(self::$tweet_boost['access_token']) >= 8) ? substr_replace(self::$tweet_boost['access_token'], '**************************', 8) : self::$tweet_boost['access_token']; ?>">
                        </td>
                        <td>
                            <span class="description">
                                 <?php _e('Enter the Twitter App\'s Access Token in this field' , 'tweet-boost' ); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Twitter App Access Token Secret' , 'tweet-boost' ); ?>
                        </th>
                        <td>
                            <input type="text" name="access_token_secret" value="<?php echo (strlen(self::$tweet_boost['access_token_secret']) >= 8) ? substr_replace(self::$tweet_boost['access_token_secret'], '**************************', 8) : self::$tweet_boost['access_token_secret']; ?>">
                        </td>
                        <td>
                            <span class="description">
                                 <?php _e('Enter your Twitter App\'s Access Token Secret in this field' , 'tweet-boost' ); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Twitter Avatar' , 'tweet-boost' ); ?>
                        </th>
                        <td id="twitter-avatar-uploader">
                            <?php
                            // made with assistance from:
                            // https://codex.wordpress.org/Javascript_Reference/wp.media

                            // try getting the image src
                            $avatar_src = wp_get_attachment_image_src(self::$tweet_boost['twitter_avatar_id'], 'full');

                            // see if we have the image src
                            $has_avatar_src = is_array($avatar_src );
                            ?>

                            <div class="twitter-avatar-container">
                                <?php if( $has_avatar_src ) : ?>
                                    <img src="<?php echo $avatar_src[0] ?>" alt="" style="max-width:45px" />
                                <?php endif; ?>
                            </div>
                            <p class="hide-if-no-js twitter-avatar-controls">
                                <a class="upload-avatar-image <?php if($has_avatar_src){ echo 'hidden'; } ?>" 
                                   href="#">
                                    <?php _e('Set Avatar Image', 'tweet-boost' ) ?>
                                </a>
                                <a class="remove-avatar-image <?php if(!$has_avatar_src){ echo 'hidden'; } ?>" 
                                  href="#">
                                    <?php _e('Remove Image', 'tweet-boost' ) ?>
                                </a>
                            </p>
                            <input class="twitter_avatar_id" name="twitter_avatar_id" type="hidden" value="<?php echo esc_attr(self::$tweet_boost['twitter_avatar_id']); ?>" />
                        </td>
                        <td>
                            <span class="description">
                                 <?php _e('Enter your Twitter Account Avatar here. (The avatar is used in the TweetBoost Tweet calendar)' , 'tweet-boost' ); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            <?php
        }

        public static function display_save_feature() {
            ?>
            <div class="tab-footer">
                    <!-- This section gets pushed to the left side
                    <div class="">
                        <div class="fa fa-spinner tb-save-featureSpinner"></div>&nbsp;<?php _e('Saving...' , 'tweet-boost' ); ?>
                    </div>-->
                    <!-- This section gets pushed to the right side-->
                    <div class="">
                        <div class="button button-default" id="save_settings"><?php _e('Save','tweet-boost' ); ?></div>
                    </div>
                </div>
            <?php
        }

        /**
         *  Display Global Settings HTML
         */
        public static function display_global_settings(){
            ?>
            <div class="tab-container" id="tab-settings">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Use Featured Image In Tweet' , 'tweet-boost' ); ?>
                        </th>
                        <td>
                            <input type="radio" name="use_featured_image_in_tweet" value="0" <?php echo (! self::$tweet_boost['use_featured_image_in_tweet']) ? 'checked="checked"' : ''; ?>> <?php _e('No' , 'tweet-boost' ); ?> &nbsp; &nbsp;
                            <input type="radio" name="use_featured_image_in_tweet" value="1" <?php echo ( self::$tweet_boost['use_featured_image_in_tweet']) ? 'checked="checked"' : ''; ?>> <?php _e('Yes' , 'tweet-boost' ); ?>
                        </td>
                        <td>
                            <span class="description">
                                <?php _e('Do you want to use the post\'s featured image as the tweet image when you don\'t put one in a tweet?' , 'tweet-boost' ); ?>
                            </span>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Tweet Calendar Heat Range Limit' , 'tweet-boost' ); ?>
                        </th>
                        <td>
                            <input type="number" min="10" max="200" step="1" name="tweet_calendar_heat_range_limit" value="<?php echo self::$tweet_boost['tweet_calendar_heat_range_limit']; ?>">
                        </td>
                        <td>
                            <span class="description">
                                <?php _e('How many tweets do you think are a lot to be made in a single day? With this setting you can program the Tweet Calendar to color the dates that tweets are being made on based on how many tweets the date has. This makes it very easy to tell your tweeting volume at a glance.' , 'tweet-boost' ); ?>
                            </span>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Simplify Tweet Status Messages' , 'tweet-boost' ); ?>
                        </th>
                        <td>
                            <input type="radio" name="simplify_tweet_status_messages" value="0" <?php echo (! self::$tweet_boost['simplify_tweet_status_messages']) ? 'checked="checked"' : ''; ?>> <?php _e('No' , 'tweet-boost' ); ?> &nbsp; &nbsp;
                            <input type="radio" name="simplify_tweet_status_messages" value="1" <?php echo ( self::$tweet_boost['simplify_tweet_status_messages']) ? 'checked="checked"' : ''; ?>> <?php _e('Yes' , 'tweet-boost' ); ?>
                        </td>
                        <td>
                            <span class="description">
                                <?php _e('Do you want to have Tweet Boost put the Tweet Status Messages inside a small icon in the message field? You can see the status message by hovering the mouse over the icon, or by tapping it on a mobile device.' , 'tweet-boost' ); ?>
                            </span>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Tweet Error Limit' , 'tweet-boost' ); ?>
                        </th>
                        <td>
                            <input type="number" min="1" max="100" step="1" name="failed_attempt_limit" value="<?php echo self::$tweet_boost['failed_attempt_limit']; ?>">
                        </td>
                        <td>
                            <span class="description">
                                <?php _e('How many times should Twitter reject a tweet before Tweet Boost stops trying to make it? When Tweet Boost stops trying to make the tweet, there will be an error message in the Tweet Status Message that should give you information about how to resolve the error. Once the error is resolved, Tweet Boost will try to make the tweet again.' , 'tweet-boost' ); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            <?php
        }

        /**
         * Enqueues styles for the admin area
         * The styles are used in the dashboard and the post screens
         **/
        public static function enqueue_admin_styles(){
            global $post;

            /* make sure get_current_screen is defined */
            if(!function_exists('get_current_screen')){
                return;
            }

            /* get the current screen */
            $screen = get_current_screen();

            /* if the curent page is a post, enqueue the styles for the post edit screens */
            if($screen->post_type == 'post' && $screen->base == 'post'){
                wp_enqueue_style('tippyjs', TWEET_BOOST_PLUGIN_URLPATH . 'assets/includes/TippyJS/tippy.css');
                wp_enqueue_style('tweet-boost-admin-styles', TWEET_BOOST_PLUGIN_URLPATH . 'assets/css/tweet-boost-admin-styles.css');
                wp_enqueue_style('tweet-boost-admin-widgets-styles', TWEET_BOOST_PLUGIN_URLPATH . 'assets/css/tweet-boost-admin-widgets.css');
                wp_enqueue_style('fontawesome', TWEET_BOOST_PLUGIN_URLPATH . 'assets/includes/fontawesome/css/font-awesome.min.css');

                /* if the sweetalert styles aren't already enqueued, enqueue them */
                if(!wp_style_is('sweet-alert-css', 'enqueued')){
                    wp_enqueue_style('sweet-alert-css', TWEET_BOOST_PLUGIN_URLPATH . 'assets/includes/SweetAlert/sweetalert.css');
                }
            }

            /* if the current page is the dashboard, enqueue the styles for the admin widgets */
            if($screen->base == 'dashboard' && $screen->id == 'dashboard'){
                wp_enqueue_style('thickbox');
                wp_enqueue_style('tippyjs', TWEET_BOOST_PLUGIN_URLPATH . 'assets/includes/TippyJS/tippy.css');
                wp_enqueue_style('tweet-boost-admin-widgets-styles', TWEET_BOOST_PLUGIN_URLPATH . 'assets/css/tweet-boost-admin-widgets.css');
                wp_enqueue_style('fontawesome', TWEET_BOOST_PLUGIN_URLPATH . 'assets/includes/fontawesome/css/font-awesome.min.css');

                /* if the sweetalert styles aren't already enqueued, enqueue them */
                if(!wp_style_is('sweet-alert-css', 'enqueued')){
                    wp_enqueue_style('sweet-alert-css', TWEET_BOOST_PLUGIN_URLPATH . 'assets/includes/SweetAlert/sweetalert.css');
                }
            }
            
             /* if we're inside the the TweetBoost Settings section */
            if($screen->base == 'toplevel_page_tweet-boost-tweet-scheduler-settings' ) {
                wp_enqueue_style('tweet-boost-admin-settings-styles', TWEET_BOOST_PLUGIN_URLPATH . 'assets/css/tweet-boost-admin-settings.css');
                wp_enqueue_style('fontawesome', TWEET_BOOST_PLUGIN_URLPATH . 'assets/includes/fontawesome/css/font-awesome.min.css');
            }

        }

        /**
         * Enqueues the scripts used in the admin screens
         * They're active in the dashboard and the post edit screens
         **/
        public static function enqueue_admin_scripts(){
            global $post;

            /* make sure we're on an admin page */
            if(!is_admin()){
                return;
            }

            /* make sure get_current_screen is defined */
            if(!function_exists('get_current_screen')){
                return;
            }

            /* get the current screen */
            $screen = get_current_screen();

            /* if the curent page is a post, enqueue the scripts for creating tweets */
            if($screen->post_type == 'post' && $screen->base == 'post'){

                /* get the stored tweet boost settings */
                $tweet_boost_settings = Tweet_Boost_Utilities::get_tweet_boost_settings();

                wp_enqueue_script('tippyjs', TWEET_BOOST_PLUGIN_URLPATH . 'assets/includes/TippyJS/tippy.js', array('jquery'));
                wp_enqueue_script('tweet-boost-admin-scripts', TWEET_BOOST_PLUGIN_URLPATH . 'assets/js/tweet-boost-admin-scripts.js', array('jquery'));

                /* create the localized values for the admin script */
                $admin_vars = array(
                    'confirmPopup' => array(
                        'title'      => __('Reset Tweet Status?', 'tweet-boost' ),
                        'text'       => __('This will clear the tweet\'s error log and tell Tweet Boost to try to make the tweet again in a minute or two. If you want it to be made later, just enter a new time and save the post.', 'tweet-boost' ),
                        'buttonText' => __('Reset tweet status', 'tweet-boost' )
                    ),
                    'waitPopup' => array(
                        'title'      => __('Resetting Tweet Status', 'tweet-boost' ),
                        'text'       => __('Please wait...', 'tweet-boost' ),
                        'waitingGif' => TWEET_BOOST_PLUGIN_URLPATH . 'assets/includes/SweetAlert/loading_colorful.gif',
                    ),
                    'addPostLinkLengthWarningPopup' => array(
                        'title'      => __('Tweet Too Big', 'tweet-boost' ),
                        'text'       => __('Adding the post\'s link to the tweet now would make the tweet too long. Please shorten the tweet to fit the link in it.', 'tweet-boost' ),
                        'buttonText' => __('Ok', 'tweet-boost' )
                    ),
                    'postLinkTokenButton' => array(
                        'text'       => __('Add Post Link', 'tweet-boost' ),
                        'title'      => __('Add this post\'s link to this tweet. The link is represented by a token that Tweet Boost converts into the post\'s permalink before making the tweet.', 'tweet-boost' )
                    ),
                    'noLinkMessage'  => __('There isn\'t a link in this tweet.', 'tweet-boost' ),
                    'rowHandlerTitles' => array(
                        'default'    => __('Drag to reorder', 'tweet-boost' ),
                        'firstTweet' => __('This tweet is the first in the schedule.', 'tweet-boost' ),
                        'lastTweet'  => __('This tweet is the last in the schedule.', 'tweet-boost' ),
                    ),
                    'tweetComingSoonMsgs'   => array(
                        'plural'     => sprintf(__('The tweet is scheduled and will be published to Twitter in about %s minutes.', 'tweet-boost'), '{{X}}'),
                        'singular'   =>         __('The tweet is scheduled and will be published to Twitter in about one minute.', 'tweet-boost')
                    ),
                    'simplifyStatusMessage' => $tweet_boost_settings['simplify_tweet_status_messages'],
                    'scheduleStatusData'    => array(
                        'schedulePaused'    => array(
                            'pauseData'     => Tweet_Boost_Utilities::get_tweet_boost_post_settings($post->ID, 'schedule-pause-data'),
                            'pauseTitle'    => __('Tweet Paused', 'tweet-boost' )
                        )
                    ),
                    'eraseStatusNonce' => wp_create_nonce('tweet-boost-reset-status' . $post->ID),
                    'rescheduleNonce' => wp_create_nonce('tweet-boost-tweets-' . $post->ID),
                    'tweetTimeReviewNonce' => wp_create_nonce('tweet-boost-tweet-time' . $post->ID),
                    'currentTime' => current_time('timestamp')
                );

                /* localize the script */
                wp_localize_script('tweet-boost-admin-scripts', 'tweetBoostAdminScriptsVars', $admin_vars);

                /* if the sweetalert scripts aren't already enqueued, enqueue them */
                if(!wp_script_is('sweet-alert-js', 'enqueued')){
                    wp_enqueue_script('sweet-alert-js', TWEET_BOOST_PLUGIN_URLPATH . 'assets/includes/SweetAlert/sweetalert.min.js', array('jquery'));
                }
            }

            /* if the current page is the dashboard, enqueue the scripts for the admin widgets */
            if($screen->base == 'dashboard' && $screen->id == 'dashboard'){

                wp_enqueue_script('thickbox');
                wp_enqueue_script('tippyjs', TWEET_BOOST_PLUGIN_URLPATH . 'assets/includes/TippyJS/tippy.js', array('jquery'));
                wp_enqueue_script('tweet-boost-dashboard-widgets', TWEET_BOOST_PLUGIN_URLPATH . 'assets/js/tweet-boost-dashboard-widgets.js', array('jquery', 'jquery-ui-datepicker'));
                wp_enqueue_script('twitter-widgets', 'https://platform.twitter.com/widgets.js', array('jquery', 'jquery-ui-datepicker'));

                /* create the localized values for the dashboard widget scripts */
                $widget_vars = array(
                    'tweetBoostImagesPath'  => TWEET_BOOST_PLUGIN_URLPATH . 'assets/images',
                    'scheduleManagementWidgetRepeatTimes' => array(
                        '0'  => __('In Queue',    'tweet-boost' ),
                        '1'  => __('Tweeted!', 'tweet-boost' ),
                        '2'  => __('Has Error', 'tweet-boost' ),
                        '3'  => __('Paused', 'tweet-boost' )
                    ),
                    'confirmPlayPopup' => array(
                        'title' =>      __('Unpause The Tweet', 'tweet-boost' ),
                        'text' =>       __('Please confirm that you want to unpause the tweet.', 'tweet-boost' ),
                        'buttonText' => __('Confirm', 'tweet-boost' )
                    ),
                    'confirmPausePopup' => array(
                        'title' =>      __('Pause The Tweet', 'tweet-boost' ),
                        'text' =>       __('Please confirm that you want to pause the tweet.', 'tweet-boost' ),
                        'buttonText' => __('Confirm', 'tweet-boost' )
                    ),
                    'confirmManualTweet' => array(
                        'title' =>      __('Tweet Now', 'tweet-boost' ),
                        'text' =>       __('Pressing confirm will immediately attempt to publish this tweet to twitter.', 'tweet-boost' ),
                        'buttonText' => __('Confirm', 'tweet-boost' )
                    ),
                    'confirmManualTweetSuccess' => array(
                        'title' =>      __('Tweeted!', 'tweet-boost' ),
                        'text' =>       __('Your tweet has been published!', 'tweet-boost' ),
                        'buttonText' => __('Return', 'tweet-boost' )
                    ),
                    'confirmTweetRepeat' => array(
                        'title' =>      __('Reset Tweet', 'tweet-boost' ),
                        'text' =>       __('Pressing confirm will tell Tweet Boost to attempt to process this tweet again. ', 'tweet-boost' ),
                        'buttonText' => __('Confirm', 'tweet-boost' )
                    ),
                    'confirmTweetRepeatSuccess' => array(
                        'title' =>      __('Scheduled!', 'tweet-boost' ),
                        'text' =>       __('Your tweet has been scheduled and should post within the next 2-to-3 minutes.', 'tweet-boost' ),
                        'buttonText' => __('Return', 'tweet-boost' )
                    ),
                    'waitPopup' => array(
                        'title' =>      __('Working', 'tweet-boost' ),
                        'text' =>       __('Please wait...', 'tweet-boost' ),
                        'waitingGif' => TWEET_BOOST_PLUGIN_URLPATH . 'assets/includes/SweetAlert/loading_colorful.gif',
                    )
                );

                /* localise the script */
                wp_localize_script('tweet-boost-dashboard-widgets', 'tweetBoostDashboardWidgetVars', $widget_vars);

                /* if the sweetalert scripts aren't already enqueued, enqueue them */
                if(!wp_script_is('sweet-alert-js', 'enqueued')){
                    wp_enqueue_script('sweet-alert-js', TWEET_BOOST_PLUGIN_URLPATH . 'assets/includes/SweetAlert/sweetalert.min.js', array('jquery'));
                }
            }
            
            /* if we're inside the the TweetBoost Settings section */
            if($screen->base == 'toplevel_page_tweet-boost-tweet-scheduler-settings' ) {
                wp_enqueue_script('tweet-boost-settings-scripts', TWEET_BOOST_PLUGIN_URLPATH . 'assets/js/tweet-boost-settings.js', array('jquery'));

                $settings_vars = array(
                    'tweetBoostImagesPath'          => TWEET_BOOST_PLUGIN_URLPATH . 'assets/images',
                    'saveButton'          => array(
                        'save' => __('Save Again' , 'tweet-boost' ),
                        'saving' => __('Saving...' , 'tweet-boost' ),
                        'saved' => __('Success!' , 'tweet-boost' ),
                        'error' => __('Error' , 'tweet-boost' )
                    )
                );

                wp_localize_script('tweet-boost-settings-scripts', 'tweetBoostSettingsVars', $settings_vars);

            }
        }

        /**
         * Enqueues the calendar scripts and required vars
         **/
        public static function enqueue_calendar_scripts(){
            global $post;

            /* make sure we're on an admin page */
            if(!is_admin()){
                return;
            }

            /* make sure get_current_screen is defined */
            if(!function_exists('get_current_screen')){
                return;
            }

            /* get the current screen */
            $screen = get_current_screen();

            /* if the curent page is a post or the dashboard, enqueue the scripts for creating the calendar */
            if($screen->post_type == 'post' && $screen->base == 'post' || $screen->base == 'dashboard' && $screen->id == 'dashboard'){
                
                /* if the css element query scripts aren't enqueued yet, enqueue them */
                if(!wp_script_is('css-element-queries', 'enqueued')){
                    /* css element queries allow media query like functionality to elements themselves.
                     * So if an element resizes, it can be styled easily!
                     * https://github.com/marcj/css-element-queries */
                    wp_enqueue_script('css-element-queries-resize-sensors', TWEET_BOOST_PLUGIN_URLPATH . 'assets/includes/css-element-queries/src/ResizeSensor.js', '', '', true);
                    wp_enqueue_script('css-element-queries-element-queries', TWEET_BOOST_PLUGIN_URLPATH . 'assets/includes/css-element-queries/src/ElementQueries.js', '', '', true);
                    
                }

                wp_enqueue_script('tweet-boost-tweeting-dates-calendar', TWEET_BOOST_PLUGIN_URLPATH . 'assets/js/tweet-boost-tweeting-dates-calendar.js', array('jquery', 'jquery-ui-datepicker', 'tippyjs'));

                /* get the tweet boost settings */
                $tweet_boost_settings = Tweet_Boost_Utilities::get_tweet_boost_settings();
                /* get the user's twitter account */
                $twitter_account = Tweet_Boost_Utilities::get_twitter_account();

                /* create the localized values for the admin script */
                $calendar_vars = array(
                    'dateTranslationObject'          => array(
                                        'months'     => array(  __('January', 'tweet-boost' ),
                                                                __('February', 'tweet-boost' ),
                                                                __('March', 'tweet-boost' ),
                                                                __('April', 'tweet-boost' ),
                                                                __('May', 'tweet-boost' ),
                                                                __('June', 'tweet-boost' ),
                                                                __('July', 'tweet-boost' ),
                                                                __('August', 'tweet-boost' ),
                                                                __('September', 'tweet-boost' ),
                                                                __('October', 'tweet-boost' ),
                                                                __('November', 'tweet-boost' ),
                                                                __('December', 'tweet-boost' )),
                                        'timePeriod' => array(  __('am', 'tweet-boost' ),
                                                                __('pm', 'tweet-boost' ))
                    ),
                    'calendarHeatRangeText'          => array(  __('Tweet Density', 'tweet-boost' ),
                                                                __('Tweets For Most Active Account', 'tweet-boost' )
                    ),
                    'calendarPleaseAddTwitterAvatar' => __('Your twitter avatar', 'tweet-boost' ),
                    'availibleTwitterAccountData'    => $twitter_account,
                    'tweetDateHeatRangeLimit'        => $tweet_boost_settings['tweet_calendar_heat_range_limit'],
                    'currentTime'                    => current_time('timestamp')
                );

                /* localize the script */
                wp_localize_script('tweet-boost-tweeting-dates-calendar', 'tweetBoostCalendarVars', $calendar_vars);
            }  
        }

        /**
         * Add the TweetBoost admin widgets to the dashboard
         **/
        public static function add_tweet_boost_dashboard_widgets(){
            wp_add_dashboard_widget('tweet-boost-admin-notification-widget', __('TweetBoost: Action Log', 'tweet-boost' ), array(__CLASS__, 'render_tweet_boost_status_notification'));
            wp_add_dashboard_widget('tweet-boost-tweet-calendar-widget', __('TweetBoost: Calendar', 'tweet-boost' ), array(__CLASS__, 'create_tweet_boost_calendar_metabox'));
            wp_add_dashboard_widget('tweet-boost-admin-schedule-management-widget', __('TweetBoost: Tweet Management', 'tweet-boost' ), array(__CLASS__, 'create_tweet_boost_tweet_schedule_management_widget'));
        }

        /**
         * Render the "TweetBoost Status" widget.
         * The status notification widget shows a list of the last 50 tweet events (successful tweets & errors).
         **/
        public static function render_tweet_boost_status_notification(){
            global $wp_embed;

            $status_data = get_option('tweet-boost-tweet-status-log', array());

            if(empty($status_data)){
                ?>
            <div id="tweet-boost-status-notification">
                <div id="tweet-boost-status-log">
                    <div id="tweet-boost-tweet-no-status-container">
                        <div class="tweet-boost-tweet-no-status-message"><?php _e('TweetBoost hasn\'t tried to make any tweets yet, so there\'s no statuses to report.', 'tweet-boost' ); ?></div>
                    </div>
                </div>
            </div>
                <?php
                return;
            }
            ?>
            <div id="tweet-boost-status-notification">
                <div id="tweet-boost-status-log">
                    <?php
                    $counter = 1;
                    foreach($status_data as $key => $data){
                        /* quit once 50 events have been listed */
                        if($counter > 50){
                            break;
                        }
                        if(isset($data['tweet_attempt_status']) && $data['tweet_attempt_status'] == 'success'){
                            /* get tweet status id */
                            $twitter_response = (isset($data['twitter_response'])) ? $data['twitter_response'] : new stdClass;
                            $twitter_response->text = (isset($twitter_response->tweet)) ?  $twitter_response->tweet : '';
                            $twitter_response->id_str = (isset($twitter_response->id_str)) ?  $twitter_response->id_str : '';
                            $media =  (isset($twitter_response->entities->media) && is_array($twitter_response->entities->media)) ? $twitter_response->entities->media  : array();
                            $twitter_response->expanded_url = (isset($media[0]->expanded_url)) ? $media[0]->expanded_url : '#no-url';
                            $twitter_stats_link = 'https://twitter.com/i/tfb/v1/quick_promote/' . $twitter_response->id_str;
                            ?>

                            <div id="tweet-boost-tweet-container-success-<?php echo $counter ?>" class="tweet-boost-tweet-container tweet-boost-successful-tweet-container" >
                                <div class="tweet-boost-tweet-image">
                                    <i class="fa fa-calendar-check-o tippy" aria-hidden="true" title=" <?php echo date_i18n('F j, Y g:i a', $data['status_timestamp']); ?>"></i>
                                </div>
                                <div class="tweet-boost-tweet-excerpt" title="<?php _e('Awesome! The tweet was made successfully!', 'tweet-boost' ); ?>">
                                    <?php (isset($data['excerpt'])) ? $excerpt = $data['excerpt'] : $excerpt = ''; echo $excerpt; ?>
                                </div>
                                <div class="tweet-boost-post">
                                    <?php
                                        echo '<a href="#" class="view-tweet"  data-tweet-id="'.$twitter_response->id_str.'"><i class="fa fa-twitter tippy" aria-hidden="true" title="View Tweet"></i></a>';
                                        echo '<a href="'.get_edit_post_link($data['post']).'#tweet-boost" target="_blank"><i class="fa fa-cog tippy" aria-hidden="true" title="'.__('Manage Campaign' , 'tweet-boost' ) .'"></i></a>';
                                        echo '<a href="'.$twitter_stats_link.'" target="_blank" ><i class="fa fa-signal tippy" aria-hidden="true" title="'.__('View Tweet Stats. At this current moment in time you have to be logged in to view this page.' , 'tweet-boost' ).'"></i></a>';
                                    ?>
                                </div>
                            </div>
                            <div id="tweet-<?php echo $twitter_response->id_str; ?>" class="tweet-oembed" style="display:none;">
                                <div class='tweet' id='<?php echo $twitter_response->id_str; ?>' ></div>
                            </div>
                            <?php
                        } else {
                    ?>
                    <div id="tweet-boost-tweet-container-error-<?php echo $counter ?>" class="tweet-boost-tweet-container tweet-boost-error-tweet-container">
                        <?php /* create the tweet error notification */
                        if(isset($data['http_response'])){
                            /* check for a stored error message */
                            if(isset($data['displayed_message'])){
                                /* if there is one, set the error message to it */
                                $error_message = esc_html__($data['displayed_message'], 'tweet-boost' );
                            }else{
                                /* if there isn't a stored error message, create a generic one */
                                $error_message = __('There was an error with this tweet, but for some reason TweetBoost can\'t find the error message here. Though there should be an error message in the tweet\'s Tweet Status message, on the tweet\'s post.', 'tweet-boost' );
                            }
                        }
                        ?>
                        <div class="tweet-boost-tweet-image">
                            <i class="fa fa-exclamation tippy" aria-hidden="true" title=" <?php echo date_i18n('F j, Y g:i a', $data['status_timestamp']); ?>"></i>
                        </div>
                        <div class="tweet-boost-tweet-excerpt"><?php (isset($data['excerpt'])) ? $excerpt = $data['excerpt'] : $excerpt = ''; echo $excerpt; ?></div>
                        <div class="tweet-boost-post">
                                <a name="error-message" >
                                    <i class="fa fa-exclamation-triangle tippy" aria-hidden="true" title="<?php echo  $error_message; ?>"></i>
                                </a>

                                <?php
                                echo '<a href="'.get_edit_post_link($data['post']).'#tweet-boost"><i class="fa fa-cog tippy" aria-hidden="true" title="Manage Campaign"></i></a>';
                                ?>

                                <?php if(isset($data['stop_processing']) && $data['stop_processing'] == true){ ?>
                                <a name="error-repeat" >
                                    <i class="fa fa-repeat tippy" data-post-id="<?php echo $data['post']; ?>" aria-hidden="true"  title="<?php _e('Reset tweet. This will tell TweetBoost to requeue the tweet and attempt to publish it.' , 'tweet-boost' ); ?>"></i>
                                </a>
                                <?php } ?>
                        </div>
                    </div>
                    <?php
                        }
                        $counter++;
                    }
                     ?>
                </div>
            </div>
            <?php

        }

        /**
         * Updates the user on upcoming tweets by showing an interactive calendar, which has dates that have tweets going out being highlit.
         * When the user hovers over a date with tweets, a popup of the tweets for that day will appear
         **/
        public static function create_tweet_boost_calendar_metabox(){
            ?>
            <div class="tweet-boost-upcoming-tweets-calendar-container tweet-boost-calendar-field">
              <div class="tweet-boost-upcoming-tweets-calendar-wrapper">
                <div id="tweet-boost-upcoming-tweets-calendar"></div>
              </div>
              <div id="tweet-boost-upcoming-tweets-calendar-select-account-button-container">
              </div>
            </div>
            <?php
        }

        /**
         * Creates the dashboard tweet schedule management widget.
         * It allows the user to manage the tweet schedules
         * this should allow users to repeate shedules, pause them, maybe adjust them
         **/
        public static function create_tweet_boost_tweet_schedule_management_widget(){
            global $wpdb;

            $meta_table = $wpdb->prefix . 'postmeta';
            $posts_table = $wpdb->prefix . 'posts';

            /* get the ids of posts with tweets, and get the status data of those tweets */
            $query = "SELECT DISTINCT(`ID`) AS 'ID', `meta_value` AS 'meta_value' FROM " . $meta_table . " JOIN " . $posts_table . " WHERE `meta_key` = 'tweet_boost_status_data' AND TRIM(`meta_value`) != 'a:0:{}' AND `post_type` = 'post' AND `post_id` = `ID` ORDER BY `ID` DESC";
            $tweeting_posts = $wpdb->get_results($query);


            /* get the post IDs that have tweets that have been set to not process. Hopefully because the tweet was successfully made */ //
            $query = "SELECT `ID` AS 'ID' FROM " . $meta_table . " JOIN " . $posts_table . " WHERE `meta_value` LIKE '%\"stop_processing\"%' AND `post_type` = 'post' AND `post_id` = `ID` ORDER BY `post_id` DESC";
            $tweets_not_being_processed = $wpdb->get_results($query);

            /* if there aren't any tweet schdules, output a no schedules message */
            if(empty($tweeting_posts)){
                ?>
                <div id="tweet-boost-schedule-management-widget-no-schedules-container" style="height: 205px;">
                    <div id="tweet-boost-schedule-management-widget-no-schedules-message" style="text-align: center; padding-top: 76px; font-size: 14px;"><?php _e('There aren\'t any tweets yet, so there aren\'t any tweets to manage. Please make one, it\'ll be awesome! :D', 'tweet-boost' ); ?></div>
                    <style type="text/css">/*#tweet-boost-admin-schedule-management-widget{display:none !important;}*/</style>
                </div>
                <?php
                return;
            }

            $dont_process_tweets = array();
            /* if there are tweets that are set not to process */
            if(!empty($tweets_not_being_processed)){
                /* count the tweets and assign the number to the post they belong to */
                foreach($tweets_not_being_processed as $id){
                    if(isset($dont_process_tweets[$id->ID])){
                        $dont_process_tweets[$id->ID] += 1;
                    }else{
                        $dont_process_tweets[$id->ID] = 1;
                    }
                }
            }

            ?>
            <div class="tweet-boost-schedule-management-widget-container">
                <table id="tweet-boost-schedule-management-table">
                    <thead id="tweet-boost-sm-table-head">
                        <tr>
                            <th class="tweet-boost-sm-table-select-header"><?php  _e('Select',  'tweet-boost' ); ?></th>
                            <th class="tweet-boost-sm-table-post-header"><?php    _e('Post',    'tweet-boost' ); ?></th>
                            <th class="tweet-boost-sm-table-status-header"><?php  _e('Status', 'tweet-boost' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
            <?php
            $post_data = array();
            /* loop through each post id */
            foreach($tweeting_posts as $data){
                $post = get_post($data->ID);
                $pause_data = Tweet_Boost_Utilities::get_tweet_boost_post_settings($data->ID, 'schedule-pause-data');
                $status_data = maybe_unserialize($data->meta_value);

                $post_data[$data->ID]['title']              = $post->post_title;
                $post_data[$data->ID]['tweet_status_data']  = $status_data;
                $post_data[$data->ID]['schedule_stopped']   = date_i18n('F j, Y g:i a', strtotime($status_data['tweeting_time'])); // the tense isn't right. schedule_stopped is used for when the schedule ends, has an error and can't repeat, and paused.
                $post_data[$data->ID]['nonce']              = wp_create_nonce('TweetBoostScheduleManagementWidget' . $data->ID);
                $post_data[$data->ID]['processing_tense']   = '';

                /* if the tweet is set to stop_processing */
                if(isset($status_data['stop_processing']) && $status_data['stop_processing'] == true){
                    /* if the reason it's not being processed is because it was successfully made */
                    if($status_data['stop_processing_reason'] == 'success'){

                        /* set the displayed message to tell the user that schedule was a success */
                        $post_data[$data->ID]['tweet_status_message'] = __('Tweet Status: Tweet made successfully!', 'tweet-boost' );
                        $post_data[$data->ID]['processing_tense']     = __('Tweet Made On: ', 'tweet-boost' );

                        /* set the time that the tweet was made if possible */
                        if(isset($post_data[$data->ID]['tweet_made_at']) && !empty($post_data[$data->ID]['tweet_made_at'])){
                            $post_data[$data->ID]['schedule_stopped']     = date_i18n('F j, Y g:i a', $post_data[$data->ID]['tweet_made_at']);
                        }

                    }elseif(isset($pause_data['pause_status']) && $pause_data['pause_status'] === 'paused'){
                    /* if the tweet is set to pause, create the pause message, and set the pause time in the post data */
                        $post_data[$data->ID]['tweet_status_message'] = __('Tweet Status: Schedule Paused.', 'tweet-boost' );
                        $post_data[$data->ID]['processing_tense']     = __('Tweet Paused On: ', 'tweet-boost' );
                        $post_data[$data->ID]['schedule_stopped']     = date_i18n('F j, Y g:i a', strtotime($pause_data['paused_on']));
                    }else{
                    /* if the tweet is set to not process but wasn't made successfully, it must have been an error */
                        $post_data[$data->ID]['tweet_status_message'] = sprintf(__('Tweet Status: An error occurred that is preventing TweetBoost from making the tweet. Please go to "%s" and check the Tweet Status Message for more information.', 'tweet-boost' ), '<a href="' . get_edit_post_link($data->ID) . '">' . $post->post_title . '</a>');
                        $post_data[$data->ID]['processing_tense']     = __('Tweet Had Error On: ', 'tweet-boost' );
                    }
                }else{
                /* if the tweet is not set to "stop processing", then it must be pending */
                    $post_data[$data->ID]['tweet_status_message']     = __('Tweet Status: In Queue...', 'tweet-boost' );
                    $post_data[$data->ID]['processing_tense']         = __('Tweet To Be Made On: ', 'tweet-boost' );
                }

                ?>
                    <tr class="tweet-boost-schedule-management-row" data-post="<?php echo $data->ID; ?>">
                        <td class="tweet-boost-schedule-selector"><input type="radio" name="tweet-boost-schedule-management-selector" value="<?php echo $data->ID; ?>" /></td>
                        <td class="tweet-boost-schedule-post"><a href="<?php echo get_edit_post_link($data->ID); ?>"><?php echo $post_data[$data->ID]['title'] ; ?></a></td>
                        <td class="tweet-boost-schedule-status">0</td>
                        <td class="tweet-boost-schedule-status-icon"><div>???</div></td>
                    </tr>
                <?php
            }
            ?>
                    </tbody>
                    <tfoot id="tweet-boost-sm-table-foot">
                        <tr>
                            <th class="tweet-boost-sm-table-select-header"><?php  _e('Select',  'tweet-boost' ); ?></th>
                            <th class="tweet-boost-sm-table-post-header"><?php    _e('Post',    'tweet-boost' ); ?></th>
                            <th class="tweet-boost-sm-table-status-header"><?php  _e('Status', 'tweet-boost' ); ?></th>
                        </tr>
                    </tfoot>
                </table>
                <div id="tweet-boost-smw-details-and-controls-container">
                    <div id="tweet-boost-smw-detail-viewer-container" class="tweet-boost-smw-content-container">
                        <div id="tweet-boost-smw-detail-viewer-header"><?php _e('Tweet Information', 'tweet-boost' ); ?></div>
                        <div id="tweet-boost-smw-detail-viewer">
                        </div>
                        <div id="tweet-boost-smw-continue-schedule-controller" class="tweet-boost-smw-schedule-controller">
                            <div id="tweet-boost-smw-unpause-schedule-container">
                                <input type="button" title="<?php _e('Click to unpause the tweet.', 'tweet-boost' ); ?>" class="tweet-boost-smw-play-pause-button tweet-boost-action-button tweet-boost-smw-schedule-controller-button tippy button button-primary" data-schedule-status="continue" value="<?php _e('Unpause Tweet', 'tweet-boost' ); ?>" />
                            </div>
                        </div>
                        <div id="tweet-boost-smw-pause-schedule-controller" class="tweet-boost-smw-schedule-controller">
                            <div id="tweet-boost-smw-pause-schedule-container">
                                <input type="button" title="<?php _e('Click to pause the tweet.', 'tweet-boost' ); ?>" class="tweet-boost-smw-play-pause-button tweet-boost-action-button tweet-boost-smw-schedule-controller-button tippy button button-primary" data-schedule-status="pause" value="<?php _e('Pause Tweet', 'tweet-boost' ); ?>" /></div>
                        </div>
                    </div>
                </div>
            </div>
            <input id="tweet-boost-ms-schedule-data" type="hidden" data-schedule-data="<?php echo esc_attr(json_encode($post_data)); ?>" />
            <?php
        }

        /**
         * Creates the All Scheduled Tweets Calendar metabox.
         * Most all of the work of building the calendar is done by js,
         * this metabox contains the target of the js calendar creator.
         **/
        public static function create_scheduled_tweets_calendar(){
            global $post;

            /* make sure get_current_screen is defined */
            if(!function_exists('get_current_screen')){
                return;
            }

            /* get the current screen */
            $screen = get_current_screen();

            /* if the curent page is a post, enqueue the scripts for creating tweets */
            if($screen->post_type == 'post' && $screen->base == 'post'){
                /* create the metabox with a basic structure inside it to act as a target for the calendar js */
                add_meta_box('tweet-boost-tweet-calendar-widget', __('TweetBoost: Calendar', 'tweet-boost' ), array(__CLASS__, 'create_tweet_boost_calendar_metabox'), null, 'side');
            }
            
        }

        /**
         * Saves the tweet's time into the tweet status data on post save
         * @param int $post_id (the post id that the tweets belong to)
         **/
        public static function save_tweet_time_in_status_data($post_id){
			
			/* get the stored tweet */
			$tweet = Tweet_Boost_Utilities::get_tweet_boost_acf_fields($post_id);

            /* exit if it's empty */
			if(empty($tweet)){
				return;		
			}

            /* if it's not an array, try json_decoding it */
			if(!is_array($tweet['field_59af60df2fbb0'])){
                $tweet['field_59af60df2fbb0'] = json_decode($tweet['field_59af60df2fbb0'], true);
            }

            /* if there is a tweet time */
            if(!empty($tweet['field_598906629db22'])){
                /* set the tweeting time in the status data to the tweet time the user set in the post edit screen */
                $tweet['field_59af60df2fbb0']['tweeting_time'] = $tweet['field_598906629db22'];
            
                /* save the new status data */
                Tweet_Boost_Utilities::update_tweet_boost_acf_fields($tweet, $post_id);
            }

			return;
        }

        /**
         * Saves the tweet's status message as "Tweet Scheduled!" in the tweet status data on post save.
         * This way the user knows that the tweet in the processing queue
         * @param int $post_id (the post id that the tweets belong to)
         **/
        public static function save_tweet_status_in_status_data($post_id){
			
			/* get the stored tweet */
			$tweet = Tweet_Boost_Utilities::get_tweet_boost_acf_fields($post_id);

            /* exit if it's empty */
			if(empty($tweet)){
				return;		
			}

            /* if it's not an array, try json_decoding it */
			if(!is_array($tweet['field_59af60df2fbb0'])){
                $tweet['field_59af60df2fbb0'] = json_decode($tweet['field_59af60df2fbb0'], true);
            }

            /* if there isn't a tweet status message */
            if(!isset($tweet['field_59af60df2fbb0']['displayed_message']) && empty($tweet['field_59af60df2fbb0']['displayed_message'])){
                /* set a "Tweet Scheduled" message to use in the Tweet Status so the user knows the tweet is coming soon! */
                $tweet['field_59af60df2fbb0']['displayed_message'] = __('Tweet Scheduled!', 'tweet-boost');

                /* save the new status data */
                Tweet_Boost_Utilities::update_tweet_boost_acf_fields($tweet, $post_id);
            }

			return;
        }

        /**
         * If a tweet's content or time has been changed, clear the tweet's status data.
         * This is mainly to allow tweets to be processed after the content or time has been changed
         * @param string $tweet_time (the time the tweet is to be made)
         * @param int $post_id (the post id the tweet belongs to)
         * @param array $field (the acf field)
         **/
        public static function change_tweet_processing_status($tweet_content_data, $post_id, $field){

            /* get the existing tweet data from the meta */
            $stored_tweet_content_data = get_post_meta($post_id, $field['name'], true);

            /* if the stored data is the same as the new data, just return the new data. It doesn't change anything */
            if($tweet_content_data == $stored_tweet_content_data){
                return $tweet_content_data;
            }else{
            /* if the new tweet data is different from the stored one and the schedule isn't paused, clear the status data.
             * Since the user has decided to change the tweet in some way, he either knows about any errors or has taken care of them */

                /* clear the tweet's status data */
                update_post_meta($post_id, $row_prefix . 'tweet_boost_status_data', '');
            }

            /* return the tweet data */
            return $tweet_content_data;
        }

        /**
         * Prevents the tweet status data from being updated from the admin screen when the post is saved
         * @param mixed $value (the tweet status data)
         * @param int $post_id (the post the tweet belongs to)
         * @param array $field (the acf field)
         **/
        public static function preserve_tweet_status_data($value, $post_id, $field){

            /* get the stored status data */
            $stored_tweet_data = get_post_meta($post_id, $field['name'], true);

            /* if the stored data is empty, return an array to format it */
            if(empty($stored_tweet_data)){
                return array();
            }

            /* return the stored data to prevent the status data being updating from the admin screen */
            return serialize($stored_tweet_data);
        }

        /**
         * Listens for a call to reset a tweet that's had an error.
         * @return null echos our success or fail message
         */
        public static function ajax_reset_tweet() {
            $post_id = (isset($_REQUEST['post_id'])) ? (int) $_REQUEST['post_id'] : null;

            if (!$post_id) {
                die('invalid ajax call');
            }

            /* get the stored tweet */
            $tweet = Tweet_Boost_Utilities::get_tweet_boost_acf_fields($post_id);

            /* if for some reason there aren't any tweets, throw error */
            if(empty($tweet)){
                die(
                    json_encode(
                        array(
                            'error'=>__('ACF fields do not exist for tweet anymore', 'tweet-boost' )
                        )
                    )
                );
            }
			
			/* unset the status data */	
            $tweet['field_59af60df2fbb0'] = array();

            /* if there is a tweet time */
            if(!empty($tweet['field_598906629db22'])){
                /* set the tweeting time in the status data to the tweet time the user set in the post edit screen */
                $tweet['field_59af60df2fbb0']['tweeting_time'] = $tweet['field_598906629db22'];
            }

            /* update the stored tweet data */
            Tweet_Boost_Utilities::update_tweet_boost_acf_fields($tweet, $post_id);

            /* relay success */
            die(
                json_encode(
                    array(
                        'success'=> $tweet
                    )
                )
            );
        }

        /**
         * Ajax that handles Tweet Boost settings save feature
         */
        public static function ajax_save_settings() {

            parse_str($_POST['settings'] , $settings);
            
            /* get the stored tweet boost settings */
            $stored_settings = Tweet_Boost_Utilities::get_tweet_boost_settings();

            /* create the updated settings */
            $updated_settings = array();

            /* santize data */
            foreach($settings as $key=>$value) {
                $updated_settings[$key] = trim(sanitize_text_field($value));

                /* case specific */
                switch($key) {
                    case 'username':
                        $updated_settings[$key] = str_replace('@','', $updated_settings[$key] );
                        break;
                    case 'consumer_key':
                    case 'consumer_secret':
                    case 'access_token':
                    case 'access_token_secret':
                        /* if the obfuscation text is present in one of the settings */
                        if(strpos($updated_settings[$key], '*****') !== false){
                            /* check to see if there's a stored version of setting */
                            if(isset($stored_settings[$key])){
                                /* if there is, set the updated setting to the stored value */
                                $updated_settings[$key] = $stored_settings[$key];
                            }else{
                                /* if there isn't, set the updated setting to empty */
                                $updated_settings[$key] = '';
                            }
                        }
                        break;
                }
            }

            /* update the stored settings */
            update_option('tweet-boost' , $updated_settings, true);

            echo json_encode($updated_settings);
            exit;
        }

    }

    new Tweet_Boost_Admin_Screens;

}

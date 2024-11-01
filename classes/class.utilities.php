<?php

if(!class_exists('Tweet_Boost_Utilities')){

    class Tweet_Boost_Utilities{

        public function __construct(){
            self::load_hooks();

        }

        public static function load_hooks(){
            /* resets a tweet's status data array from an ajax call */
            add_action('wp_ajax_tweet_boost_reset_tweet_status', array(__CLASS__, 'ajax_tweet_boost_reset_tweet_status'));
            /* retrieves upcoming tweets by the twitter account that's making the tweets */
            add_action('wp_ajax_get_tweets_by_account', array(__CLASS__, 'ajax_get_tweets_by_account'));
            /* plays and pauses tweet schedules from the admin widget */
            add_action('wp_ajax_tweet_schedule_play_pause', array(__CLASS__, 'ajax_tweet_schedule_play_pause'));
        }

        /**
         * Gets all the tweet data from the post meta directly,
         * and formats it into the ACF standard form.
         * @param int $post_id (the id of the post that the schedule belongs to)
         * @return array $field_data (the tweet schedule repeater data)
         **/
        public static function get_tweet_boost_acf_fields($post_id){

            /* if there is no post id, exit */
            if(empty($post_id)){
                return;
            }

            /* get the post's meta */
            $post_meta = get_post_meta($post_id, '', true);

            /* create the field data array */
            $field_data = array();

            /* format the values into a field structure that ACF can use */
            $field_data['field_598906629db22'] = $post_meta['tweet_time'][0]; // tweet time
            $field_data['field_598907a39db24'] = $post_meta['tweet_photo'][0]; // tweet photo
            $field_data['field_598906cf9db23'] = $post_meta['tweet_content'][0]; // tweet content
            $field_data['field_59af60df2fbb0'] = maybe_unserialize(maybe_unserialize($post_meta['tweet_boost_status_data'][0])); // tweet status data

            /* return the field data */
            return $field_data;
        }

        /**
         * Gets TweetBoost settings.
         * @return array
         **/
        public static function get_tweet_boost_settings(){

            /* get the tweet boost settings */
            $tweet_boost = get_option( 'tweet-boost' , array() );

            /* put the settings into a nice array */
            $tweet_boost['username'] = (isset($tweet_boost['username'])) ? $tweet_boost['username'] : '';
            $tweet_boost['consumer_key'] = (isset($tweet_boost['consumer_key'])) ? $tweet_boost['consumer_key'] : '';
            $tweet_boost['consumer_secret'] = (isset($tweet_boost['consumer_secret'])) ? $tweet_boost['consumer_secret'] : '';
            $tweet_boost['access_token'] = (isset($tweet_boost['access_token'])) ? $tweet_boost['access_token'] : '';
            $tweet_boost['access_token_secret'] = (isset($tweet_boost['access_token_secret'])) ? $tweet_boost['access_token_secret'] : '';
            $tweet_boost['twitter_avatar_id'] = (isset($tweet_boost['twitter_avatar_id'])) ? $tweet_boost['twitter_avatar_id'] : '';
            $tweet_boost['use_featured_image_in_tweet'] = (isset($tweet_boost['use_featured_image_in_tweet'])) ? $tweet_boost['use_featured_image_in_tweet'] : '';
            $tweet_boost['tweet_calendar_heat_range_limit'] = (isset($tweet_boost['tweet_calendar_heat_range_limit'])) ? $tweet_boost['tweet_calendar_heat_range_limit'] : 10;
            $tweet_boost['simplify_tweet_status_messages'] = (isset($tweet_boost['simplify_tweet_status_messages'])) ? $tweet_boost['simplify_tweet_status_messages'] : 0;
            $tweet_boost['failed_attempt_limit'] = (isset($tweet_boost['failed_attempt_limit'])) ? $tweet_boost['failed_attempt_limit'] : 3;

            /* return the results of our attempts */
            return (is_array($tweet_boost)) ? $tweet_boost : array();
        }

        /**
         * Obtains the stored twitter account data from the tweet boost settings.
         * Used in the All Tweets Calendar
         * @return array
         **/
        public static function get_twitter_account( $tweet_boost = null, $return_tokens = false ){
            /* get tweet-boost settings */
            if (empty($tweet_boost)) {
                $tweet_boost = self::get_tweet_boost_settings();
            }

            /* get the stored twitter account data */
            $username = (isset($tweet_boost['username'])) ? $tweet_boost['username'] : '' ;

            /* get the avatar images */
            $twitter_avatar = wp_get_attachment_image_src($tweet_boost['twitter_avatar_id'], 'full');
            $twitter_avatar_thumb = wp_get_attachment_image_src($tweet_boost['twitter_avatar_id'], 'thumbnail');

            /* if there are no images, setup the placeholders */
            $twitter_avatar         = (is_array($twitter_avatar))       ?  $twitter_avatar[0]       : TWEET_BOOST_PLUGIN_URLPATH . 'assets/images/no-profile-image.jpg';
            $twitter_avatar_thumb   = (is_array($twitter_avatar_thumb)) ?  $twitter_avatar_thumb[0] : TWEET_BOOST_PLUGIN_URLPATH . 'assets/images/no-profile-image.jpg';


            /* assemble the basic account data */
            $twitter_account_data = array(
                'username' => $username,
                'twitter_avatar' => $twitter_avatar,
                'twitter_avatar_thumb' => $twitter_avatar_thumb
            );

            /* if we're supposed to return the access tokens too, include them in the account data */
            if($return_tokens === true){
                $twitter_account_data['consumer_key'] = (isset($tweet_boost['consumer_key']) ? $tweet_boost['consumer_key'] : '' );
                $twitter_account_data['consumer_secret'] = (isset($tweet_boost['consumer_secret']) ? $tweet_boost['consumer_secret'] : '' );
                $twitter_account_data['access_token'] = (isset($tweet_boost['access_token']) ? $tweet_boost['access_token'] : '' );
                $twitter_account_data['access_token_secret'] = (isset($tweet_boost['access_token_secret']) ? $tweet_boost['access_token_secret'] : '' );
            }

            return array($twitter_account_data);
        }

        /**
         * Updates the ACF tweet data fields directly through the meta.
         * @param string $repeater_name (the name of the tweet schedule repeater)
         * @pararm array $fields (the array of tweet field data)
         * @param int $post_id (the post id that the tweet schedule is on)
         **/
        public static function update_tweet_boost_acf_fields($fields = array(), $post_id = null){

            /* exit if the fields or the post id are empty */
            if(empty($fields) || empty($post_id)){
                return;
            }

            /* update the tweet fields with the supplied data */
            update_post_meta($post_id, 'tweet_time', $fields['field_598906629db22']);              // update the tweet's time to be made
            update_post_meta($post_id, 'tweet_boost_status_data', $fields['field_59af60df2fbb0']); // update the internal data about making the tweet

        }

        /**
         * Erases tweet status data for a specific tweet. Activated by an ajax call
         **/
        public static function ajax_tweet_boost_reset_tweet_status(){

            /* check the nonce to make sure it's valid */
            if(!wp_verify_nonce($_POST['nonce'], 'tweet-boost-reset-status' . $_POST['post_id'])){
                wp_send_json(array('error' => __('There was an error when trying to reset the tweet status data. Please reload the page and try again, that might fix it', 'tweet-boost' ), 'title' => __('Data Error', 'tweet-boost' )));
            }

            /* get the post id as it's int values */
            $post_id = intval($_POST['post_id']);

            /* if for some reason the post_id is 0 or empty, send back an error */
            if($post_id == 0 || empty($post_id)){
                wp_send_json(array('error' => __('There was an error with the post information. Please reload the page and try again', 'tweet-boost' ), 'title' => __('Post Data Error','tweet-boost' )));
            }

            /* retrieve the tweet's fields */
            $fields = self::get_tweet_boost_acf_fields($post_id);

            /* if the tweeting time is in the status data */
            if( isset($fields['field_59af60df2fbb0']['tweeting_time']) && 
                !empty($fields['field_59af60df2fbb0']['tweeting_time']))
            {
                /* replace the status data with an array containing the tweeting time */
                $fields['field_59af60df2fbb0'] = array('tweeting_time' => $fields['field_59af60df2fbb0']['tweeting_time']);
            }else{
                /* otherwise, replace the status data with an empty array */
                $fields['field_59af60df2fbb0'] = array();
            }

            /* save the new field data */
            self::update_tweet_boost_acf_fields($fields, $post_id);

            /* send the user a success message */
            wp_send_json(array('success' => __('The tweet status data has been reset!', 'tweet-boost' ), 'title' => __('Tweet Data Reset', 'tweet-boost' )));
        }

        /**
         * Stores a log of the past 100 successful tweets/tweet errors.
         * This log is used in the dashboard tweet status widget
         * @param array $tweet_data (data about the tweet attempt)
         * @param int $post_id (the id of the post that the tweet is from)
         * @param int $tweet_row_number (the tweet's displayed row number in the schedule)
         */
        public static function update_tweet_boost_status_log($tweet_data, $post_id){
            /* get the tweet status log */
            $status_log = get_option('tweet-boost-tweet-status-log', array());

            /* assemble the tweet status data */
            $status_data['http_response']   = (isset($tweet_data['field_59af60df2fbb0']['http_status_code'])) ? $tweet_data['field_59af60df2fbb0']['http_status_code'] : null;  // set the http_response code
            $status_data['twitter_response']= (isset($tweet_data['field_59af60df2fbb0']['twitter_response'])) ? $tweet_data['field_59af60df2fbb0']['twitter_response'] : null;  // set the entire twitter response
            $status_data['excerpt']         = (isset($tweet_data['field_598906cf9db23'])) ? $tweet_data['field_598906cf9db23'] : null;                                          // set the tweet snippet to give the user an idea of which tweet the status is about
            $status_data['post']            = (isset($post_id)) ? $post_id : null;                                                                                              // set the post id so we know where the tweet is
            $status_data['tweet_image']     = (isset($tweet_data['field_598907a39db24'])) ? $tweet_data['field_598907a39db24'] : null;                                          // set the tweet image fo better UX purposes
            $status_data['tweet_date']      = (isset($tweet_data['field_598906629db22'])) ? $tweet_data['field_598906629db22'] : null;                                          // set the tweet's date
            $status_data['tweet_timestamp'] = (isset($tweet_data['field_598906629db22'])) ? strtotime($tweet_data['field_598906629db22']) : null;                               // set the tweet's timestamp
            $status_data['status_timestamp']= current_time('timestamp');                                                                                                        // set the timestamp of the current logging

            /* if the http status code is set */
            if(isset($tweet_data['field_59af60df2fbb0']['http_status_code']) && !empty($tweet_data['field_59af60df2fbb0']['http_status_code'])){
                /* if the 200 http code is set... */
                if(isset($tweet_data['field_59af60df2fbb0'][200]) && $tweet_data['field_59af60df2fbb0'][200] == true){
                    /* log the tweet as a success */
                    $status_data['tweet_attempt_status'] = 'success';
                }else{
                /* if the 200 http code isn't set, log the tweet an error */
                    $status_data['tweet_attempt_status'] = 'error';
                }
            }else{
            /* if the http status code isn't set, log the tweet as an error */
                $status_data['tweet_attempt_status'] = 'error';
            }

            /* if there is an http error code supplied in the tweet status data and the error status data is stored */
            if(isset($tweet_data['field_59af60df2fbb0']['http_status_code']) && isset($tweet_data['field_59af60df2fbb0'][$tweet_data['field_59af60df2fbb0']['http_status_code']])){
                $status_data['error_count'] = $tweet_data['field_59af60df2fbb0'][$tweet_data['field_59af60df2fbb0']['http_status_code']]['standard'];
            }

            /* if the tweet has stopped processing, note that too */
            if(isset($tweet_data['field_59af60df2fbb0']['stop_processing']) && $tweet_data['field_59af60df2fbb0']['stop_processing'] == true){
                $status_data['stop_processing'] = true;
            }

            /* if there's a displayed message, include it in the log */
            if(isset($tweet_data['field_59af60df2fbb0']['displayed_message']) && !empty($tweet_data['field_59af60df2fbb0']['displayed_message'])){
                $status_data['displayed_message'] = $tweet_data['field_59af60df2fbb0']['displayed_message'];
            }

            /* add the tweet data to the beginning of the proper status listing */
            array_unshift($status_log, $status_data);

            /* limit the length of the status log to 100 entries */
            $status_log = array_slice($status_log, 0, 100);

            /* update the status log */
            update_option('tweet-boost-tweet-status-log', $status_log);

        }

        /**
         * Saves the TweetBoost post settings to an array in the post's meta.
         * A setting can be deleted by passing its key and setting the $delete_setting flag to true.
         * All of a post's TweetBoost settings can be deleted by passing an empty string as the setting key and setting the delete_setting flag to true.
         * @param  int    $post_id        (the id of the post that the setting(s) is for)
         * @param  string $setting_key    (the key of the setting value we're looking for)
         * @param  mixed  $setting_value  (the value to be saved to the setting key)
         * @param  bool   $delete_setting (the flag for deleting the chosen setting(s))
         * @return bool  (returns "true" if the settings have been updated, returns "false" if the settings could not be updated)
         **/
        public static function update_tweet_boost_post_settings($post_id, $setting_key = '', $setting_value = '', $delete_setting = false){
            /* exit if the setting key is empty and the delete flag hasn't been set */
            if($setting_key === '' && $delete_setting !== true){
                /* return false to say that the settings haven't been updated */
                return false;
            }

            /* get the stored settings */
            $settings = get_post_meta($post_id, 'tweet_boost_post_settings', true);

            /* if the settings haven't been created yet */
            if($settings === ''){
                /* create an empty array to start with */
                $settings = array();
            }else{
            /* if the settings have been created, unserialize them if they've been serialized */
                $settings = maybe_unserialize($settings);
            }

            /* if the setting(s) should be deleted and there are settings to delete */
            if($delete_setting === true && !empty($settings)){
                /** if all the TweetBoost post settings are supposed to be deleted **/
                /* if the setting key is an empty string */
                if($setting_key === ''){
                    /* update the post settings with an empty array to wipe the settings */
                    $result = update_post_meta($post_id, 'tweet_boost_post_settings', array());
                    /* if the result is a string, return false since the settings weren't updated */
                    if(is_string($result)){
                        return false;
                    }else{
                    /* if the result is a bool, return it for the benefit of whatever might be listening for it */
                        return $result;
                    }
                }

                /** if a specific setting is supposed to be deleted **/
                /* see if the setting exists */
                if(isset($settings[$setting_key])){
                    /* unset the setting */
                    unset($settings[$setting_key]);
                    /* update the post settings with the new settings */
                    $result = update_post_meta($post_id, 'tweet_boost_post_settings', array());
                    /* if the result is a string, return false since the settings weren't updated */
                    if(is_string($result)){
                        return false;
                    }else{
                    /* if the result is a bool, return it for the benefit of whatever might be listening for it */
                        return $result;
                    }
                }
            }else{
            /** if the settings are to be updated **/
                /* set the new setting */
                $settings[$setting_key] = $setting_value;
                /* update the settings with the new setting */
                $result = update_post_meta($post_id, 'tweet_boost_post_settings', $settings);
                /* if the result is a string, return false since the settings weren't updated */
                if(is_string($result)){
                    return false;
                }else{
                /* if the result is a bool, return it for the benefit of whatever might be listening for it */
                    return $result;
                }
            }

            /** if the settings haven't been returned at this point, save the settings as currently they currently exist **/
            $result = update_post_meta($post_id, 'tweet_boost_post_settings', $settings);
            /* if the result is a string, return false since the settings weren't updated */
            if(is_string($result)){
                return false;
            }else{
            /* if the result is a bool, return it for the benefit of whatever might be listening for it */
                return $result;
            }
        }

        /**
         * Gets a TweetBoost setting from the post meta.
         * Setting $return_all to true returns all TweetBoost post settings for the given post
         * @param int    $post_id     (the id of the post that the setting(s) is from)
         * @param string $setting_key (the key of the setting value we're looking for)
         * @param bool   $return_all  (the flag for returning all TweetBoost settings for the post)
         * @return mixed
         **/
        public static function get_tweet_boost_post_settings($post_id, $setting_key = '', $return_all = false){
            $settings = get_post_meta($post_id, 'tweet_boost_post_settings', true);

            $settings = maybe_unserialize($settings);

            /* if there are settings and all the settings are supposed to be returned */
            if($settings !== '' && $return_all === true){
                /* return all of the post's TweetBoost settings */
                return $settings;
            }elseif(isset($settings[$setting_key]) && $settings[$setting_key] !== ''){
            /* if the current setting exists and isn't an empty string, return the setting */
                return $settings[$setting_key];
            }else{
            /* if the setting doesn't exist, or the settings are empty, return an empty string */
                return '';
            }
        }
        
        /**
         * Gets all unmade tweets for the All Tweets Calendar, sorted by twitter account
         * Returns an error if no tweets were found
         * @return string (a JSON encoded string either of tweets sorted by account, or an error message telling the user that no tweets were found)
         **/
        public static function ajax_get_tweets_by_account(){
            global $wpdb;

            /* setup the query tables */
            $meta_table = $wpdb->prefix . 'postmeta';
            $posts_table = $wpdb->prefix . 'posts';

            /* get the current time */
            $current_time = current_time('timestamp');

            /* get the time 30 days ago to limit how far back the tweets will be queried */
            $past_time = date('Y-m-d H:i:s', $current_time - 2592000);
            
            /* get all the posts that have tweets going out between 30 days ago and the indefinite future */
            $query = "SELECT DISTINCT(`ID`) AS 'ID' FROM " . $meta_table . " JOIN " . $posts_table . " WHERE `meta_key` = 'tweet_time' AND `post_type` = 'post' AND `post_id` = `ID` AND `meta_value` > '" . $past_time . "' ORDER BY `ID` DESC";
            $results = $wpdb->get_results($query);

            /* if there weren't any tweets found, send an error and exit */
            if(empty($results)){
                wp_send_json(json_encode(array('error' => __('No tweets found', 'tweet-boost' ))));
            }

            /* get tweet-boost settings */
            $tweet_boost = self::get_tweet_boost_settings();

            /** loop through the tweeting posts and get all the tweets that are due to be made.
             *  And store them under the twitter user name that they're being made under **/
            $tweet_array = array();
            $dont_process_tweets = array();
            foreach($results as $post_id){

                /* get the post's tweet */
                $tweet = self::get_tweet_boost_acf_fields($post_id->ID);
			
                /* get if the tweet is paused */
                $pause_data = Tweet_Boost_Utilities::get_tweet_boost_post_settings($post_id->ID, 'schedule-pause-data');

                /* create a temp array for the tweet data */
                $assembled_tweet_data = array();
                /* get the time that the tweet is supposed to go out */
                $time_to_tweet = strtotime($tweet['field_598906629db22']);

                /* get the account the tweet belongs to */
                $assembled_tweet_data['twitter_account'] = $tweet_boost['username'];
                /* create the tweet UID to overcome the spooky action at a distance */
                $assembled_tweet_data['tweet_uid'] = md5(str_shuffle($time_to_tweet . $current_time));
                /* add the tweet's timestamp for sorting purposes */
                $assembled_tweet_data['tweet_timestamp'] = $time_to_tweet;
                /* create the tweet date time */
                $assembled_tweet_data['tweet_date'] = date_i18n('F j, Y g:i a', $time_to_tweet);
                /* create the tweet displayed time of day */
                $assembled_tweet_data['tweet_time_of_day'] = date_i18n('g:i a', $time_to_tweet);
                /* add the post id */
                $assembled_tweet_data['post_id'] = $post_id->ID;
                /* add the post's edit link */
                $assembled_tweet_data['post_link'] = get_edit_post_link($post_id->ID);
                /* add the post's title */
                $assembled_tweet_data['post_title'] = get_the_title($post_id->ID);
                /* add the tweet's content excerpt if it's set */
                if(isset($tweet['field_598906cf9db23']) && !empty($tweet['field_598906cf9db23'])){
                    $assembled_tweet_data['excerpt'] = $tweet['field_598906cf9db23'];
                }
                /* add the tweet's image if it's set */
                if(isset($tweet['field_598907a39db24']) && !empty($tweet['field_598907a39db24'])){
                    $assembled_tweet_data['image'] = wp_get_attachment_image((int)$tweet['field_598907a39db24'], array('32', '32'));
                }elseif($tweet_boost['use_featured_image_in_tweet']){
                /* if there isn't an image set, but TweetBoost is set to use the featured image as the twitter pic */
                    /* try to get the featured image's id */
                    $featured_image_id = get_post_thumbnail_id($post_id->ID);
                    /* if there is a featured image */
                    if(!empty($featured_image_id)){
                        /* use that for the tweet image in the calendar */
                        $assembled_tweet_data['image'] = wp_get_attachment_image($featured_image_id, array('32', '32'));
                    }                            
                }

                /* if the tweet isn't supposed to be processed */
                if(isset($tweet['field_59af60df2fbb0']['stop_processing'])){
                    /* add that the tweet isn't supposed to be processed */
                    $assembled_tweet_data['dont_process'] = true;
                    /* add why the tweet isn't supposed to be processed */
                    $assembled_tweet_data['dont_process_reason'] = $tweet['field_59af60df2fbb0']['stop_processing_reason'];
                    /* if the schedule is paused, add that to the tweet too */
                    if($pause_data['pause_status'] === 'paused'){
                        $assembled_tweet_data['schedule_paused'] = true;
                    } 
                    /* and add it to the don't process array */
                    $dont_process_tweets[$tweet_boost['username']][] = $assembled_tweet_data;  
                }else{
                    /* if the tweet is supposed to be processed, add it to the process array */
                    $tweet_array[$tweet_boost['username']][] = $assembled_tweet_data;
                }

            }

            wp_send_json(json_encode(array('success' => $tweet_array, 'dont_process_tweets' => $dont_process_tweets)));
            
        }
        
        /**
         * Sets a tweet that doesn't have errors to play or pause
         * on an ajax call from the TweetBoost: Tweet Management widget
         **/
        public static function ajax_tweet_schedule_play_pause(){

            /* exit if the user doesn't have a high enough access level */
            if(!current_user_can('publish_pages')){
                wp_send_json(array('error' => __('The request could not be processed, please reload the page and try again', 'tweet-boost' ), 'title' => __('Data Error', 'tweet-boost' ))); // not entirely accurate error, but if he doesn't have the access to do this, there must be some trickery afoot!
            }

            /* if a post id isn't supplied, throw an error */
            if(empty($_POST['post_id'])){
                wp_send_json(array('error' => __('The post id for this schedule isn\'t set. Are there any tweets in the schedule? If there are please try reloading the page and trying again. If this error persists, it could be caused by a plugin conflict.', 'tweet-boost' ), 'title' => __('Data Error', 'tweet-boost' )));
            }

            /* if the schedule status isn't set, throw an error */
            if(empty($_POST['schedule_status'])){
                wp_send_json(array('error' => __('The tweet schedule\'s Play or Pause status couldn\'t be retrieved. Are there any tweets in the schedule? If there are please try reloading the page and trying again. If this error persists, it could be caused by a plugin conflict.', 'tweet-boost' ), 'title' => __('Data Error', 'tweet-boost' )));
            }

            /* check the nonce to make sure it's valid */
            if(!wp_verify_nonce($_POST['nonce'], 'TweetBoostScheduleManagementWidget' . $_POST['post_id'])){
                wp_send_json(array('error' => __('There was an error when trying ' . $schedule_status . ' the schedule. Please reload the page and try again, that might fix it', 'tweet-boost' ), 'title' => __('Data Error', 'tweet-boost' )));
            }

            /* sanitize the variables */
            $post_id = intval($_POST['post_id']);
            $schedule_status = sanitize_text_field($_POST['schedule_status']);

            /* play/pause translate strings */
            $play_pause_string = array(                         'continue' => __('unpause',  'tweet-boost' ), 'pause' => __('pause',  'tweet-boost' ),
                                        'plural'    => array(   'continue' => __('unpaused', 'tweet-boost' ), 'pause' => __('paused', 'tweet-boost' )),
                                        'uc'        => array(   'continue' => __('Unpause',  'tweet-boost' ), 'pause' => __('Pause',  'tweet-boost' )),
                                        'uc_plural' => array(   'continue' => __('Unpaused', 'tweet-boost' ), 'pause' => __('Paused', 'tweet-boost' ))

                                        );

            /* create a default status message */
            $play_pause_status = array('error' => sprintf(__('The tweet couldn\'t be set to %s. Either there was an error, or the tweet has been made. Any TweetBoost settings for the tweet in the post that could be set to %s, were set to %s though.', 'tweet-boost' ), $play_pause_string[$schedule_status], $play_pause_string[$schedule_status], $play_pause_string[$schedule_status]), 'title' => sprintf(__('Couldn\'t %s the Schedule', 'tweet-boost' ), $play_pause_string['uc'][$schedule_status]));

            /* get the tweet's fields */
            $fields = self::get_tweet_boost_acf_fields($post_id);

            /* set the processing tense. Is the schedule paused, or is it in progress? */
            $processing_tense = '';

            /* get the current time */
            $current_time = current_time('timestamp');

            /* if the schedule is set to play and there are tweets in the schedule */
            if($schedule_status == 'continue' && !empty($fields)){
                /* delete the paused schedule data from the post meta */
                self::update_tweet_boost_post_settings($post_id, 'schedule-pause-data', '', true);
                /* if the tweet has been paused */
                if(isset($fields['field_59af60df2fbb0']['stop_processing']) && $fields['field_59af60df2fbb0']['stop_processing'] == true && $fields['field_59af60df2fbb0']['stop_processing_reason'] == 'schedule_paused'){
                    /* if the current time is before the time that the tweet is to be made */
                    if($current_time < strtotime($fields['field_598906629db22'])){
                        /* unset the pause settings to let the tweet be processed normally */
                        unset($fields['field_59af60df2fbb0']['stop_processing']);
                        unset($fields['field_59af60df2fbb0']['stop_processing_reason']);
                        unset($fields['field_59af60df2fbb0']['displayed_message']);

                        /* set the displayed state of processing to "Tweet To Be Made On" */
                        $processing_tense = __('Tweet To Be Made On: ', 'tweet-boost' );
                        /* set the time that the tweet is to be made */
                        $fields['field_59af60df2fbb0']['schedule_stops_on'] = date_i18n('F j, Y g:i a', strtotime($fields['field_598906629db22']));
                        /* set the tweet's status message */
                        $fields['field_59af60df2fbb0']['tweet_status_message']   = __('Tweet Status: In Queue...', 'tweet-boost' );
                        /* get the tweet's status to update the Tweet Management widget status viewer */
                        $play_pause_status = $fields['field_59af60df2fbb0'];
                    }else{
                    /* if the current time is past the time that the tweet was to be made,
                     * keep the tweet set to not process and update the reason and displayed message */
                        $fields['field_59af60df2fbb0']['stop_processing_reason'] = 'tweet_expired';
                        $fields['field_59af60df2fbb0']['displayed_message']      = __('This tweet has expired. If you still want to make the tweet, you can update the Tweet Time to a time in the future.', 'tweet-boost' );

                        /* set the displayed state of processing to "Tweet Expired on" */
                        $processing_tense = __('Tweet Expired On: ', 'tweet-boost' );
                        /* also set the time that the tweet expired */
                        $fields['field_59af60df2fbb0']['schedule_stops_on'] = date_i18n('F j, Y g:i a', strtotime($fields['field_598906629db22']));
                        /* set the tweet status message */
                        $fields['field_59af60df2fbb0']['tweet_status_message']   = __('Tweet Status: Tweet Expired', 'tweet-boost' );
                        /* get the tweet's status to update the Tweet Management widget status viewer */
                        $play_pause_status = $fields['field_59af60df2fbb0'];
                    }
                }else{
                /* if the current tweet hasn't been paused, but is set to not process.
                 * That could be be because it was made, or it had an error. */

                    /* if the current tweet is the last one and it wasn't successfully made */
                    if(isset($fields['field_59af60df2fbb0']['stop_processing_reason']) && $fields['field_59af60df2fbb0']['stop_processing_reason'] != 'success'){
                        /* set the schedule error status message */
                        $fields['field_59af60df2fbb0']['tweet_status_message']   = sprintf(__('Tweet Status: An error occured that is preventing TweetBoost from making the tweet. Please go to "%s" and check the Tweet Status Message for more information.', 'tweet-boost' ), get_the_title($post_id));

                    }elseif(isset($fields['field_59af60df2fbb0']['stop_processing_reason']) && $fields['field_59af60df2fbb0']['stop_processing_reason'] == 'success'){
                    /* if the tweet has been successfully made... */
                        /* set the tweet success status message telling the user that the schedule has been completed */
                        $fields['field_59af60df2fbb0']['tweet_status_message']   = __('Tweet Status: Tweet made successfully!', 'tweet-boost' );
                    }
                }
            }elseif($schedule_status == 'pause' && !empty($fields)){
            /* if the schedule is set to play and there are tweets in the schedule */ // its somewhat easier to pause tweets than to play them ;)
                /* create a setting in the post meta that says the schedule is paused and when it was paused */
                self::update_tweet_boost_post_settings($post_id, 'schedule-pause-data', array('pause_status' => 'paused', 'paused_on' => date_i18n('F j, Y g:i a', current_time('timestamp'))));
                /* if the current tweet isn't already set to not process */
                if(!isset($fields['field_59af60df2fbb0']['stop_processing'])){
                    /* set the tweet to not process and add a message telling the user that the tweet has been paused */
                    $fields['field_59af60df2fbb0']['stop_processing']        = true;
                    $fields['field_59af60df2fbb0']['stop_processing_reason'] = 'schedule_paused';
                    $fields['field_59af60df2fbb0']['displayed_message']      = __('This tweet has been paused. To unpause it, please go over to the "TweetBoost: Tweet Management" widget in the dashboard, select this tweet\'s post and click the "Unpause Tweet" button.', 'tweet-boost' );

                    /* if the current tweet is the last one, grab it after the processing has occured.
                     * And return it in the success message so the js that generates the management table
                     * can us it to update the table with the new status. */
                    if(isset($fields['field_59af60df2fbb0']) && $fields['field_59af60df2fbb0'] == true){
                        /* also set the time that the schedule was paused */
                        $fields['field_59af60df2fbb0']['schedule_paused_on'] = date_i18n('F j, Y g:i a', current_time('timestamp'));

                        /* set the schedule "Schedule paused" status message */
                        $fields['field_59af60df2fbb0']['tweet_status_message']   = __('Tweet Status: Tweet Has Been Paused.', 'tweet-boost' );

                        /* set the displayed state of processing to "Schedule paused on:" */
                        $processing_tense = __('Tweet Paused On: ', 'tweet-boost' );

                        /* get the last tweet status to update the schedule management widget status viewer */
                        $play_pause_status = $fields['field_59af60df2fbb0'];
                    }
                }
            }elseif(empty($fields)){
            /* if there aren't any tweets, clear the pause data */
                self::update_tweet_boost_post_settings($post_id, 'schedule-pause-data', '', true);
            }

            /* update the stored tweet data */
            self::update_tweet_boost_acf_fields($fields, $post_id);

            /* send the user a message saying that the mission was a success and send some data to update the display */
            wp_send_json(array( 'success' => sprintf(__('The tweet has been successfully %s!', 'tweet-boost' ), $play_pause_string['plural'][$schedule_status]),
                                'title' => sprintf(__('Tweet %s Successfully', 'tweet-boost' ), $play_pause_string['uc_plural'][$schedule_status]),
                                'tweet_schedule_status' => $play_pause_status,
                                'dont_process_tweet_count' => $tweet_counter,
                                'processing_tense' => $processing_tense));
        }

    }

    new Tweet_Boost_Utilities;
}

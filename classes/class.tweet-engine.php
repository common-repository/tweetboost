<?php
/**
 * Tweet_Boost_Tweet_Engine
 */

if(!class_exists('Tweet_Boost_Tweet_Engine')){


    class Tweet_Boost_Tweet_Engine{

        static $tweet_error_limit; /* classwide variable for storing user defined setting */
        static $connection_error_limit = 15;
        static $tweet_boost; /* global settings and account data */

        /**
         * Tweet_Boost_Tweet_Engine constructor.
         */
        public function __construct(){
            self::load_hooks();
            /* if the codebird twitter api isn't already included, include it */
            if(!class_exists('Codebird')){
                require(TWEET_BOOST_PLUGIN_PATH . '/assets/includes/codebird-php/src/codebird.php');
            }

        }

         /**
         *
         */
        public static function load_hooks() {
            /* checks for tweets every 2 mins. If there are tweets, it starts the fantastic process of making a tweet */
            add_action('tweet_boost_heartbeat', array(__CLASS__, 'check_for_scheduled_tweets'));

        }


        /**
         * Queries the DB to see if there are any tweets to be made upto 30 seconds in the future
         * If there are tweets, then it calls process_scheduled_tweets with an array of post ids that the tweets belong to
         */
        public static function check_for_scheduled_tweets(){
            global $wpdb;

            $meta_table = $wpdb->prefix . 'postmeta';
            $posts_table = $wpdb->prefix . 'posts';

            /* get the current time + 30 seconds */
            $current_time = date('Y-m-d H:i:s', current_time('timestamp') + 30);

            /* get the time 30 days ago to limit how far back the tweets will be queried */
            $past_time = date('Y-m-d H:i:s', current_time('timestamp') - 2592000);

			$query = "SELECT DISTINCT(`ID`) AS 'ID' FROM " . $meta_table . " JOIN " . $posts_table . " WHERE `meta_key` = 'tweet_time' AND TRIM(`meta_value`) != 'a:0:{}' AND `post_type` = 'post' AND `post_id` = `ID` AND `meta_value` > '" . $past_time . "' ORDER BY `ID` DESC";

            $tweeting_posts = $wpdb->get_results($query);

            /* if there are posts that have tweets to be made */
            if(isset($tweeting_posts) && !empty($tweeting_posts)){
                /* call process_scheduled_tweets with the array of tweeting post ids */
                self::process_scheduled_tweets($tweeting_posts);
            }
        }

        /**
         * Processes stored tweets and coordinates the making of tweets
         * @param array $tweeting_posts (an array of post ids)
         */
        public static function process_scheduled_tweets($tweeting_posts ){

            /* if there aren't any tweets to process, exit */
            if(empty($tweeting_posts)){
                return;
            }


            /* get tweet-boost settings */
            self::$tweet_boost = Tweet_Boost_Utilities::get_tweet_boost_settings();

            /* load user defined setting */
            self::$tweet_error_limit = self::$tweet_boost['failed_attempt_limit'];

            /* get the stored twitter account data */
            $twitter_account_data = Tweet_Boost_Utilities::get_twitter_account( self::$tweet_boost, true );

            /* loop through the posts that have tweets scheduled to go out */
            foreach($tweeting_posts as $post_data){

                /* get the stored tweet */
                $tweet = Tweet_Boost_Utilities::get_tweet_boost_acf_fields($post_data->ID);

                /* if for some reason there isn't a tweet, continue */
                if(empty($tweet)){
                    continue;
                }

                /* if the tweet isn't supposed to be processed, skip to the next one */
                if( isset($tweet['field_59af60df2fbb0']['stop_processing']) &&
                    $tweet['field_59af60df2fbb0']['stop_processing'] == true)
                {
                    continue;
                }

                /* assemble the tweet data object */
                $tweet_data = array(
                    'tweet_date'          => $tweet['field_598906629db22'],
                    'tweet_photo_id'      => $tweet['field_598907a39db24'],
                    'tweet_content'       => $tweet['field_598906cf9db23'],
                    'twitter_account'     => $twitter_account_data[0]['username'],
                    'twitter_access_data' => $twitter_account_data[0],
                    'tweeting_post_id'    => $post_data->ID
                );

                /* get the current time for comparative purposes */
                $current_time = current_time('timestamp');
                /* get the tweet time for comparative purposes */
                $tweet_time = strtotime($tweet_data['tweet_date']);

                /* if the current time (plus +30 seconds) is later than the time that the tweet is to be made.
                 * And if the tweet isn't supposed to go out any later than 30 days ago */
                if( ($current_time + 30) >= $tweet_time && $tweet_time >= ($current_time - 2592000) ){

                    /* make the tweet */
                    $status = self::send_scheduled_tweet($tweet_data);

                    /* handle the result of the tweet and update the fields accordingly */
                    $tweet = self::handle_tweet_status($status, $tweet, $post_data->ID);

                    /* update the tweet data with with a results of trying to make the tweet */
                    Tweet_Boost_Utilities::update_tweet_boost_acf_fields($tweet, $post_data->ID, $field_id);

                    /* if tweet is being tweeted manually by process_tweet_manuall() then return API response status */
                    if (isset($post_data->row_number)) {
                        return $status;
                    }
                }
            }
        }

        /**
         * Makes tweets based on stored data
         * @param array $tweet_data
         **/
        public static function send_scheduled_tweet($tweet_data) {

            \Codebird\Codebird::setConsumerKey($tweet_data['twitter_access_data']['consumer_key'], $tweet_data['twitter_access_data']['consumer_secret']);

            $cb = \Codebird\Codebird::getInstance();
            $cb->setToken($tweet_data['twitter_access_data']['access_token'], $tweet_data['twitter_access_data']['access_token_secret']);

            /* create the array of arguments that are going to be sent to Twitter */
            $tweet_args = array();

            /* if there is text content in the tweet */
            if(isset($tweet_data['tweet_content']) && !empty($tweet_data['tweet_content'])){
                /* check for post link tokens in the content */
                $post_link_token = strpos($tweet_data['tweet_content'], '{post-link}');
                /* if there's any post-link tokens in the content */
                if($post_link_token !== false){
                    /* get the post link */
                    $post_link = get_permalink($tweet_data['tweeting_post_id']);
                    /* replace all post link tokens with the actual post links */
                    $tweet_data['tweet_content'] = str_replace('{post-link}', $post_link, $tweet_data['tweet_content']);
                }

                /* add the content to the tweet args */
                $tweet_args['status'] = $tweet_data['tweet_content'];
            }

            /* if there is an image id in the tweet */
            if(isset($tweet_data['tweet_photo_id']) && !empty($tweet_data['tweet_photo_id'])){
                /* try to get the image's url */
                $tweet_image_url = wp_get_attachment_url($tweet_data['tweet_photo_id']);
                /* if there is an image */
                if($tweet_image_url){
                    /* upload it to twitter and get a response object */
                    try{
                        $reply = $cb->media_upload(array('media' => $tweet_image_url));
                    }catch(Exception $e){
                        /* if there is an error, log it for the moment */
                        error_log('sent from a catch in tweet-engine.php on line 108. It had to do with using an image as the tweet image');
                        error_log($e);
                        error_log(print_r($tweet_data, true));
                    }
                    if(isset($reply) && !empty($reply) && !isset($reply->error)){
                        /* add the image id to the tweet args */
                        $tweet_args['media_ids'] = $reply->media_id_string;
                    }
                }
            }elseif(self::$tweet_boost['use_featured_image_in_tweet']){
            /* if there isn't an image set, but TweetBoost is set to use the featured image as the twitter pic */
                /* try to get the featured image's id */
                $featured_image_id = get_post_thumbnail_id($tweet_data['tweeting_post_id']);
                /* if there is a featured image */
                if(!empty($featured_image_id)){
                    /* try to upload it to twitter and get a response object */
                    try{
                        $reply = $cb->media_upload(array('media' => trim(wp_get_attachment_url($featured_image_id))));
                    }catch(Exception $e){
                        /* if there is an error, log it for the moment */
                        error_log('sent from a catch in tweet-engine.php on line 200. It had to do with using the featured image as the tweet image');
                        error_log($e);
                        error_log(print_r($tweet_data, true));
                    }
                    /* if contact has been made and there are no errors */
                    if(isset($reply) && !empty($reply) && !isset($reply->error)){
                        /* add the image id to the tweet args */
                        $tweet_args['media_ids'] = $reply->media_id_string;
                    }
                }
            }

            /* attempt to make the tweet */
            try{
                $status = $cb->statuses_update($tweet_args);
            }catch(Exception $e){
                error_log('sent from a catch in tweet-engine.php on line 216. It had to do with attempting to make the tweet');
                error_log($e);
                error_log(print_r($tweet_data, true));
            }

            /* if the call got a response */
            if(isset($status) && !empty($status)){
                /* return the status */
                return $status;
            }else{

                /* if the call didn't get a response, return false */
                return false;
            }
        }

        /**
         * Handles the creation of tweet response messages and handles the setting of error data
         * @param object $status (the twitter tweet status object)
         * @param array $tweet (the stored tweet field data)
         * @param int $field_id (the tweet row id that the tweet belongs to)
         * $param int $post_id (the id of the post that the tweet belongs to)
         **/
        public static function handle_tweet_status($status, $tweet, $post_id){

            /* if the status isn't set, set it to false */
            if(!isset($status)){
                $status = false;
            }

            /* decode the stored field data */
            if(is_string($tweet['field_59af60df2fbb0'])){

                $status_data = json_decode($tweet['field_59af60df2fbb0'], true);

                /* if there is stored data, set the status index for the decoded data */
                if(is_array($status_data) && $status_data !== null && $status_data !== false){
                    $tweet['field_59af60df2fbb0'] = $status_data;
                }else{
                /* if there is no stored data, set the data index to an empty array */
                    $tweet['field_59af60df2fbb0'] = array();
                }
            }

            /* if there is an error code returned from twitter */
            if(isset($status->errors[0]->code) && !empty($status->errors[0]->code)){
                /* set the twitter_error_code to that */
                $twitter_error_code = $status->errors[0]->code;
            }else{
            /* if there isn't, set the twitter_error_code to no_twitter_code.
             * This is so handle_tweet_status_data can process errors that only return http codes */
                $twitter_error_code = 'no_twitter_code';
            }

            if($status->httpstatus == 200){
            /** if the tweet has been made successfully **/

                /* set the displayed message to success! */
                $tweet['field_59af60df2fbb0']['displayed_message'] = __('Tweeted! :)', 'tweet-boost' );
                $tweet['field_59af60df2fbb0'][$status->httpstatus] = true;

                /* set the tweet to not process */
                $tweet['field_59af60df2fbb0']['stop_processing'] = true;

                /* give the reason why not to process the tweet */
                $tweet['field_59af60df2fbb0']['stop_processing_reason'] = 'success';

                /* set the time that the tweet was made */
                $tweet['field_59af60df2fbb0']['tweet_made_at'] = current_time('timestamp');

                /* unset/reset tweet-not-sent flag */
                unset($tweet['field_59af60df2fbb0']['tweet-not-sent']);

            }elseif($status === false){
            /** if the error is false. Most likely meaning that TweetBoost couldn't connect to twitter **/
                /* check to see if cURL is active */
                $curl_active = function_exists('curl_version');
                $tweet['field_59af60df2fbb0']['tweet-not-sent'] = (!isset($tweet['field_59af60df2fbb0']['tweet-not-sent'])) ? 1 : $tweet['field_59af60df2fbb0']['tweet-not-sent'] + 1 ;

                /* if this is the first time the tweet has failed to send, cURL is active */
                if(!isset($tweet['field_59af60df2fbb0']['tweet-not-sent']) && $curl_active){
                    $tweet['field_59af60df2fbb0']['displayed_message'] = __('The tweet hasn\'t been made, TweetBoost couldn\'t make contact with Twitter.', 'tweet-boost' );
                }elseif(isset($tweet['field_59af60df2fbb0']['tweet-not-sent']) && $tweet['field_59af60df2fbb0']['tweet-not-sent'] < 15 && $curl_active){
                /* if this error has occured before, its happened fewer times than the limit, and cURL is active: Increase the number of times it's happened */
                    $tweet['field_59af60df2fbb0']['displayed_message'] = __('The tweet hasn\'t been made, TweetBoost couldn\'t make contact with Twitter. Most likely this happend because there were too many tweets being made at once and the php execution time limit was exceeded. Increasing the limit should help. If you don\'t know how, you can ask your site host to increase it for you. TweetBoost will attempt to make the tweet again in a few minutes. If this error continues, or effects more than a few tweets here and there, there could be some problem with the network settings that\'s keeping TweetBoost from making contact with Twitter.', 'tweet-boost' );
                }else{

                    /* if cURL isn't active */
                    if($curl_active == false){
                        /* create the message to the user */
                        $tweet['field_59af60df2fbb0']['displayed_message'] = __('The tweet couldn\'t be made, TweetBoost couldn\'t make contact with Twitter. It seems a php module called cURL isn\'t enabled for your web site. Please ask your web host to enable it for you so TweetBoost can make tweets. When it\'s enabled, please reset the Tweet Time to have TweetBoost make the tweet.', 'tweet-boost' );

                        /* set the tweet to not process */
                        $tweet['field_59af60df2fbb0']['stop_processing'] = true;

                        /* give the reason why not to process the tweet */
                        $tweet['field_59af60df2fbb0']['stop_processing_reason'] = 'no_curl_error';
                    } else {
                        /* create the message to the user */
                        $tweet['field_59af60df2fbb0']['displayed_message'] = __('The tweet couldn\'t be made, TweetBoost couldn\'t make contact with Twitter. This error has happened a number of times, so there might be a network setting keeping TweetBoost from making contact with Twitter. The PHP error logs might have some information about what\'s going wrong. In the mean time TweetBoost will stop trying to make the tweet until the error is fixed. When it is, just click the Reset Tweet Status button or change the tweet time to have TweetBoost try again.', 'tweet-boost' );

                        /* set the tweet to not process */
                        $tweet['field_59af60df2fbb0']['stop_processing'] = true;

                        /* give the reason why not to process the tweet */
                        $tweet['field_59af60df2fbb0']['stop_processing_reason'] = 'no_connect_error';
                    }
                }


            }elseif($status->httpstatus >= 400 && $status->httpstatus < 500){
            /** if the error is of the 400 class **/

                /* set the tweet fail flag */
                $tweet['field_59af60df2fbb0']['tweet-not-sent'] = (!isset($tweet['field_59af60df2fbb0']['tweet-not-sent'])) ? 1 : $tweet['field_59af60df2fbb0']['tweet-not-sent'] + 1 ;

                /* if this twitter error hasn't happend yet */
                if(!isset($tweet['field_59af60df2fbb0'][$status->httpstatus][$twitter_error_code])){

                    /* put together the data about the twitter error */
                    $args = array('tweet' => $tweet, 'status' => $status, 'error_exists' => false, 'is_4xx_error' => true, 'limit_reached' => false);
                    /* update the tweet field with the twitter error data */
                    $tweet = self::handle_tweet_status_data($args);

                }elseif(isset($tweet['field_59af60df2fbb0'][$status->httpstatus][$twitter_error_code]) && intval($tweet['field_59af60df2fbb0'][$status->httpstatus][$twitter_error_code]) < self::$tweet_error_limit){
                /* if the error has occured before but it's fewer than the limit, increase the count and allow the tweet to be processed later */

                    /* put together the data about the twitter error */
                    $args = array('tweet' => $tweet, 'status' => $status, 'error_exists' => true, 'is_4xx_error' => true, 'limit_reached' => false);
                    /* update the tweet field with the twitter error data */
                    $tweet = self::handle_tweet_status_data($args);

                }else{
                /* if the tweet has passed the try limit or twitter didn't return an error object like we were expecting, stop processing the tweet */

                    /* put together the data about the twitter error */
                    $args = array('tweet' => $tweet, 'status' => $status, 'error_exists' => true, 'is_4xx_error' => true, 'limit_reached' => true);
                    /* update the tweet field with the twitter error data */
                    $tweet = self::handle_tweet_status_data($args);
                }

            }elseif($status->httpstatus >= 500 && $status->httpstatus < 600){
            /** if the error is of the 500 class **/
                $tweet['field_59af60df2fbb0']['tweet-not-sent'] = (!isset($tweet['field_59af60df2fbb0']['tweet-not-sent'])) ? 1 : $tweet['field_59af60df2fbb0']['tweet-not-sent'] + 1 ;

                /* if this twitter error hasn't happend yet */
                if(!isset($tweet['field_59af60df2fbb0'][$status->httpstatus][$twitter_error_code])){

                    /* put together the data about the twitter error */
                    $args = array('tweet' => $tweet, 'status' => $status, 'error_exists' => false, 'is_4xx_error' => false, 'limit_reached' => false);
                    /* update the tweet field with the twitter error data */
                    $tweet = self::handle_tweet_status_data($args);

                }elseif(isset($tweet['field_59af60df2fbb0'][$status->httpstatus][$twitter_error_code]) && $tweet['field_59af60df2fbb0'][$status->httpstatus][$twitter_error_code] < self::$connection_error_limit){
                /* if the error has occured before but it's fewer than the limit, increase the count and allow the tweet to be processed later */

                    /* put together the data about the twitter error */
                    $args = array('tweet' => $tweet, 'status' => $status, 'error_exists' => true, 'is_4xx_error' => false, 'limit_reached' => false);
                    /* update the tweet field with the twitter error data */
                    $tweet = self::handle_tweet_status_data($args);

                }else{
                /* if the tweet has passed the try limit or twitter didn't return an error object like we were expecting, stop processing the tweet */

                    /* put together the data about the twitter error */
                    $args = array('tweet' => $tweet, 'status' => $status, 'error_exists' => true, 'is_4xx_error' => false, 'limit_reached' => true);
                    /* update the tweet field with the twitter error data */
                    $tweet = self::handle_tweet_status_data($args);
                }

            }else{
                /* catch what's left */
            }

            /* set the twitter response */
            $tweet['field_59af60df2fbb0']['twitter_response'] = $status;

            /* set the tweet http status code */
            $tweet['field_59af60df2fbb0']['http_status_code'] = $status->httpstatus;

            /* update the the status log */
            Tweet_Boost_Utilities::update_tweet_boost_status_log($tweet, $post_id);

            /* return the updated fields */
            return $tweet;

        }

        /**
         * Updates the tweet status data field with twitter error data
         * @param array $status_args (the array of data field and twitter error data)
         **/
        public static function handle_tweet_status_data($status_args = null){
            /* set the default values */
            $defaults = array(
                'tweet'   => array(),
                'status'   => null,
                'error_exists' => false,
                'is_4xx_error' => false,
                'limit_reached' => false,
                'response' => $status_args
            );
            /* merge the given args with the defaults */
            $status_args = wp_parse_args($status_args, $defaults);

            /* assemble the varibles from the merged arrays */
            $tweet = $status_args['tweet'];
            $status = $status_args['status'];
            $error_exists = $status_args['error_exists'];
            $is_4xx_error = $status_args['is_4xx_error'];
            $limit_reached = $status_args['limit_reached'];

            /* if there isn't a twitter error code */
            if(!isset($status->errors[0]->code) || empty($status->errors[0]->code)){
                /* if this error hasn't happend yet */
                if($error_exists == false){

                    /* set the error message for the admin */
                    $tweet['field_59af60df2fbb0']['displayed_message'] = self::generate_tweet_status_message(null, null, $status->httpstatus, $is_4xx_error);

                    /* setup the status code array */
                    $tweet['field_59af60df2fbb0'][$status->httpstatus] = array();

                    /* count this as the first time the error has shown up */
                    $tweet['field_59af60df2fbb0'][$status->httpstatus]['no_twitter_code'] = 1;
                    $tweet['field_59af60df2fbb0'][$status->httpstatus]['standard'] = 1;

                }elseif($error_exists == true && $limit_reached == false){
                /* if the error has occured before but it's fewer than the limit, increase the count and allow the tweet to be processed later */

                    /* set the error message for the admin */
                    $tweet['field_59af60df2fbb0']['displayed_message'] = self::generate_tweet_status_message(null, null, $status->httpstatus, $is_4xx_error);

                    /* increase the count of how many times this error has happend */
                    $tweet['field_59af60df2fbb0'][$status->httpstatus]['no_twitter_code'] += 1;
                    $tweet['field_59af60df2fbb0'][$status->httpstatus]['standard'] += 1;

                }else{
                /* if the tweet has passed the try limit or twitter didn't return an error object like we were expecting, stop processing the tweet */

                    /* set the error message for the admin */
                    $tweet['field_59af60df2fbb0']['displayed_message'] = self::generate_tweet_status_message(null, null, $status->httpstatus, $is_4xx_error, true);

                    /* set the tweet to not process */
                    $tweet['field_59af60df2fbb0']['stop_processing'] = true;

                    /* give the reason why not to process the tweet */
                    if($is_4xx_error){
                        $tweet['field_59af60df2fbb0']['stop_processing_reason'] = '400_error';
                    }else{
                        $tweet['field_59af60df2fbb0']['stop_processing_reason'] = '500_error';
                    }
                }
                /* return the updated tweet */
                return $tweet;
            }

            /* if this twitter error hasn't happend yet */
            if($error_exists == false){

                /* set the error message for the admin */
                $tweet['field_59af60df2fbb0']['displayed_message'] = self::generate_tweet_status_message($status->errors[0]->code, $status->errors[0]->message, $status->httpstatus, $is_4xx_error);

                /* setup the status code array */
                $tweet['field_59af60df2fbb0'][$status->httpstatus] = array();

                /* count this as the first time the error has shown up */
                $tweet['field_59af60df2fbb0'][$status->httpstatus][$status->errors[0]->code] = 1;
                $tweet['field_59af60df2fbb0'][$status->httpstatus]['standard'] = 1;

            }elseif($error_exists == true && $limit_reached == false){
            /* if the error has occured before but it's fewer than the limit, increase the count and allow the tweet to be processed later */

                /* set the error message for the admin */
                $tweet['field_59af60df2fbb0']['displayed_message'] = self::generate_tweet_status_message($status->errors[0]->code, $status->errors[0]->message, $status->httpstatus, $is_4xx_error);

                /* increase the count of how many times this error has happend */
                $tweet['field_59af60df2fbb0'][$status->httpstatus][$status->errors[0]->code] += 1;
                $tweet['field_59af60df2fbb0'][$status->httpstatus]['standard'] += 1;

            }else{
            /* if the tweet has passed the try limit or twitter didn't return an error object like we were expecting, stop processing the tweet */

                /* set the error message for the admin */
                $tweet['field_59af60df2fbb0']['displayed_message'] = self::generate_tweet_status_message($status->errors[0]->code, $status->errors[0]->message, $status->httpstatus, $is_4xx_error, true);

                /* set the tweet to not process */
                $tweet['field_59af60df2fbb0']['stop_processing'] = true;

                /* give the reason why not to process the tweet */
                if($is_4xx_error){
                    $tweet['field_59af60df2fbb0']['stop_processing_reason'] = '400_error';
                }else{
                    $tweet['field_59af60df2fbb0']['stop_processing_reason'] = '500_error';
                }
            }

            /* return the processed tweet */
            return $tweet;

        }

        /**
         * Generates a message for the Tweet Status message area
         * @param int $error_code (the twitter api error code)
         * @param string $error_message (the twitter api error message)
         * @param int $http_response_code (the http response code from the twitter api)
         * @param bool $is_4xx_error (whether or not the error is of the 400 class)
         * @param bool $stop_processing (whether this is the final process or the tweet error)
         **/
        public static function generate_tweet_status_message($error_code, $error_message = false, $http_response_code, $is_4xx_error = false, $stop_processing = false){

            $displayed_message = '';

            /* this is an array of suggestions based on the error code twitter returns */
            $error_code_array = array(
                'unknown'  => '',
                '32'  => __('This means either there was a typo when entering the Twitter App\'s credentials (the API Key, API Secret, App Access Token or App Access Token Secret). Or the credentials aren\'t valid. Please try re-entering the credentials into the TweetBoost settings. If that doesn\'t solve it, please try regenerating the Twitter App\'s credentials and enter them into the TweetBoost settings. Note: Regenerating the credentials will invalidate the current ones. So if you have another product using the App\'s current credentials, you\'ll need to enter the new credentials into that product as well.', 'tweet-boost' ),
                '50'  => __('This means Twitter can\'t find the account we\'re trying to tweet with. Most likely this is from a mistake when entering the App\'s keys into the TweetBoost settings. Please try re-entering the Twitter App\'s keys and secrets into the TweetBoost settings.', 'tweet-boost' ),
                '63'  => __('This should only show up when data is requested, not when tweets are made. Nevertheless, it seems that this account has been suspended. Most likely, there\'s a notification about this suspension in your Twitter Apps\'s dashboard.', 'tweet-boost' ),
                '64'  => __('This means the Twitter account is suspended. Twitter has probably sent an email about this, and there may be a notification in your Twitter account\'s dashboard. If the account was suspended but is now unsuspended, try regenerating the App\'s access keys and tokens and enter the new ones into the TweetBoost settings. Note: Regenerating the credentials will invalidate the current ones. So if you have another product using the App\'s current credentials, you\'ll need to enter the new credentials into that product as well.', 'tweet-boost' ),
                '87'  => __('Most likely this means the Twitter App doesn\'t have read and write permissions. Try going to your App\'s Permissions page and set it \"Read and Write\". After doing this, you\'ll need to regenerate the App\'s access tokens and enter the new ones into the TweetBoost settings. Note: Regenerating the credentials will invalidate the current ones. So if you have another product using the App\'s current credentials, you\'ll need to enter the new credentials into that product as well.', 'tweet-boost' ),
                '88'  => __('This means that too many tweets have been made by this account in a short amount of time. Try spacing them a little further apart.', 'tweet-boost' ),
                '89'  => __('This means either a token was entered incorrectly or one of them have expired. Try going to your Twitter App\'s Keys and Access Tokens page, and copy and paste each token into the TweetBoost settings. Each setting input is named exactly like the token that should go in it. If that doesn\'t work, you may have to regenerate all of the app\'s tokens and enter the regenerated tokens in the settings. Note: Regenerating the credentials will invalidate the current ones. So if you have another product using the App\'s current credentials, you\'ll need to enter the new credentials into that product as well.', 'tweet-boost' ),
                '99'  => __('This means Twitter could not verify the App\'s access tokens. Try going to your App\'s Keys and Access Tokens page and regenerating the access tokens. Then enter the new tokens into the TweetBoost settings. Note: Regenerating the credentials will invalidate the current ones. So if you have another product using the App\'s current credentials, you\'ll need to enter the new credentials into that product as well.', 'tweet-boost' ),
                '130' => __('This means there\'s too much traffic on Twitter\'s servers to make the tweet. TweetBoost will try tweeting in a few minutes.', 'tweet-boost' ),
                '131' => __('This means the Twitter servers had an internal error. TweetBoost will try tweeting again in a few minutes.', 'tweet-boost' ),
                '135' => __('This means your webserver\'s clock is more than 30 seconds different from the UTC current time. Your web host may be able to fix this, please contact them for more information.', 'tweet-boost' ),
                '170' => __('This means that there wasn\'t any text, or an image in the tweet.', 'tweet-boost' ),
                '185' => __('This means too many tweets have been made in a short period of time. The error message says the daily limit has been reached, but this should be a placeholder. It\'s more likely tweeting has been paused for less than an hour.', 'tweet-boost' ),
                '187' => __('This means a tweet with the same content as this one has been made fairly recently. Try changing the content a bit to get around this.', 'tweet-boost' ),
                '215' => __('This means there\'s an error with the App\'s keys and access tokens. Please try re-entering the Twitter App\'s keys and secrets into the TweetBoost settings. If that doesn\'t solve it, please try regenerating the Consumer Key, Consumer Secret and the Access Tokens. Then enter them in the TweetBoost settings. Note: Regenerating the credentials will invalidate the current ones. So if you have another product using the App\'s current credentials, you\'ll need to enter the new credentials into that product as well.', 'tweet-boost' ),
                '220' => __('This means the Twitter App is resticted in some way and can\'t make the tweet. Check to make sure the App has \"Read and Write\" permissions. If it doesn\'t, please enable \"Read and Write\" permissions. After doing this, you will need to regenerate the app\'s credentials and re-enter them in the TweetBoost settings for the new permissions to take effect. Note: Regenerating the credentials will invalidate the current ones. So if you have another product using the App\'s current credentials, you\'ll need to enter the new credentials into that product as well.', 'tweet-boost' ),
                '226' => __('This means Twitter thinks this tweet, or maybe the account, is generating spam. You can contact Twitter about this by going to https://help.twitter.com/forms/platform and filling out a ticket.', 'tweet-boost' ),
                '261' => __('This means the App has been restricted or suspended by Twitter. There may be a notification about this from Twitter in your app\'s dashboard. You can also open a support ticket with Twitter here: https://help.twitter.com/forms/platform ', 'tweet-boost' ),
                '323' => __('This means there\'s more than one gif in this tweet.', 'tweet-boost' ),
                '324' => __('This means there was an error with the tweet\'s image. Try using a different image, or re-upload the image to your site and use that one in the tweet.', 'tweet-boost' ),
                '325' => __('This means Twitter couldn\'t find the image for the tweet. Try using a different image or re-upload the image to your site and try using that one.', 'tweet-boost' ),
                '326' => __('This means Twitter has flagged this account as possibly being a spam account, you should be able to fix this by logging into the Twitter account and answering any notifications Twitter has sent.', 'tweet-boost' ),
                '354' => __('This means for some reason the charactor limit didn\'t kick in. Please try shortening the tweet a little.', 'tweet-boost' ),
                '386' => __('This means the tweet has more than one type of non text content in it. Tweets can have images, or gifs, or videos in them, but only one of these content types can be in a tweet at once.', 'tweet-boost' )// though TweetBoost should only allow single images in tweets...
            );

            $http_error_array = array(
                '400' => __('the HTTP error code for this is 400', 'tweet-boost' ),
                '401' => __('the HTTP error code for this is 401', 'tweet-boost' ),
                '403' => __('the HTTP error code for this is 403', 'tweet-boost' ),
                '404' => __('the HTTP error code for this is 404', 'tweet-boost' ),
                '406' => __('the HTTP error code for this is 406', 'tweet-boost' ),
                '410' => __('the HTTP error code for this is 410', 'tweet-boost' ),
                '422' => __('the HTTP error code for this is 422', 'tweet-boost' ),
                '429' => __('the HTTP error code for this is 429', 'tweet-boost' ),
                '500' => __('the HTTP error code for this is 500', 'tweet-boost' ),
                '502' => __('the HTTP error code for this is 502', 'tweet-boost' ),
                '503' => __('the HTTP error code for this is 503', 'tweet-boost' ),
                '504' => __('the HTTP error code for this is 504', 'tweet-boost' )
            );

            /* if the error is of the 4xx class of errors */
            if($is_4xx_error){

                /* if the twitter error code is supplied */
                if(isset($error_code) && !empty($error_code)){

                    /* if the twitter error code isn't recognized, set it to 'unknown' to select the placeholder message */
                    if(!isset($error_code_array[$error_code])){
                        $error_code = 'unknown';
                    }

                    /* if an error message is supplied */
                    if($error_message){

                        /* if the max number of attempts to make the tweet has been made,
                         * and the tweet is to not be processed.
                         * Create a message telling the user that the tweet won't be processed until the error is resolved */
                        if($stop_processing){
                            $displayed_message = sprintf(__('There was an error when trying to make the tweet. The error message was: "%s" %s This error has happened a number of times, so TweetBoost will stop processing the tweet until the error is fixed. When it is, you can click the Reset Tweet Status button or change the Tweet Time to try making the tweet again.', 'tweet-boost' ), $error_message, $error_code_array[$error_code]);
                        }else{
                        /* if the tweet hasn't reached the max error count, create an error message telling the user about the error */
                            $displayed_message = sprintf(__('There was an error when trying to make the tweet. The error message was: "%s" %s TweetBoost will try to make the tweet again in a few minutes.', 'tweet-boost' ), $error_message, $error_code_array[$error_code]);
                        }

                    }else{
                    /* if no error message is supplied by twitter */

                        /* if the max number of attempts to make the tweet has been made,
                         * and the tweet is to not be processed.
                         * Create a message telling the user that the tweet won't be processed until the error is resolved */
                        if($stop_processing){
                            $displayed_message = sprinf(__('There was an error when trying to make the tweet. There wasn\'t a message attached to it, but %s. And the Twitter API error code was %s. %s This error has happened a number of times, so TweetBoost will stop processing the tweet until the error is fixed. When it is, you can click the Reset Tweet Status button or change the Tweet Time to try making the tweet again.', 'tweet-boost' ), $http_error_array[$http_response_code], $error_code, $error_code_array[$error_code]);
                        }else{
                        /* if the tweet hasn't reached the max error count, create an error message telling the user about the error */
                            $displayed_message = sprinf(__('There was an error when trying to make the tweet. There wasn\'t a message attached to it, but %s. And the Twitter API error code was %s. %s TweetBoost will try to make the tweet again in a few minutes.', 'tweet-boost' ), $http_error_array[$http_response_code], $error_code, $error_code_array[$error_code]);
                        }
                    }

                    /* return the error message */
                    return $displayed_message;

                }else{
                /* if the twitter error code isn't supplied, return a message based on the http response code */

                    return sprintf(__('There was an error when trying to make the tweet. There wasn\'t a message attached to it, but %s.', 'tweet-boost' ), $http_error_array[$http_response_code]);
                }
            }else{
            /* if the class isn't of the 4xx class of errors, it must be of the 5xx class */

                /* if the twitter error code is supplied */
                if(isset($error_code) && !empty($error_code)){

                    /* if the twitter error code isn't recognized, set it to 'unknown' to select the placeholder message */
                    if(!isset($error_code_array[$error_code])){
                        $error_code = 'unknown';
                    }

                    /* if an error message is supplied */
                    if($error_message){
                        /* if the max number of attempts to make the tweet has been made,
                         * and the tweet is to not be processed.
                         * Create a message telling the user that the tweet won't be processed until the error is resolved */
                        if($stop_processing){
                            /* create an error message to tell the user that the twitter servers had an error */
                            $displayed_message = sprintf(__('The Twitter servers had an error when trying to make the tweet. The error message was %s. %s Since this is an error with the Twitter servers, this error should be resolved soon. This error has happened a number of times, so TweetBoost will stop processing the tweet until the error is fixed. When it is, you can click the Reset Tweet Status button or change the Tweet Time to try making the tweet again.', 'tweet-boost' ), $error_message, $error_code_array[$error_code]);
                        }else{
                            $displayed_message = sprintf(__('The Twitter servers had an error when trying to make the tweet. The error message was %s. %s Since this is an error with the Twitter servers, this error should be resolved soon. TweetBoost will try to make the tweet again in a few minutes', 'tweet-boost' ), $error_message, $error_code_array[$error_code]);
                        }
                    }else{
                        /* if the max number of attempts to make the tweet has been made,
                         * and the tweet is to not be processed.
                         * Create a message telling the user that the tweet won't be processed until the error is resolved */
                        if($stop_processing){
                            $displayed_message = sprintf(__('The Twitter servers had an error when trying to make the tweet. There wasn\'t a message attached to it, but %s. And the Twitter API error code was %s. %s Since this is an error with the Twitter servers, this error should be resolved soon. This error has happened a number of times, so TweetBoost will stop processing the tweet until the error is fixed. When it is, you can click the Reset Tweet Status button or change the Tweet Time to try making the tweet again.', 'tweet-boost' ), $http_error_array[$http_response_code], $error_code, $error_code_array[$error_code]);
                        }else{
                        /* if there is no error message supplied, create an error message telling the user about the twitter server error with a bit of data about the twitter API error code*/
                            $displayed_message = sprintf(__('The Twitter servers had an error when trying to make the tweet. There wasn\'t a message attached to it, but %s. And the Twitter API error code was %s. %s Since this is an error with the Twitter servers, this error should be resolved soon. TweetBoost will try to make the tweet again in a few minutes.', 'tweet-boost' ), $http_error_array[$http_response_code], $error_code, $error_code_array[$error_code]);
                        }
                    }

                    /* return the error message */
                    return $displayed_message;

                }else{
                /* if the twitter error code isn't supplied, return a message based on the http response code */
                    return sprintf(__('The Twitter servers had an error when trying to make the tweet. There wasn\'t a message attached to it, but %s. Since this is an error with the Twitter servers, this error should be resolved soon.', 'tweet-boost' ), $http_error_array[$http_response_code]);
                }
            }
        }
    }

    new Tweet_Boost_Tweet_Engine;

}

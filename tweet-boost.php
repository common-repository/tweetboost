<?php
/**
 * Plugin Name: TweetBoost Free - Simple Tweet Scheduler For WordPress
 * Plugin URI:	https://wptweetboost.com
 * Description: The TweetBoost Free plugin provides an easy way to create and schedule a Tweet when editing your content.
 * Author:		Matt Bissett, Hudson Atwell
 * Author URI:	https://codeable.io/developers/matt-bissett/?ref=76T5o
 * Version:		1.1.0
 * Text Domain: tweet-boost
 * Domain Path: /assets/lang/
 */



if(!class_exists('Tweet_Boost_Tweet_Scheduler')){

    class Tweet_Boost_Tweet_Scheduler{

        /**
         * Create the TweetBoost object
         **/
        public function __construct(){
            self::load_constants();
            self::load_plugins();
            self::load_classes();
            add_action( 'plugins_loaded', array( __CLASS__ , 'load_text_domain') );
        }

        /**
         * Loads the TweetBoost constants
         **/
        public static function load_constants(){
            define('TWEET_BOOST_PLUGIN_VERSION', '1.1.0');
            define('TWEET_BOOST_PLUGIN_URLPATH', plugin_dir_url(__FILE__));
            define('TWEET_BOOST_PLUGIN_PATH', plugin_dir_path(__FILE__));
            define('TWEET_BOOST_STORE_URL', 'https://wptweetboost.com' );
            define('TWEET_BOOST_SETTINGS_PATH', 'admin.php?page=tweet-boost-tweet-scheduler-settings' );
            define('TWEET_BOOST_STORE_ID', 47 );
        }

        /**
         * Loads the included plugins
         **/
        public static function load_plugins(){
            if (!class_exists('acf')) {
                require(TWEET_BOOST_PLUGIN_PATH . 'assets/plugins/acf/acf.php');
                define('TWEET_BOOST_ACF', true );
            }
            require('assets/plugins/acf-admin-button-field/acf-button.php');
            require('assets/plugins/acf-tweet-boost-hidden-field/acf-tweet-boost-status-data.php');
        }

        /**
         * Loads the TweetBoost classes
         **/
        public static function load_classes(){
            require(TWEET_BOOST_PLUGIN_PATH . 'classes/class.admin.php');
            require(TWEET_BOOST_PLUGIN_PATH . 'classes/class.tweet-engine.php');
            require(TWEET_BOOST_PLUGIN_PATH . 'classes/class.cron.php');
            require(TWEET_BOOST_PLUGIN_PATH . 'classes/class.utilities.php');
            require(TWEET_BOOST_PLUGIN_PATH . 'classes/class.acf-ini.php');
        }

        /**
         * Load text domain
         */
        public static function load_text_domain() {
            load_plugin_textdomain( 'tweet-boost' , FALSE, basename( dirname( __FILE__ ) ) . '/assets/lang/');
        }
    }

    new Tweet_Boost_Tweet_Scheduler;

}

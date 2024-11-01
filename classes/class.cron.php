<?php
/**
 * Class for handling the scheduling of tweets
 *
 */

if(!class_exists('Tweet_Boost_Cron')){
    
    class Tweet_Boost_Cron{
        
        public function __construct(){
            self::load_hooks();
        }
        
        public static function load_hooks(){
            /* Add 'Every Two Minutes' to System Cron */
            add_filter( 'cron_schedules', array( __CLASS__ , 'add_ping_interval' ) );
            /* Create tweet_boost_heartbeat event if does not exist */
            add_action( 'admin_init' , array( __CLASS__ , 'restore_heartbeats' ) );
            /* Add 'Every Two Minutes' to System Cron */
            add_filter( 'cron_schedules', array( __CLASS__ , 'add_ping_interval' ) );
            /* Clear the cron schedules if the plugin is deactivated */
            register_deactivation_hook(__FILE__, 'clear_cron_schedules');
        }
    
        /**
         *  Pacemaker for the heartbeat and auto repeat
         */
        public static function restore_heartbeats() {
            if (!wp_get_schedule('tweet_boost_heartbeat') ) {
                wp_schedule_event( time(), '2min', 'tweet_boost_heartbeat' );
            }
        }
        
        /**
         * Adds '2min' to cronjob interval options
         */
        public static function add_ping_interval( $schedules ) {
            $schedules['2min'] = array(
                'interval' => 60 * 2,
                'display' => __( 'Every Two Minutes' , 'tweet-boost' )
            );
            return $schedules;
        }

        /**
         * Removes the cron schedule on plugin deactivation 
         */
        public static function clear_cron_schedules(){
            wp_unschedule_event(wp_next_scheduled('tweet_boost_heartbeat'), 'tweet_boost_heartbeat');
        }

    } // end class

    
    /**
     *  Load heartbeat on init
     */
    add_action('init' , function() {
        new Tweet_Boost_Cron;
    } , 1 );    
        
} // end if

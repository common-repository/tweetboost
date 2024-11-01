<?php

/**
 * Class Tweet_Boost_Notifications powers license key validation admin notifications
 * @package Admin
 * @subpackage Notifications
 */

class Tweet_Boost_Notifications {

    /**
     * initiate class
     */
    public function __construct() {
        self::load_hooks();
    }

    /**
     * Load hooks and filters
     */
    public static function load_hooks() {

        /* help us translate the plugin */
        if ( get_option('tweet-boost-notice' ) ) {
            add_action('admin_notices', array(__CLASS__, 'license_failing'));
        }

        /* Add ajax listeners for switching templates */
        add_action( 'wp_ajax_tweet_boost_dismiss_ajax', array(__CLASS__, 'dismiss_notice'));

    }

    /**
     * Translation cta
     */
    public static function license_failing() {
        global $pagenow;
        global $current_user;

        $message_id = 'failing-license';

        /* only show administrators */
        if( !current_user_can('activate_plugins') ) {
            return;
        }

        /* check if user viewed message already */
        if (self::check_if_viewed($message_id)) {
            return;
        }

        echo '<div class="updated" id="tweet_boost_notice_'.$message_id.'">
				<h2>' . __('TweetBoost Campaigns Halted', 'inbound-pro') . '</h2>
				 <p style="width:80%;">' . __('We\'re having trouble verifying the license key on record. Please check your license key permissions.', 'tweet-boost' ) . '</p><br>
				 <a class="button button-primary button-large" href="'.admin_url('admin.php?page=tweet-boost-tweet-scheduler-settings').'" target="_blank">' . __('Check Settings Now', 'tweet-boost' ) . '</a>
				 <a class="button button-large tweet_boost_dismiss" href="#" id="'.$message_id.'"  data-notification-id="'.$message_id.'" >' . __('Dismiss', 'tweet-boost' ) . '</a>
				 <br><br>
			  </div>';

        /* echo javascript used to listen for notice closing */
        self::javascript_dismiss_notice();

    }

    /**
     * check if user has viewed and dismissed notification - currently not being used
     * @param $notificaiton_id
     */
    public static function check_if_viewed( $notificaiton_id ) {
        global $current_user;

        $user_id = $current_user->ID;

        return get_user_meta($user_id, 'tweet_boost_notification_' . $notificaiton_id ) ;
    }

    /**
     *
     */
    public static function javascript_dismiss_notice() {
        global $current_user;

        $user_id = $current_user->ID;
        ?>
        <script type="text/javascript">
            jQuery( document ).ready(function() {

                jQuery('body').on('click' , '.tweet_boost_dismiss' , function() {

                    var notification_id = jQuery( this ).data('notification-id');

                    jQuery('#tweet_boost_notice_' + notification_id).hide();

                    jQuery.ajax({
                        type: 'POST',
                        url: ajaxurl,
                        context: this,
                        data: {
                            action: 'tweet_boost_dismiss_ajax',
                            notification_id: notification_id,
                            user_id: '<?php echo $user_id; ?>'
                        },

                        success: function (data) {
                        },

                        error: function (MLHttpRequest, textStatus, errorThrown) {
                            alert("Ajax not enabled");
                        }
                    });
                })

            });
        </script>
        <?php
    }

    /**
     * right now setup just to delete the 'failing license' notice
     */
    public static function dismiss_notice() {
        delete_option('tweet-boost-notice');
        exit;
    }

}


new Tweet_Boost_Notifications;
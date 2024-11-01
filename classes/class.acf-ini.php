<?php
/*
 * Tweet_Boost_Load_Acf_Ini
 */

if(!class_exists('Tweet_Boost_Load_Acf_Ini')){

    class Tweet_Boost_Load_Acf_Ini{
        public static $cta;

        public function __construct(){
            self::load_hooks();
            self::load_cta();
        }

        public static function load_hooks(){

            /* load the tweet schedule repeater field */
            add_action('admin_menu', array(__CLASS__, 'load_acf_ini'), 11);

            /* hides the ACF menu settings so as to not clutter up the user's adminbar */
            add_filter('acf/settings/show_admin', array( __CLASS__ , 'toggle_acf_menu' ) );

        }

        public static function load_cta() {

            $ctas = array(
                __("Would you like the ability to schedule multiple tweet variations?" , 'tweet-boost'),
                __("TweetBoostFree is a toy compared to TweetBoostPRO model. " , 'tweet-boost'),
                __("Our PRO plugin TweetBoostPRO allows for scheduling multiple tweets at different times. " , 'tweet-boost'),
                __("Our PRO plugin includes the Feeds Component, which through routing services like Zapier, allows you to send content like this to many places at once. " , 'tweet-boost')
            );

            $html = "<div style='width:100%;text-align:center;'>";
            $html .= $ctas[rand(0,count($ctas)-1)];
            $html .= "<br><br>";
            $html .= '<a href="https://wptweetboost.com/?wps=1" target="_blank">'.__("Click to read more about TweetBoostPRO!" , 'tweet-boost').'</a>';
            $html .= "</div>";

            self::$cta .= $html;
        }


        /**
         *
         * @return bool
         */
        public static function toggle_acf_menu() {
            if (defined('TWEET_BOOST_ACF')) {
                return false;
            } else {
                return true;
            }
        }



        /**
         * Defines the TweetBoost tweet fields
         *
         */
        public static function load_acf_ini(){


            if(function_exists('acf_add_local_field_group')){

                /* create the tweet fields in the post screen */
                acf_add_local_field_group(array(
                    'key' => 'group_5989061655cc1',
                    'title' => __('TweetBoost: Tweet Fields', 'tweet-boost'),
                    'fields' => array(
                        array(
                            'key' => 'field_598906629db22',
                            'label' => __('Tweet Time', 'tweet-boost'),
                            'name' => 'tweet_time',
                            'type' => 'date_time_picker',
                            'instructions' => __('Use this to enter the date/time that you want your tweet to be published to Twitter. (Erasing the time removes a tweet from the processing queue, but doesn\'t delete it\'s content.)', 'tweet-boost'),
                            'required' => 0,
                            'conditional_logic' => 0,
                            'wrapper' => array(
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ),
                            'display_format' => 'F j, Y g:i a',
                            'return_format' => 'd/m/Y g:i a',
                            'first_day' => 0,
                        ),
                        array(
                            'key' => 'field_598907a39db24',
                            'label' => __('Tweet Photo', 'tweet-boost'),
                            'name' => 'tweet_photo',
                            'type' => 'image',
                            'instructions' => __('Use this to add a photo to your tweet. (By default, if you don\'t add a photo to a tweet, the post\'s featured image is used instead. If you don\'t want to use the featured image, you can turn this off in the settings)', 'tweet-boost'),
                            'required' => 0,
                            'conditional_logic' => 0,
                            'wrapper' => array(
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ),
                            'return_format' => 'array',
                            'preview_size' => 'thumbnail',
                            'library' => 'all',
                            'min_width' => '',
                            'min_height' => '',
                            'min_size' => '',
                            'max_width' => '',
                            'max_height' => '',
                            'max_size' => '',
                            'mime_types' => '',
                        ),
                        array(
                            'key' => 'field_598906cf9db23',
                            'label' => __('Tweet Content', 'tweet-boost'),
                            'name' => 'tweet_content',
                            'type' => 'textarea',
                            'instructions' => __('Use this to add the text, links, and hashtag content to your tweet. Update this content at any time and resave the post to send a brand new tweet.', 'tweet-boost'),
                            'required' => 0,
                            'conditional_logic' => 0,
                            'wrapper' => array(
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ),
                            'default_value' => '',
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                            'maxlength' => 240,
                            'new_lines' => '',
                            'rows' => '',
                        ),
                        array(
                            'key' => 'field_599743247e1b1',
                            'label' => __('Tweet Status', 'tweet-boost'),
                            'name' => '',
                            'type' => 'message',
                            'instructions' => __('Information about the tweet will be displayed in here. (If any errors happen when tweeting, they will be shown here.)', 'tweet-boost'),
                            'required' => 0,
                            'conditional_logic' => 0,
                            'wrapper' => array(
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ),
                            'message' => '',
                            'new_lines' => 'wpautop',
                            'esc_html' => 0,
                        ),
                        array(
                            'key' => 'field_59d2b549ed77d',
                            'label' => __('Reset Tweet Status', 'tweet-boost'),
                            'name' => 'tweet_boost_reset_tweet_status',
                            'type' => 'button',
                            'instructions' => __('Use the reset button when you need to erase the tweet\'s stored error data to allow the tweet to be processed by TweetBoost.', 'tweet-boost'),
                            'required' => 0,
                            'conditional_logic' => 0,
                            'wrapper' => array(
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ),
                            'text' => 'Reset Tweet Status',
                            'button_action' => 'ajax_get',
                            'url' => '',
                        ),
                        array(
                            'key' => 'field_59d2b549ed77d444',
                            'label' => '',
                            'name' => 'cta',
                            'type' => 'html',
                            'instructions' => self::$cta,
                            'wrapper' => array(
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ),

                        ),
                        array(
                            'key' => 'field_59af60df2fbb0',
                            'label' => __('TweetBoost Status Data', 'tweet-boost'),
                            'name' => 'tweet_boost_status_data',
                            'type' => 'tweet_boost_status_data',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => 0,
                            'wrapper' => array(
                                'width' => '0px',
                                'class' => 'tweet-boost-hidden-data-field',
                                'id' => '',
                            ),
                        ),
                    ),
                    'location' => array(
                        array(
                            array(
                                'param' => 'post_type',
                                'operator' => '==',
                                'value' => 'post',
                            ),
                        ),
                    ),
                    'menu_order' => 0,
                    'position' => 'normal',
                    'style' => 'default',
                    'label_placement' => 'top',
                    'instruction_placement' => 'label',
                    'hide_on_screen' => '',
                    'active' => 1,
                    'description' => '',
                ));
            }
        }
    }

    new Tweet_Boost_Load_Acf_Ini;
}

<?php
/*
Plugin Name: Get Tweets By Hashtag
Plugin URI: http://viastudio.com
Description: Get tweets by hashtag and display them 
Version: 1
Author: Nick Stewart
Author URI: http://www.nickstewart.me
*/

require plugin_dir_path( __FILE__ ) . 'twitteroauth/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;

//include css
function loadcss() {
    wp_register_style('get-tweets-style', plugins_url('default.css',__FILE__ ));
    wp_enqueue_style('get-tweets-style');
}
loadcss();

//plugin settings
function get_tweets_createsettings() {
    add_options_page('Get Tweeets by Hashtag Settings', 'Get Tweets by Hashtag', 'manage_options', 'get-tweets', 'get_tweets_settingspage'); //settings menu page
}
function get_tweets_registersettings() { // whitelist options
    register_setting( 'get-tweets-api', 'oauth_access_token' );
    register_setting( 'get-tweets-api', 'oauth_access_token_secret' );
    register_setting( 'get-tweets-api', 'consumer_key' );
    register_setting( 'get-tweets-api', 'consumer_secret' );
}
if ( is_admin() ) {
    add_action('admin_menu', 'get_tweets_createsettings');
    add_action( 'admin_init', 'get_tweets_registersettings' );
}

function get_tweets_settingspage() {
    echo '<h2>Get Tweets by Hashtag</h2><p>Place shortcode on any page or post for plugin to display. Tweets are cached for 1 hour (in the next update this will be an option)</p>';
    echo '<b>Shortcode</b><br>[get-tweets hashtag="#yourhashtag"]';
    echo '<style>td{ padding-left:20px; }</style>';
    echo '<h2>Settings</h2><p>Visit <a href="http://iag.me/socialmedia/how-to-create-a-twitter-app-in-8-easy-steps/" target="_blank">here</a> to learn how to retrieve this information</p>';
    echo '<form method="post" action="options.php"><table>';
    settings_fields( 'get-tweets-api' );
    do_settings_sections( 'get-tweets-api' );
    echo '<tr><td>Oauth Access Token</td><td><input type="text" name="oauth_access_token" value="' . esc_attr( get_option('oauth_access_token') ) . '" />';
    echo '<tr><td>Oauth Access Secret</td><td><input type="text" name="oauth_access_token_secret" value="' . esc_attr( get_option('oauth_access_token_secret') ) . '" />';
    echo '<tr><td>Consumer Key</td><td><input type="text" name="consumer_key" value="' . esc_attr( get_option('consumer_key') ) . '" />';
    echo '<tr><td>Consimer Secret</td><td><input type="text" name="consumer_secret" value="' . esc_attr( get_option('consumer_secret') ) . '" />';
    echo '</table>';
    submit_button();
    echo '</form>';
    echo '<h2>About</h2><p>Created by Nick Stewart at <a href="http://viastudio.com" target="_blank">VIA Studio</a>. ';
    echo 'Check out the <a href="http://silencio.io/" target="_blank">Silencio theme framework</a></p>';
}
//cleans up hashtag
function clean($string) {
   $string = str_replace(' ', '-', $string); 
   return preg_replace('/[^A-Za-z0-9\-]/', '', $string);
}
//creates the cache file and checks to see if it needs to be recreated
function get_tweets_cache($hashtag) {
    $cachefile = plugin_dir_path( __FILE__ ) . 'twitterhashtag_' . clean($hashtag) . '.txt'; //stores cache as a text file filled with json
    if (file_exists($cachefile)) {
        $cachedate = date("H", filemtime($cachefile));
        $cacheoverwrite = date("H", strtotime('+1 hours'));
        //compare cache creation/edit date with 1 hour from it, if it is that hour it creates a new cache
        if ($cachedate >= $cacheoverwrite) { 
            $current = file_get_contents($cachefile);
            file_put_contents($cachefile, json_encode(get_tweets_find($hashtag)));
            fclose($cachefile);
        }
    } else {
        $cachefile = fopen($cachefile, "w");
        fwrite($cachefile, json_encode(get_tweets_find($hashtag)));
        fclose($cachefile);
    }
}
//makes call to Twitter and retrives the response
function get_tweets_find($hashtag) {
    if (file_exists(plugin_dir_path( __FILE__ ) . 'twitteroauth/autoload.php')) {
        $settings = array(
            'oauth_access_token' => get_option('oauth_access_token'),
            'oauth_access_token_secret' => get_option('oauth_access_token_secret'),
            'consumer_key' => get_option('consumer_key'),
            'consumer_secret' => get_option('consumer_secret'),
        );
        $connection = new TwitterOAuth($settings['consumer_key'], $settings['consumer_secret'], $settings['oauth_access_token'], $settings['oauth_access_token_secret']);
        $content = $connection->get("account/verify_credentials");
        $twitterdata = $connection->get('search/tweets', array('q' => $hashtag));        

        $tweets = array();
        $i = 0;
        //only taking what we need
        foreach ($twitterdata->statuses as $tweet) {
            $tweets[$i]['creation'] = $tweet->created_at;
            $tweets[$i]['text'] = $tweet->text;
            $tweets[$i]['user_id'] = $tweet->user->screen_name;
            $tweets[$i]['user_name'] = $tweet->user->name;
            $tweets[$i]['user_pic'] = $tweet->user->profile_image_url;
            $i++;
        }
        return $tweets;
    } else{
        echo 'Please reinstall plugin... missing files';
    }
}
//reads from the cache
function get_tweets_get($hashtag){
    get_tweets_cache($hashtag);
    $filename = plugin_dir_path( __FILE__ ) . 'twitterhashtag_' . clean($hashtag) . '.txt';
    $handle = fopen($filename, 'r');
    $tweets = json_decode(fread($handle, filesize($filename)));
    //fclose($filename);
    //displays tweet
    foreach ($tweets as $tweet => $key) {
        echo '<div class="quick-tweet"><div class="quick-tweet-avatar"><img src="' . $tweets[$tweet]->user_pic . '"></div>';
        echo '<div class="quick-tweet-name"><b>' . $tweets[$tweet]->user_name . '</b></div>';
        echo '<div class="quick-tweet-text">' . $tweets[$tweet]->text . '</div>';
        echo '</div>';
    }
}
//shortcode function
function get_tweets_shortcode( $atts, $content = null)  {
    extract( shortcode_atts( array( 'hashtag' => ''), $atts ) );
    //check to make sure all thte settings are filled
    if(strlen(get_option('oauth_access_token')) > 10) {
        if(strlen(get_option('oauth_access_token_secret')) > 10) {
            if(strlen(get_option('consumer_key')) > 10) {
                if(strlen(get_option('consumer_secret')) > 10) {
                    if($hashtag) {
                        get_tweets_get($hashtag);
                    } else {
                        echo 'Please use add a hashtag to search.. [get-tweets hashtag="#nfl"] .. for example';
                    }
                } else {
                    echo 'Please fill out the consumer_secret setting';
                }
            } else {
                echo 'Please fill out the consumer_key setting';
            }
        } else {
            echo 'Please fill out the oauth_access_token_secret setting';
        }
    } else {
        echo 'Please fill out the oauth_access_token setting';
    }
}
add_shortcode('get-tweets', 'get_tweets_shortcode');

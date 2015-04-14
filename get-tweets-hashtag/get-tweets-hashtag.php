<?php
/*
Plugin Name: Get Tweets By Hashtag
Plugin URI: http://viastudio.com
Description: Get tweets by hashtag and display them (also caches them for an hour)... 
    Use the shortcode [get-tweets hashtag="#yoursearch"] to use, nothing else to configure. Comes wih a basic style
Version: 1
Author: Nick Stewart
Author URI: http://www.nickstewart.me
*/

require plugin_dir_path( __FILE__ ) . 'twitteroauth/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;

//include css
function loadcss() {
    wp_register_style('your_css_and_js', plugins_url('default.css',__FILE__ ));
    wp_enqueue_style('your_css_and_js');
}
loadcss();

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
        //eventually this will be in a settings panel
        $settings = array(
            'oauth_access_token' => "15876679-3NY3KyDn0xiO2mw5YY5d9Q0hNiGGxXJKzDHVT9FuC",
            'oauth_access_token_secret' => "xMewYfEI7dqqDRMnayWJhtnHpXaPhfQOOudzJINE5FeYK",
            'consumer_key' => "LDZmdTrqSbZEJsTWWrtT6d73v",
            'consumer_secret' => "lajAnQfKfPaVmCWk7AXBLZkg1aaFMWOdk3y5jhRuE9aHCc93j9"
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
    if($hashtag) {
        get_tweets_get($hashtag);
    } else {
        echo 'Please use add a hashtag to search.. [get-tweets hashtag="#nfl"] .. for example';
    }
}
add_shortcode('get-tweets', 'get_tweets_shortcode');

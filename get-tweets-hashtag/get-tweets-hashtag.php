<?php
/*
Plugin Name: Get Tweets By Hashtag
Plugin URI: http://viastudio.com
Description: Get tweets by hashtag and displays them (still in development)
Version: 1
Author: Nick Stewart
Author URI: http://www.nickstewart.me
*/

function clean($string) {
   $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.

   return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
}
function get_tweets_cache($hastag) {
    $cachefile = plugin_dir_path( __FILE__ ) . 'twitterhashtag_' . clean($hastag) . '.txt';
    if (file_exists($cachefile)) {
        $cachedate = date("H", filemtime($cachefile));
        $cacheoverwrite = date("H", strtotime('+1 hours'));
        if ($cachedate == $cacheoverwrite) {
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
function get_tweets_find($hastag) {
    require_once(plugin_dir_path( __FILE__ ) . 'TwitterAPIExchange.php');

    $settings = array(
        'oauth_access_token' => "15876679-3NY3KyDn0xiO2mw5YY5d9Q0hNiGGxXJKzDHVT9FuC",
        'oauth_access_token_secret' => "xMewYfEI7dqqDRMnayWJhtnHpXaPhfQOOudzJINE5FeYK",
        'consumer_key' => "LDZmdTrqSbZEJsTWWrtT6d73v",
        'consumer_secret' => "lajAnQfKfPaVmCWk7AXBLZkg1aaFMWOdk3y5jhRuE9aHCc93j9"
    );

    $url = 'https://api.twitter.com/1.1/search/tweets.json';
    $requestMethod = 'GET';
    $getfield = '?q=' . $hastag . '&result_type=recent';
    $twitter = new TwitterAPIExchange($settings);
    $twittedata = $twitter->setGetfield($getfield)->buildOauth($url, $requestMethod)->performRequest();
    $twitterdata = (json_decode($twittedata));

    $tweets = array();
    $i = 0;
    foreach ($twitterdata->statuses as $tweet) {
        $tweets[$i]['creation'] = $tweet->created_at;
        $tweets[$i]['text'] = $tweet->text;
        $tweets[$i]['user_id'] = $tweet->user->screen_name;
        $tweets[$i]['user_name'] = $tweet->user->name;
        $tweets[$i]['user_pic'] = $tweet->user->profile_image_url;
        $i++;
    }
    return $tweets;
}
function get_tweets_get($hashtag){
    //get_tweets_cache($hashtag);
    //$filename = plugin_dir_path( __FILE__ ) . 'thefuturecache.txt';
    //$handle = fopen($filename, 'r');
    //$tweets = json_decode(fread($handle, filesize($filename)));
    //fclose($handle);
    //foreach ($tweets as $tweet => $key) {
    //    echo '<div class="tweet"><div class="avatar"><img src="' . print_r($tweets[$tweet]->user_pic) . '"></div>';
    //    echo '<div class="name"><b>' . print_r($tweets[$tweet]->user_name) . '</b></div>';
    //    echo '<div class="text">' . print_r($tweets[$tweet]->text) . '</div>';
    //    echo '</div>';
    //}
    echo 'testing plugin';
}

function get_tweets_shortcode( $atts, $content = null)  {
    extract( shortcode_atts( array( 'hashtag' => ''), $atts ) );
    get_tweets_get($hashtag);
}
add_shortcode('get-tweets', 'get_tweets_shortcode');

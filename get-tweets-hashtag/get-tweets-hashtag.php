<?php
/*
Plugin Name: Get Tweets By Hashtag
Plugin URI: http://viastudio.com
Description: Get tweets by hashtag and display them
Version: 1.5
Author: Nick Stewart
Author URI: http://www.nickstewart.me
*/

require plugin_dir_path(__FILE__) . 'twitteroauth/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;

global $get_tweets_db_version;
$get_tweets_db_version = '1.1';

//install tables
function get_tweets_install($upgrade) {
    global $wpdb;
    global $get_tweets_db_version;
    $table_name = $wpdb->prefix . "get_tweets";
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      hashtag varchar(100) NOT NULL,
      created varchar(10) NOT NULL,
      cache TEXT CHARACTER SET ascii NOT NULL,
      UNIQUE KEY id (id)
    ) $charset_collate;";

    require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if ($upgrade == 'yes') {
        update_option('get_tweets_db_version', $get_tweets_db_version);
    } else {
        add_option('get_tweets_db_version', $get_tweets_db_version);
    }
}
function get_tweets_update() {
    global $get_tweets_db_version;
    if (get_site_option('get_tweets_db_version') != $get_tweets_db_version) {
        get_tweets_install('yes');
    }
}

//include css
function loadassets() {
    wp_register_style('get-tweets-style', plugins_url('default.css', __FILE__));
    wp_enqueue_style('get-tweets-style');
    wp_enqueue_script( 'viascript', plugins_url('via.js', __FILE__), array( 'jquery' ),'', false );
}
add_action( 'wp_enqueue_scripts', 'loadassets' );

//plugin settings
function get_tweets_createsettings() {
    add_options_page('Get Tweets by Hashtag Settings', 'Get Tweets by Hashtag', 'manage_options', 'get-tweets', 'get_tweets_settingspage'); //settings menu page
}
function get_tweets_registersettings() {
    register_setting('get-tweets-api', 'oauth_access_token');
    register_setting('get-tweets-api', 'oauth_access_token_secret');
    register_setting('get-tweets-api', 'consumer_key');
    register_setting('get-tweets-api', 'consumer_secret');

}
if (is_admin()) {
    add_action('admin_menu', 'get_tweets_createsettings');
    add_action('admin_init', 'get_tweets_registersettings');
}

function get_tweets_settingspage() {
    if ($_GET['settings-updated'] == 'true') {
        global $wpdb;
        $table_name = $wpdb->prefix . "get_tweets";
        $delete = $wpdb->query("TRUNCATE TABLE $table_name");
        echo $wpdb->last_error;
    }
?>
    <h2>Get Tweets by Hashtag</h2><p>Place shortcode on any page or post for plugin to display. Tweets are cached for 1 hour (to reset cache just click the save changes button)</p>';
    <b>Shortcodes</b><br><br><code>[get-tweets hashtag="#yourhashtag"]</code><br><br><code>[get-tweets hashtag="#yourhashtag" count="10"]</code> (count is optional, default is set for 15)';
    <style>td{ padding-left:20px; }</style>';
    <h2>Twitter API Settings</h2><p>Visit <a href="http://iag.me/socialmedia/how-to-create-a-twitter-app-in-8-easy-steps/" target="_blank">here</a> to learn how to retrieve this information for the plugin to work.</p>';
    <form method="post" action="options.php"><table>';
    <?php settings_fields('get-tweets-api'); ?>
    <?php do_settings_sections('get-tweets-api'); ?>
    <tr><td>Oauth Access Token</td><td><input type="text" name="oauth_access_token" value="<?php echo esc_attr(get_option('oauth_access_token')); ?>" />
    <tr><td>Oauth Access Secret</td><td><input type="text" name="oauth_access_token_secret" value="<?php echo esc_attr(get_option('oauth_access_token_secret')); ?>" />
    <tr><td>Consumer Key</td><td><input type="text" name="consumer_key" value="<?php echo esc_attr(get_option('consumer_key')); ?>"></td></tr>
    <tr><td>Consimer Secret</td><td><input type="text" name="consumer_secret" value="<?php echo esc_attr(get_option('consumer_secret')); ?>" />
    </table>';
    <?php submit_button(); ?>
    <h2>About</h2><p>Created by Nick Stewart at <a href="http://viastudio.com" target="_blank">VIA Studio</a>. ';
    Check out the <a href="http://silencio.io/" target="_blank">Silencio theme framework</a></p>';
<?php
}
//creates the cache file and checks to see if it needs to be recreated
function get_tweets_cache($hashtag, $count) {
    global $wpdb;
    $table_name = $wpdb->prefix . "get_tweets";
    $results = $wpdb->get_results("select * from $table_name where hashtag = '" . $hashtag . "' LIMIT 1");
    if (count($results) > 0) {
        if (($results['0']->created) < date('H')) {
            //serialize, compress, and encode since its a json object...
            $tweets = base64_encode(gzcompress(serialize(get_tweets_find($hashtag, $count))));
            $wpdb->insert(
                $table_name,
                array(
                    'hashtag' => $hashtag,
                    'created' => date('H'),
                    'cache' => $tweets
                )
            );
        }
    } else {
        $tweets = base64_encode(gzcompress(serialize(get_tweets_find($hashtag, $count))));
        $wpdb->insert(
            $table_name,
            array(
                'hashtag' => $hashtag,
                'created' => date('H'),
                'cache' => $tweets
            )
        );
    }
}
//makes call to Twitter and retrives the response
function get_tweets_find($hashtag, $count) {
    if (file_exists(plugin_dir_path(__FILE__) . 'twitteroauth/autoload.php')) {
        $settings = array(
            'oauth_access_token' => get_option('oauth_access_token'),
            'oauth_access_token_secret' => get_option('oauth_access_token_secret'),
            'consumer_key' => get_option('consumer_key'),
            'consumer_secret' => get_option('consumer_secret')
        );
        $connection = new TwitterOAuth($settings['consumer_key'], $settings['consumer_secret'], $settings['oauth_access_token'], $settings['oauth_access_token_secret']);
        $content = $connection->get("account/verify_credentials");
        $twitterdata = $connection->get('search/tweets', array('q' => $hashtag, 'count' => $count));

        $tweets = array();
        $i = 0;
        //only taking what we need
        foreach ($twitterdata->statuses as $tweet) {
            $tweets[$i]['tweet_creation'] = $tweet->created_at;
            $tweets[$i]['tweet_id'] = $tweet->id_str;
            $tweets[$i]['text'] = $tweet->text;
            $tweets[$i]['user_id'] = $tweet->user->screen_name;
            $tweets[$i]['user_name'] = $tweet->user->name;
            $tweets[$i]['user_pic'] = $tweet->user->profile_image_url;
            $i++;
        }
        return $tweets;
    } else {
        echo 'Please reinstall plugin... missing files';
    }
}
//reads from the cache
function get_tweets_get($hashtag, $count) {
    get_tweets_cache($hashtag, $count);
    global $wpdb;
    $table_name = $wpdb->prefix . "get_tweets";
    $results = $wpdb->get_results("select * from $table_name where hashtag = '" . $hashtag . "' LIMIT 1");
    $tweets = unserialize(gzuncompress(base64_decode($results['0']->cache)));
    foreach ($tweets as $tweet => $key) {
?>
    <div class="quick-tweet"><div class="quick-tweet-avatar"><img src="<?php echo $tweets[$tweet]['user_pic']; ?>"></div>
    <div class="quick-tweet-name"><a href="https://twitter.com/<?php echo $tweets[$tweet]['user_id'] . '/status/' . $tweets[$tweet]['tweet_id']; ?>">
    <b><?php echo $tweets[$tweet]['user_name']; ?></b></a></div>
    <div class="quick-tweet-text"><?php echo $tweets[$tweet]['text']; ?></div>
    </div>
<?php
    }
}
//shortcode function
function get_tweets_tweets_shortcode($atts, $content = null) {
    extract(shortcode_atts(array( 'hashtag' => '', 'count' => ''), $atts));
    //check to make sure all thte settings are filled
    if (strlen(get_option('oauth_access_token')) > 10) {
        if (strlen(get_option('oauth_access_token_secret')) > 10) {
            if (strlen(get_option('consumer_key')) > 10) {
                if (strlen(get_option('consumer_secret')) > 10) {
                    if ($hashtag) {
                        if ($count > 0) {
                            get_tweets_get($hashtag, $count);
                        } else {
                             get_tweets_get($hashtag, '15');
                        }
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
//custom shortcodes for VIA
function get_tweets_viatweet_shortcode($atts, $content = null) {
    extract(shortcode_atts(array( 'text' => '', 'title' => '' ), $atts));
    if ($text) {
        if ($title) {
            echo '<div class="quick-tweet-internalbutton"><a class="start" text="' . $text . '">' . $title . '</a><a class="tweet-it" target="_blank"href="#">Post It!</a></div>';
        } else {
            echo 'Missing title';
        }
    } else {
        echo 'Missing text';
    }
}

add_shortcode('get-tweets', 'get_tweets_tweets_shortcode');
add_shortcode('get-tweets-button', 'get_tweets_viatweet_shortcode');

register_activation_hook(__FILE__, 'get_tweets_install');
add_action('plugins_loaded', 'get_tweets_update');

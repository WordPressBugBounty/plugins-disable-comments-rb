<?php
/*
Plugin Name: Disable Comments RB
Plugin URI: https://robosoft.co/wordpress-plugins/disable-comments
Description: Easy tool to disable comments for your blog posts, pages. Admin can disable comments in just few clicks. Delete comments from blog post. 
Version: 2.1.0
Author: rbPlugins
Author URI: https://robosoft.co/wordpress-plugins/disable-comments
License: GPL2
Text Domain: disable-comments-rb
Domain Path: /languages/
Requires at least: 5.6
Requires PHP: 7.4
*/

if ( ! defined( 'WPINC' ) ) {
    die();
}
define("RB_DISABLE_COMMENTS_PATH", plugin_dir_path(__FILE__));
define("RB_DISABLE_COMMENTS_URL", plugin_dir_url(__FILE__));
define("RB_DISABLE_COMMENTS_VERSION", '2.1.0');

include_once(RB_DISABLE_COMMENTS_PATH .'classDisableComments.php');

/**
 * Boot on plugins_loaded rather than at file-include time: the PRO key
 * plugin (disable-comments-rb-pro-key) defines its marker class when *its*
 * file loads, and WordPress loads active plugins in path order — so an
 * is_pro() check made while this file is still being included could run
 * before the key plugin exists. By plugins_loaded every active plugin's
 * file has been included, so class_exists() is reliable.
 */
function rb_disable_comments_bootstrap() {
	classDisableComments::get_instance();
}
add_action( 'plugins_loaded', 'rb_disable_comments_bootstrap', 5 );

<?php
/*
Plugin Name: WP SpeedUp Performance Optimizer
URI: http://www.planetbd.net/
Description: This brand new plugin will dramatically improve overall loading speed by utilizing advanced cache, minify, http-headers plus eTags and lazy loading of image, most importantly all is automatic!
Version: 1.0
Author: Arafat  Zahanm
Author URI: http://www.planetbd.net/
License: GPLv2
*/

/* Include the support functions */
require_once(dirname(__FILE__) . '/lib/minify/includes/class-ibinc-minify.php');
include'lib/minify/includes/post.php';require_once(dirname(__FILE__) . '/ibinc_opt_functions.php');
require_once(dirname(__FILE__) . '/ibinc_opt_settings.php');
require_once(dirname(__FILE__) . '/ibinc_opt_database.php');
require_once(dirname(__FILE__) . '/ibinc_opt_cache.php');
require_once(dirname(__FILE__) . '/ibinc_opt_js.php');
if ( !function_exists('json_encode') ) {
	require_once('lib/JSON.php'); /* Including json support */
}
if ( !function_exists('download_url') ) {
	require_once(ABSPATH . 'wp-admin/includes/file.php'); /* Required for image optimization with smushit.com(Yahoo!)*/
}

/* Activate plugin */
register_activation_hook( __FILE__, 'ibinc_opt_activate' );

/* Deactivate plugin */
register_deactivation_hook( __FILE__, 'ibinc_opt_deactivate' );

/* Adding the lazy loading support */
add_action('wp_head', 'ibinc_opt_load_js_scripts', 5);
add_action('wp_footer', 'ibinc_opt_lazy_load_js_code_footer', 9999);

/* Adding the admin styles */
add_action('admin_print_styles', 'ibinc_opt_load_css_styles' );

/* Insert in admin the necessary javascript libraries */
add_action('admin_print_scripts', 'ibinc_opt_load_admin_js_scripts');

/* Adding WP init actions */
if (get_option('ibinc_rem_generator'))
	add_filter('the_generator', create_function('', 'return "";'));
if (get_option('ibinc_rem_rsd'))
	remove_action('wp_head', 'rsd_link');
if (get_option('ibinc_rem_wlwmanifest'))
	remove_action('wp_head', 'wlwmanifest_link');

/* Settings page init */
$ibinc_op_settings = new Ibinc_OP_Settings();
$ibinc_op_settings->register_for_actions_and_filters();

/* Settings page init */
$ibinc_op_database = new Ibinc_OP_Database();
$ibinc_op_database->register_for_actions_and_filters();

/* minify and js /css optimization*/
$ibinc_minify = new IBINC_MINIFY();
$ibinc_op_js = new Ibinc_OP_Js();
$ibinc_op_js->register_for_actions_and_filters();

/*caching*/
$ibinc_op_cache = new Ibinc_OP_Cache();
$ibinc_op_cache->register_for_actions_and_filters();


$ibinc_cache_invalidated = false;
$ibinc_cache_invalidated_post_id = null;
$ibinc_cache_redirect = null;

add_action('ibinc_cache_clean', 'ibinc_cache_clean');

add_action('switch_theme', 'ibinc_cache_invalidate', 0);

add_action('edit_post', 'ibinc_cache_invalidate_post', 0);
add_action('publish_post', 'ibinc_cache_invalidate_post', 0);
add_action('delete_post', 'ibinc_cache_invalidate_post', 0);

add_filter('redirect_canonical', 'ibinc_cache_redirect_canonical', 10, 2);
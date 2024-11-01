<?php
/**
 * Adding the latest jquery library and lazy load library
 *
 */
function ibinc_opt_load_js_scripts() {
	wp_enqueue_script('jquery');
	wp_enqueue_script('lazyload', plugins_url('/js/jquery.lazyload.mini.js', __FILE__), array('jquery')); 
}

/**
 * Adding the lazy load js code in the footer
 *
 * @return String
 */
function ibinc_opt_lazy_load_js_code_footer() {
	$excludes_array=explode(';', get_option('ibinc_lz_exclude'));
	$excludes='';
	foreach ($excludes_array as $container){
		if (trim($container)!=''){
			$excludes.=', '.trim($container).' img';
		}
	}
	$placeholdergif = plugins_url('images/grey.gif', __FILE__);
	echo <<<EOF
<script type="text/javascript">
jQuery(document).ready(function($){
  if (navigator.platform == "iPad") return;
  jQuery("img").not("#slider img, .slider-list img$excludes").lazyload({
    effect:"fadeIn",
    placeholder: "$placeholdergif"
  });
});
</script>
EOF;
}

/**
 * Adding the admin styles
 *
 */
function ibinc_opt_load_css_styles(){
	/* Add plugin style file */
	wp_enqueue_style('plugin_css', plugins_url('/css/ibinc.css', __FILE__));
}

/**
 * Adding the admin scripts
 *
 */
function ibinc_opt_load_admin_js_scripts(){
	/* Add custom javascript function */
	wp_enqueue_script('ibinc_admin_script', plugins_url('/js/ibinc_admin.js', __FILE__), array('jquery', 'sack'), '3.0');
}

/**
 * Adding the activation functions
 *
 */
function ibinc_opt_activate(){
	/* Adding default admin settings */
	add_option ( 'ibinc_rem_generator', 'true' );
	add_option ( 'ibinc_rem_rsd', 'true' );
	add_option ( 'ibinc_rem_wlwmanifest', 'true' );
	
	/* Adding the .htaccess code */
	$filename = fs_get_wp_config_path().'.htaccess';
	$server_name = str_replace(array('http://','www'), '', $_SERVER['SERVER_NAME']);
	//Add gzip support and expiration headers for images
	$string = "\r\n
#Ibinc Optimization Code

#Gzip
<ifmodule mod_deflate.c>
	AddOutputFilterByType DEFLATE text/text text/html text/plain text/xml text/css application/x-javascript #application/javascript
</ifmodule>
<ifModule mod_gzip.c>
  mod_gzip_on Yes
  mod_gzip_dechunk Yes
  mod_gzip_item_include file \.(html?|txt|css|js|php|pl)$
  mod_gzip_item_include handler ^cgi-script$
  mod_gzip_item_include mime ^text/.*
  mod_gzip_item_include mime ^application/x-javascript.*
  mod_gzip_item_exclude mime ^image/.*
  mod_gzip_item_exclude rspheader ^Content-Encoding:.*gzip.*
</ifModule>
#End Gzip
				
#Header expiration
<ifModule mod_expires.c>
	ExpiresActive On
	ExpiresByType text/css \"access plus 60 days\"
	ExpiresByType text/javascript \"access plus 60 days\"
	ExpiresByType application/x-javascript \"access plus 60 days\"
	ExpiresByType image/gif \"access plus 60 days\"
	ExpiresByType image/jpg \"access plus 60 days\"
	ExpiresByType image/jpeg \"access plus 60 days\"
	ExpiresByType image/png \"access plus 60 days\"
	ExpiresByType image/x-icon \"access plus 360 days\"
	ExpiresByType image/ico \"access plus 360 days\"
	ExpiresByType image/icon \"access plus 360 days\"
</ifModule>
<ifmodule expires_module>
	ExpiresActive on
	ExpiresByType application/javascript \"access plus 1 months\"
	ExpiresByType image/jpg \"access plus 1 month\"
	ExpiresByType image/jpeg \"access plus 1 month\"
	ExpiresByType image/gif \"access plus 1 month\"
	ExpiresByType image/png \"access plus 1 month\"
	ExpiresByType text/css \"access plus 1 months\"
</ifmodule>
#End Header expiration
	
#Disable hotlinking images with forbidden image option
<ifModule mod_rewrite.c>
	RewriteEngine on
	RewriteCond %{HTTP_REFERER} !^$
	RewriteCond %{HTTP_REFERER} !^http://(www\.)?".$server_name."/.*$ [NC]
	#RewriteRule \.(gif|jpg)$ – [F]
	#RewriteRule \.(gif|jpg)$ ".plugins_url('images/denied.png', __FILE__)." [R,L]
</ifModule>
#End Disable hotlinking images with forbidden image option

#Protect from spam bots
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteCond %{REQUEST_METHOD} POST
	RewriteCond %{REQUEST_URI} .wp-comments-post\.php*
	RewriteCond %{HTTP_REFERER} !.".$server_name.".* [OR]
	RewriteCond %{HTTP_USER_AGENT} ^$
	RewriteRule (.*) ^http://%{REMOTE_ADDR}/$ [R=301,L]
</IfModule>
#End Protect from spam bots

#Etags
<ifModule mod_headers.c>
	Header unset ETag
</ifModule>
FileETag None
#End Etags
	
#End Ibinc Optimization Code";

	$fp = fopen($filename, 'a+');
	$fcontent = fread($fp, "500000");
	if (stristr($fcontent, "#Ibinc Optimization Code")==false)
		fwrite($fp, $string);
	fclose($fp);
	
	/* Adding the js optimizations options */
	$ibinc_js['enable_min_js']='yes';
	$ibinc_js['input_maxfiles']='5';
	$ibinc_js['input_header']='';
	$ibinc_js['input_footer']='';
	$ibinc_js['input_direct']='admin-bar.js';
	$ibinc_js['input_ignore']='';
	add_option('ibinc_js',$ibinc_js);
	
	/* Adding the cache activation options and creating the cache directory and files*/
	$options = get_option('ibinc_cache');
	wp_clear_scheduled_hook('ibinc_cache_clean');
	if (!is_array($options)) {
		$options = array();
		$options['comment'] = 1;
		$options['archive'] = 1;
		$options['timeout'] = 1440;
		$options['redirects'] = 1;
		$options['notfound'] = 1;
		$options['clean_interval'] = 60;
		$options['gzip'] = 1;
		$options['store_compressed'] = 1;
		$options['expire_type'] = 'post';
		update_option('ibinc_cache', $options);
	}
	
	$buffer = ibinc_cache_generate_config($options);
	$file = @fopen(ABSPATH . 'wp-content/advanced-cache.php', 'wb');
	@fwrite($file, $buffer);
	@fclose($file);
	
	wp_mkdir_p(WP_CONTENT_DIR . '/cache/ibinc-cache');
	wp_schedule_event(time()+300, 'hourly', 'ibinc_cache_clean');
	
	/* Adding the cache code in config.php */
	$filename = fs_get_wp_config_path().'wp-config.php';
	$backup_filename = fs_get_wp_config_path().'wp-config-backup.php';
	
	$fp = fopen($filename, 'a+');
	$fcontent = fread($fp, "500000");
	fclose($fp);
	
	/* creating a backup config file */
	$fp = fopen($backup_filename, 'a+');
	fwrite($fp, $fcontent);
	fclose($fp);

	if (stristr($fcontent, "define('WP_CACHE', true);")==false){
		$fcontent=str_replace("/* That's all, stop editing! Happy blogging. */", "define('WP_CACHE', true);\n/* That's all, stop editing! Happy blogging. */", $fcontent);
		$fp = fopen($filename, 'w+');
		fwrite($fp, $fcontent);
		fclose($fp);
	}
}

/**
 * Removing the .htaccess code on deactivation
 *
 */
function ibinc_opt_deactivate(){
	/* Removing the admin settings variables */
	//delete_option ( 'ibinc_rem_generator' );for updatin purposes once the plugin is complete decomment
	//delete_option ( 'ibinc_rem_rsd' );for updatin purposes once the plugin is complete decomment
	//delete_option ( 'ibinc_rem_wlwmanifest' );for updatin purposes once the plugin is complete decomment

	
	/* Removing the .htaccess code */
	$filename = fs_get_wp_config_path().'.htaccess';
	$server_name = str_replace(array('http://','www'), '', $_SERVER['SERVER_NAME']);
	
	$fp = fopen($filename, 'a+');
	$fcontent = fread($fp, "500000");
	fclose($fp);
	
	$start=stripos($fcontent, '#Ibinc Optimization Code');
	$end=stripos($fcontent, '#End Ibinc Optimization Code')+25;
	$del_string=substr($fcontent,$start,$end);
	$fcontent=str_replace($del_string, "", $fcontent);
	
	$fp = fopen($filename, 'w+');
	fwrite($fp, $fcontent);
	fclose($fp);
	
	/* Clearing the cache settings and files */
	wp_clear_scheduled_hook('ibinc_cache_clean');
	// delete_option ( 'ibinc_cache' ); for updatin purposes once the plugin is complete decomment

	$file = @fopen(ABSPATH . 'wp-content/advanced-cache.php', 'wb');
	if ($file)
	{
		@fwrite($file, '');
		@fclose($file);
	}
	
	/* Removing the cache code from config.php */
	$filename = fs_get_wp_config_path().'wp-config.php';
	
	$fp = fopen($filename, 'a+');
	$fcontent = fread($fp, "500000");
	fclose($fp);
	
	$fcontent=str_replace("define('WP_CACHE', true);\n", "", $fcontent);
	
	$fp = fopen($filename, 'w+');
	fwrite($fp, $fcontent);
	fclose($fp);
}


/**
 * Finds the root directory path of the wordpress install
 *
 * @return string
 */
function fs_get_wp_config_path(){
	$base = dirname(__FILE__);
	$path = false;

	if (@file_exists(dirname(dirname($base))."/wp-config.php"))	{
		$path = dirname(dirname($base))."/wp-config.php";
	}
	else
		if (@file_exists(dirname(dirname(dirname($base)))."/wp-config.php"))		{
			$path = dirname(dirname(dirname($base)))."/wp-config.php";
		} else
			$path = false;

	if ($path != false){
			$path = str_replace("\\", "/", $path);
	}
	
	return str_replace('wp-config.php', '', $path);
}

/**
 * Writes into the plugin's log file
 *
 * @param string $text
 */
function ibinc_log($text)
{
	$file = fopen(ABSPATH . 'wp-content/plugins/ibinc-performance-optimizer/log.txt', 'wb');
	fwrite($file, $text . "\n");
	fclose($file);
}

/**
 * Invalidates the caching directory and deletes it
 *
 */
function ibinc_cache_invalidate(){
	global $ibinc_cache_invalidated;

	ibinc_log("ibinc_cache_invalidate> Called");

	if ($ibinc_cache_invalidated){
		ibinc_log("ibinc_cache_invalidate> Cache already invalidated");
		return;
	}

	if (!@touch(WP_CONTENT_DIR . '/cache/ibinc-cache/_global.dat')){
		ibinc_log("ibinc_cache_invalidate> Unable to touch cache/_global.dat");
	} else {
		ibinc_log("ibinc_cache_invalidate> Touched cache/_global.dat");
	}
	@unlink(WP_CONTENT_DIR . '/cache/ibinc-cache/_archives.dat');
	$ibinc_cache_invalidated = true;

}

/**
 * Invalidates the caching of a single post and eventually the home and archives if required
 * 
 * @param int $post_id
 */
function ibinc_cache_invalidate_post($post_id){
	global $ibinc_cache_invalidated_post_id;

	ibinc_log("ibinc_cache_invalidate_post(" . $post_id . ")> Called");

	if ($ibinc_cache_invalidated_post_id == $post_id){
		ibinc_log("ibinc_cache_invalidate_post(" . $post_id . ")> Post was already invalidated");
		return;
	}

	$options = get_option('ibinc_cache');

	if ($options['expire_type'] == 'none'){
		ibinc_log("ibinc_cache_invalidate_post(" . $post_id . ")> Invalidation disabled");
		return;
	}

	if ($options['expire_type'] == 'post'){
		$post = get_post($post_id);

		$link = get_permalink($post_id);
		ibinc_log('Permalink to invalidate ' . $link);
		// Remove 'http://', and for wordpress 'pretty URLs' strip trailing slash (e.g. 'http://my-site.com/my-post/' -> 'my-site.com/my-post')
		// The latter ensures existing cache files are still used if a wordpress admin just adds/removes a trailing slash to/from the permalink format
		$link = rtrim(substr($link, 7), '/');
		ibinc_log('Corrected permalink to invalidate ' . $link);
		$file = md5($link);
		ibinc_log('File basename to invalidate ' . $file);

		$path = WP_CONTENT_DIR . '/cache/ibinc-cache';
		$handle = @opendir($path);
		if ($handle){
			while ($f = readdir($handle)){
				if (substr($f, 0, 32) == $file){
					if (unlink($path . '/' . $f)) {
						ibinc_log('Deleted ' . $path . '/' . $f);
					} else {
						ibinc_log('Unable to delete ' . $path . '/' . $f);
					}
				}
			}
			closedir($handle);
		}

		$ibinc_cache_invalidated_post_id = $post_id;

		ibinc_log("ibinc_cache_invalidate_post(" . $post_id . ")> Post invalidated");

		if ($options['archive']) {
			ibinc_log("ibinc_cache_invalidate_post(" . $post_id . ")> Archive invalidation required");

			if (!@touch(WP_CONTENT_DIR . '/cache/ibinc-cache/_archives.dat')) {
				ibinc_log("ibinc_cache_invalidate_post(" . $post_id . ")> Unable to touch cache/_archives.dat");
			} else {
				ibinc_log("ibinc_cache_invalidate_post(" . $post_id . ")> Touched cache/_archives.dat");
			}
		}
		return;
	}

	if ($options['expire_type'] == 'all') {
		ibinc_log("ibinc_cache_invalidate_post(" . $post_id . ")> Full invalidation");
		ibinc_cache_invalidate();
		return;
	}
}

/**
 * Capture and register if a redirect is sent back from WP, so the cache can cache (or ignore) it.
 *
 * @param string $redirect_url
 * @param string $requested_url
 * @return String
 */
function ibinc_cache_redirect_canonical($redirect_url, $requested_url){
	global $ibinc_cache_redirect;

	$ibinc_cache_redirect = $redirect_url;

	return $redirect_url;
}

/**
 * Clearing the cache
 *
 */
function ibinc_cache_clean(){
	$invalidation_time = @filemtime(WP_CONTENT_DIR . '/cache/ibinc-cache/_global.dat');

	ibinc_log('start cleaning');

	$options = get_option('ibinc_cache');

	$timeout = $options['timeout']*60;
	if ($timeout == 0) return;

	$path = WP_CONTENT_DIR . '/cache/ibinc-cache';
	$time = time();

	$handle = @opendir($path);
	if (!$handle) {
		ibinc_log('unable to open cache dir');
		return;
	}

	while ($file = readdir($handle)) {
		if ($file == '.' || $file == '..' || $file[0] == '_') continue;

		ibinc_log('checking ' . $file . ' for cleaning');
		$t = @filemtime($path . '/' . $file);
		ibinc_log('file time ' . $t);
		if ($time - $t > $timeout || ($invalidation_time && $t < $invalidation_time)) {
			@unlink($path . '/' . $file);
			ibinc_log('cleaned ' . $file);
		}
	}
	closedir($handle);

	ibinc_log('end cleaning');
}

/**
 * Generates the content for the advanced-cache.php file
 *
 * @param array $options
 * @return string 
 */

function ibinc_cache_generate_config(&$options) {
	$buffer = '';

	$timeout = $options['timeout']*60;
	if ($timeout == 0) $timeout = 2000000000;

	$buffer = "<?php\n";
	$buffer .= '$ibinc_cache_path = "' . WP_CONTENT_DIR . '/cache/ibinc-cache/"' . ";\n";
	$buffer .= '$ibinc_cache_charset = "' . get_option('blog_charset') . '"' . ";\n";
	// Do not cache for commenters
	$buffer .= '$ibinc_cache_comment = ' . (isset($options['comment'])?'true':'false') . ";\n";
	// Ivalidate archives on post invalidation
	$buffer .= '$ibinc_cache_archive = ' . ($options['archive']?'true':'false') . ";\n";
	// Single page timeout
	$buffer .= '$ibinc_cache_timeout = ' . ($timeout) . ";\n";
	// Cache redirects?
	$buffer .= '$ibinc_cache_redirects = ' . (isset($options['redirects'])?'true':'false') . ";\n";
	// Cache page not found?
	$buffer .= '$ibinc_cache_notfound = ' . (isset($options['notfound'])?'true':'false') . ";\n";
	// Separate caching for mobile agents?
	$buffer .= '$ibinc_cache_mobile = ' . (isset($options['mobile'])?'true':'false') . ";\n";
	// WordPress mobile pack integration?
	$buffer .= '$ibinc_cache_plugin_mobile_pack = ' . (isset($options['plugin_mobile_pack'])?'true':'false') . ";\n";
	// Cache the feeds?
	$buffer .= '$ibinc_cache_feed = ' . (isset($options['feed'])?'true':'false') . ";\n";
	// Cache GET request with parameters?
	$buffer .= '$ibinc_cache_cache_qs = ' . (isset($options['cache_qs'])?'true':'false') . ";\n";
	// Strip query string?
	$buffer .= '$ibinc_cache_strip_qs = ' . (isset($options['strip_qs'])?'true':'false') . ";\n";
	// DO NOT cache the home?
	$buffer .= '$ibinc_cache_home = ' . (isset($options['home'])?'true':'false') . ";\n";
	// Disable last modified header
	$buffer .= '$ibinc_cache_lastmodified = ' . (isset($options['lastmodified'])?'true':'false') . ";\n";
	// Allow browser caching?
	$buffer .= '$ibinc_cache_browsercache = ' . (isset($options['browsercache'])?'true':'false') . ";\n";
	// Do not use cache if browser sends no-cache header?
	$buffer .= '$ibinc_cache_nocache = ' . (isset($options['nocache'])?'true':'false') . ";\n";

	if ($options['gzip']) $options['store_compressed'] = 1;

	$buffer .= '$ibinc_cache_gzip = ' . (isset($options['gzip'])?'true':'false') . ";\n";
	$buffer .= '$ibinc_cache_gzip_on_the_fly = ' . (isset($options['gzip_on_the_fly'])?'true':'false') . ";\n";
	$buffer .= '$ibinc_cache_store_compressed = ' . (isset($options['store_compressed'])?'true':'false') . ";\n";

	if (isset($options['reject']) && trim($options['reject']) != '') {
		$options['reject'] = str_replace(' ', "\n", $options['reject']);
		$options['reject'] = str_replace("\r", "\n", $options['reject']);
		$buffer .= '$ibinc_cache_reject = array(';
		$reject = explode("\n", $options['reject']);
		$options['reject'] = '';
		foreach ($reject as $uri)
		{
			$uri = trim($uri);
			if ($uri == '') continue;
			$buffer .= "\"" . addslashes(trim($uri)) . "\",";
			$options['reject'] .= $uri . "\n";
		}
		$buffer = rtrim($buffer, ',');
		$buffer .= ");\n";
	} else {
		$buffer .= '$ibinc_cache_reject = false;' . "\n";
	}

	if (isset($options['reject_agents']) && trim($options['reject_agents']) != '') {
		$options['reject_agents'] = str_replace(' ', "\n", $options['reject_agents']);
		$options['reject_agents'] = str_replace("\r", "\n", $options['reject_agents']);
		$buffer .= '$ibinc_cache_reject_agents = array(';
		$reject_agents = explode("\n", $options['reject_agents']);
		$options['reject_agents'] = '';
		foreach ($reject_agents as $uri) {
			$uri = trim($uri);
			if ($uri == '') continue;
			$buffer .= "\"" . addslashes(strtolower(trim($uri))) . "\",";
			$options['reject_agents'] .= $uri . "\n";
		}
		$buffer = rtrim($buffer, ',');
		$buffer .= ");\n";
	} else {
		$buffer .= '$ibinc_cache_reject_agents = false;' . "\n";
	}

	if (isset($options['reject_cookies']) && trim($options['reject_cookies']) != '') {
		$options['reject_cookies'] = str_replace(' ', "\n", $options['reject_cookies']);
		$options['reject_cookies'] = str_replace("\r", "\n", $options['reject_cookies']);
		$buffer .= '$ibinc_cache_reject_cookies = array(';
		$reject_cookies = explode("\n", $options['reject_cookies']);
		$options['reject_cookies'] = '';
		foreach ($reject_cookies as $c) {
			$c = trim($c);
			if ($c == '') continue;
			$buffer .= "\"" . addslashes(strtolower(trim($c))) . "\",";
			$options['reject_cookies'] .= $c . "\n";
		}
		$buffer = rtrim($buffer, ',');
		$buffer .= ");\n";
	} else {
		$buffer .= '$ibinc_cache_reject_cookies = false;' . "\n";
	}

	if (isset($options['mobile'])){
		if (!isset($options['mobile_agents']) || trim($options['mobile_agents']) == ''){
			$options['mobile_agents'] = "elaine/3.0\niphone\nipod\npalm\neudoraweb\nblazer\navantgo\nwindows ce\ncellphone\nsmall\nmmef20\ndanger\nhiptop\nproxinet\nnewt\npalmos\nnetfront\nsharp-tq-gx10\nsonyericsson\nsymbianos\nup.browser\nup.link\nts21i-10\nmot-v\nportalmmm\ndocomo\nopera mini\npalm\nhandspring\nnokia\nkyocera\nsamsung\nmotorola\nmot\nsmartphone\nblackberry\nwap\nplaystation portable\nlg\nmmp\nopwv\nsymbian\nepoc";
		}

		if (trim($options['mobile_agents']) != ''){
			$options['mobile_agents'] = str_replace(',', "\n", $options['mobile_agents']);
			$options['mobile_agents'] = str_replace("\r", "\n", $options['mobile_agents']);
			$buffer .= '$ibinc_cache_mobile_agents = array(';
			$mobile_agents = explode("\n", $options['mobile_agents']);
			$options['mobile_agents'] = '';
			foreach ($mobile_agents as $uri){
				$uri = trim($uri);
				if ($uri == '') continue;
				$buffer .= "\"" . addslashes(strtolower(trim($uri))) . "\",";
				$options['mobile_agents'] .= $uri . "\n";
			}
			$buffer = rtrim($buffer, ',');
			$buffer .= ");\n";
		} else {
			$buffer .= '$ibinc_cache_mobile_agents = false;' . "\n";
		}
	}

	$buffer .= "include(ABSPATH . 'wp-content/plugins/ibinc-performance-optimizer/ibinc_cache.php');\n";

	return $buffer;
}
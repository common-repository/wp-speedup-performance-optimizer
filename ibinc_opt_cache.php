<?php
/**
 * Class for plugin options administration
 */

class Ibinc_OP_Cache {

	/**
	 * Info message
	 * @var string
	 */
	var $info_message;

	/**
	 * Error message
	 * @var string
	 */
	var $error_message;
	/**
	 * Construct function
	 */
	function __construct() {
		$this->info_message = '';
		$this->error_message = '';
	}
	
	/**
	 * Register wordpress actions for administration page 
	 */
	function register_for_actions_and_filters() {
		add_action ('admin_menu', array (&$this,'admin_plugin_menu'));
	}

	/**
	 * Sets plugin menu items in wordpress administration menu
	 */
	function admin_plugin_menu() {
		add_submenu_page (plugin_dir_path ( __FIlE__ ) . 'ibinc_opt_settings.php', __( 'Cache Settings' ), __( 'Cache Settings' ), 1, __FILE__, array (&$this,'admin_handle_other_options'));

	}

	/**
	 * Sets or delete wordpress options for plugin administration 
	 * 
	 * @param string $info_message
	 */
	function admin_handle_other_options($info_message = '') {
		$info_message='';
		$options = get_option('ibinc_cache');
	
	
	
		if (isset($_POST['clean'])){
		    $this->ibinc_delete_path(WP_CONTENT_DIR . '/cache/ibinc-cache');
		}
		
		$error = false;
		if (isset($_POST['save'])) {
		    if (!check_admin_referer()) die('No hacking please');
		    
		    $tmp = stripslashes_deep($_POST['options']);
		
		    if ($options['gzip'] != $tmp['gzip'])
		    {
		       $this->ibinc_delete_path(WP_CONTENT_DIR . '/cache/ibinc-cache');
		    }
		
		    $options = $tmp;
		    
		    if (!is_numeric($options['timeout'])) $options['timeout'] = 60;
		    $options['timeout'] = (int)$options['timeout'];
		
		    if (!is_numeric($options['clean_interval'])) $options['clean_interval'] = 60;
		    $options['clean_interval'] = (int)$options['clean_interval'];
		
		    $buffer = ibinc_cache_generate_config($options);
		    
		    $file = @fopen(ABSPATH . 'wp-content/advanced-cache.php', 'w');
		    if ($file) {
		    @fwrite($file, $buffer);
		    @fclose($file);
		    }
		    else {
		        $error = true;
		    }
		    update_option('ibinc_cache', $options);
		
		    // When the cache does not expire
		    if ($options['expire_type'] == 'none')
		    {
		        @unlink(WP_CONTENT_DIR . '/cache/ibinc-cache/_global.dat');
		        @unlink(WP_CONTENT_DIR . '/cache/ibinc-cache/_archives.dat');
		    }
		} else {
		    if ($options['mobile_agents'] == '')
		    {
		        $options['mobile_agents'] = "elaine/3.0\niphone\nipod\npalm\neudoraweb\nblazer\navantgo\nwindows ce\ncellphone\nsmall\nmmef20\ndanger\nhiptop\nproxinet\nnewt\npalmos\nnetfront\nsharp-tq-gx10\nsonyericsson\nsymbianos\nup.browser\nup.link\nts21i-10\nmot-v\nportalmmm\ndocomo\nopera mini\npalm\nhandspring\nnokia\nkyocera\nsamsung\nmotorola\nmot\nsmartphone\nblackberry\nwap\nplaystation portable\nlg\nmmp\nopwv\nsymbian\nepoc";
		    }
		}
		$this->info_message = $info_message;
		$this->error_message = '';
		$this->display_admin_handle_other_options ();
	}

	/**
	 * Builds database options template page
	 * 
	 */
	function display_admin_handle_other_options() {
		$options = get_option('ibinc_cache');
	?>
		<?php if (!defined('WP_CACHE') || !WP_CACHE) { ?>
		<div class="error">
		    <?php _e('You must add to the file wp-config.php (at its beginning after the &lt;?php) the line of code: <code>define(\'WP_CACHE\', true);</code>.', 'IBINC_POe'); ?>
		</div>
		<?php } ?>
		<div class="wrap" class="paddingTop50">
			<h3>Cache Options</h3>
			<?php
			    if ($error)
			    {
			        echo __('<p><strong>Options saved BUT not active because Ibinc Cache was not able to update the file wp-content/advanced-cache.php (is it writable?).</strong></p>', 'IBINC_PO');
			    }
			?>
			<?php
			    if (!wp_mkdir_p(WP_CONTENT_DIR . '/cache/ibinc-cache'))
			    {
			        echo __('<p><strong>Ibinc Cache was not able to create the folder "wp-content/cache/ibinc-cache". Make it manually setting permissions to 777.</strong></p>', 'IBINC_PO');
			    }
			?>
			<?php $this->display_messages(); ?>

	    	
			<form method="post" action="">
			<?php wp_nonce_field(); ?>
			
			<p class="submit">
			    <input class="button-primary" type="submit" name="clean" value="<?php _e('Clear cache', 'IBINC_PO'); ?>">
			</p>
			
			<h3><?php _e('Cache status', 'IBINC_PO'); ?></h3>
			<table class="form-table">
			<tr valign="top">
			    <th><?php _e('Files in cache (valid and expired)', 'IBINC_PO'); ?></th>
			    <td><?php echo $this->ibinc_file_count(); ?></td>
			</tr>
			<tr valign="top">
			    <th><?php _e('Cleaning process', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <?php _e('Next run on: ', 'IBINC_PO'); ?>
			        <?php
			        $next_scheduled = wp_next_scheduled('ibinc_cache_clean');
			        if (empty($next_scheduled)) echo '? (read below)';
			        else echo gmdate(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled + get_option('gmt_offset')*3600);
			        ?>
			    </td>
			</tr>
			</table>
			
			
			<h3><?php _e('Configuration', 'IBINC_PO'); ?></h3>
			
			<table class="form-table">
			
			<tr valign="top">
			    <th><?php _e('Cached pages timeout', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <input type="text" size="5" name="options[timeout]" value="<?php echo htmlspecialchars($options['timeout']); ?>"/>
			        (<?php _e('minutes', 'IBINC_PO'); ?>)
			        <div class="hints">
			        <?php _e('Minutes a cached page is valid and served to users. A zero value means a cached page is
			        valid forever.', 'IBINC_PO'); ?>
			        <?php _e('If a cached page is older than specified value (expired) it is no more used and
			        will be regenerated on next request of it.', 'IBINC_PO'); ?>
			        <?php _e('720 minutes is half a day, 1440 is a full day and so on.', 'IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			
			<tr valign="top">
			    <th><?php _e('Cache invalidation mode', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <select name="options[expire_type]">
			            <option value="all" <?php echo ($options['expire_type'] == 'all')?'selected':''; ?>><?php _e('All cached pages', 'IBINC_PO'); ?></option>
			            <option value="post" <?php echo ($options['expire_type'] == 'post')?'selected':''; ?>><?php _e('Only modified posts', 'IBINC_PO'); ?></option>
			            <!--<option value="post_strictly" <?php echo ($options['expire_type'] == 'post_strictly')?'selected':''; ?>><?php _e('Only modified pages', 'IBINC_PO'); ?></option>-->
			            <option value="none" <?php echo ($options['expire_type'] == 'none')?'selected':''; ?>><?php _e('Nothing', 'IBINC_PO'); ?></option>
			        </select>
			        <br />
			        <input type="checkbox" name="options[archive]" value="1" <?php echo $options['archive']?'checked':''; ?>/>
			        <?php _e('Invalidate home, archives, categories on single post invalidation', 'IBINC_PO'); ?>
			        <br />
			        <div class="hints">
			        <?php _e('"Invalidation" is the process of deleting cached pages when they are no more valid.', 'IBINC_PO'); ?>
			        <?php _e('Invalidation process is started when blog contents are modified (new post, post update, new comment,...) so
			        one or more cached pages need to be refreshed to get that new content.', 'IBINC_PO'); ?>
			        <?php _e('A new comment submission or a comment moderation is considered like a post modification
			        where the post is the one the comment is relative to.', 'IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			
			<tr valign="top">
			    <th><?php _e('Disable cache for commenters', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <input type="checkbox" name="options[comment]" value="1" <?php echo $options['comment']?'checked':''; ?>/>
			        <div class="hints">
			        <?php _e('When users leave comments, WordPress show pages with their comments even if in moderation
			        (and not visible to others) and pre-fills the comment form.', 'IBINC_PO'); ?>
			        <?php _e('If you want to keep those features, enable this option.', 'IBINC_PO'); ?>
			        <?php _e('The caching system will be less efficient but the blog more usable.', 'IBINC_PO'); ?>
			        </div>
			
			    </td>
			</tr>
			
			<tr valign="top">
			    <th><?php _e('Feeds caching', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <input type="checkbox" name="options[feed]" value="1" <?php echo $options['feed']?'checked':''; ?>/>
			        <div class="hints">
			        <?php _e('When enabled the blog feeds will be cache as well.', 'IBINC_PO'); ?>
			        <?php _e('Usually this options has to be left unchecked but if your blog is rather static,
			        you can enable it and have a bit more efficiency', 'IBINC_PO'); ?>
			        </div>
			    </td>    
			</tr>
			
			<tr valign="top">
			    <th><?php _e('Allow browser caching', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <input type="checkbox" name="options[browsercache]" value="1" <?php echo $options['browsercache']?'checked':''; ?>/>
			        <div class="hints">
			        <?php _e('Allow browser caching.','IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			</table>
			<p class="submit">
			    <input class="button-primary" type="submit" name="save" value="<?php _e('Update'); ?>">
			</p>
			
			<h3><?php _e('Configuration for mobile devices', 'IBINC_PO'); ?></h3>
			<table class="form-table">
			<tr valign="top">
			    <th><?php _e('WordPress Mobile Pack', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <input type="checkbox" name="options[plugin_mobile_pack]" value="1" <?php echo $options['plugin_mobile_pack']?'checked':''; ?>/>
			        <div class="hints">
			           <?php _e('Enbale integration with <a href="http://wordpress.org/extend/plugins/wordpress-mobile-pack/">WordPress Mobile Pack</a> plugin. If you have that plugin, the cache system use it to detect mobile devices and caches saparately
			    the different pages generated.', 'IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			<tr valign="top">
			    <th><?php _e('Detect mobile devices', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <input type="checkbox" name="options[mobile]" value="1" <?php echo $options['mobile']?'checked':''; ?>/>
			        <div class="hints">
			        <?php _e('When enabled mobile devices will be detected and the cached page stored under different name.', 'IBINC_PO'); ?>
			        <?php _e('This makes blogs with different themes for mobile devices to work correctly.', 'IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			
			<tr valign="top">
			    <th><?php _e('Mobile agent list', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <textarea wrap="off" rows="4" cols="70" name="options[mobile_agents]"><?php echo htmlspecialchars($options['mobile_agents']); ?></textarea>
			        <div class="hints">
			        <?php _e('One per line mobile agents to check for when a page is requested.', 'IBINC_PO'); ?>
			        <?php _e('The mobile agent string is matched against the agent a device is sending to the server.', 'IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			</table>
			<p class="submit">
			    <input class="button-primary" type="submit" name="save" value="<?php _e('Update'); ?>">
			</p>
			
			
			<h3><?php _e('Compression', 'IBINC_PO'); ?></h3>
			
			<?php if (!function_exists('gzencode') || !function_exists('gzinflate')) { ?>
			
			<p><?php _e('Your hosting space has not the "gzencode" or "gzinflate" function, so no compression options are available.', 'IBINC_PO'); ?></p>
			
			<?php } else { ?>
			
			<table class="form-table">
			<tr valign="top">
			    <th><?php _e('Store compressed pages', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <input type="checkbox" name="options[store_compressed]" value="1" <?php echo $options['store_compressed']?'checked':''; ?>
			            onchange="jQuery('input[name=&quot;options[gzip]&quot;]').attr('disabled', !this.checked)" />
			        
			        <div class="hints">
			        <?php _e('Enable this option to minimize disk space usage and make sending of compressed pages possible with the option below.', 'IBINC_PO'); ?>
			        <?php _e('The cache will be a little less performant.', 'IBINC_PO'); ?>
			        <?php _e('Leave the options disabled if you note malfunctions, like blank pages.', 'IBINC_PO'); ?>
			        <br />
			        <?php _e('If you enable this option, the option below will be available as well.', 'IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			
			<tr valign="top">
			    <th><?php _e('Send compressed pages', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <input type="checkbox" name="options[gzip]" value="1" <?php echo $options['gzip']?'checked':''; ?>
			            <?php echo $options['store_compressed']?'':'disabled'; ?> />
			        
			        <div class="hints">
			        <?php _e('When possible (i.e. if the browser accepts compression and the page was cached compressed) the page will be sent compressed to save bandwidth.', 'IBINC_PO'); ?>
			        <?php _e('Only the textual part of a page can be compressed, not images, so a photo
			        blog will consume a lot of bandwidth even with compression enabled.', 'IBINC_PO'); ?>
			        <?php _e('Leave the options disabled if you note malfunctions, like blank pages.', 'IBINC_PO'); ?>
			        <br />
			        <?php _e('If you enable this option, the option below will be available as well.', 'IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			
			<tr valign="top">
			    <th><?php _e('On-the-fly compression', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <input type="checkbox" name="options[gzip_on_the_fly]" value="1" <?php echo $options['gzip_on_the_fly']?'checked':''; ?> />
			        
			        <div class="hints">
			        <?php _e('When possible (i.e. if the browser accepts compression) use on-the-fly compression to save bandwidth when sending pages which are not compressed.', 'IBINC_PO'); ?>
			        <?php _e('Serving of such pages will be a little less performant.', 'IBINC_PO'); ?>
			        <?php _e('Leave the options disabled if you note malfunctions, like blank pages.', 'IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			</table>
			<p class="submit">
			    <input class="button-primary" type="submit" name="save" value="<?php _e('Update'); ?>">
			</p>
			<?php } ?>
			
			
			<h3><?php _e('Advanced options', 'IBINC_PO'); ?></h3>
			
			<table class="form-table">
			<tr valign="top">
			    <th><?php _e('Translation', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <input type="checkbox" name="options[notranslation]" value="1" <?php echo $options['notranslation']?'checked':''; ?>/>
			        
			        <div class="hints">
			        <?php _e('DO NOT show this panel translated.', 'IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			
			<tr valign="top">
			    <th><?php _e('Disable Last-Modified header', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <input type="checkbox" name="options[lastmodified]" value="1" <?php echo $options['lastmodified']?'checked':''; ?>/>
			        
			        <div class="hints">
			        <?php _e('Disable some HTTP headers (Last-Modified) which improve performances but some one is reporting they create problems which some hosting configurations.','IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			
			<tr valign="top">
			    <th><?php _e('Home caching', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <input type="checkbox" name="options[home]" value="1" <?php echo $options['home']?'checked':''; ?>/>
			        
			        <div class="hints">
			        <?php _e('DO NOT cache the home page so it is always fresh.','IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			
			<tr valign="top">
			    <th><?php _e('Redirect caching', 'IBINC_PO'); ?></th>
			    <td>
			        <input type="checkbox" name="options[redirects]" value="1" <?php echo $options['redirects']?'checked':''; ?>/>
			        <br />
			        <?php _e('Cache WordPress redirects.', 'IBINC_PO'); ?>
			        <?php _e('WordPress sometime sends back redirects that can be cached to avoid further processing time.', 'IBINC_PO'); ?>
			    </td>
			</tr>
			
			<tr valign="top">
			    <th><?php _e('Page not found caching (HTTP 404)', 'IBINC_PO'); ?></th>
			    <td>
			        <input type="checkbox" name="options[notfound]" value="1" <?php echo $options['notfound']?'checked':''; ?>/>
			    </td>
			</tr>
			
			<tr valign="top">
			    <th><?php _e('Strip query string', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <input type="checkbox" name="options[strip_qs]" value="1" <?php echo $options['strip_qs']?'checked':''; ?>/>
			        
			        <div class="hints">
			           <?php _e('This is a really special case, usually you have to kept it disabled. When enabled, URL with query string will be
			    reduced removing the query string. So the URL http://www.domain.com/post-title and
			    http://www.domain.com/post-title?a=b&amp;c=d are cached as a single page.<br />
			    Setting this option disable the next one.', 'IBINC_PO'); ?>
			          <br />
			        <?php _e('<strong>Many plugins can stop to work correctly with this option enabled
			        (eg. my <a href="http://www.satollo.net/plugins/newsletter">Newsletter plugin</a>)</strong>', 'IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			
			<tr valign="top">
			    <th><?php _e('URL with parameters', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <input type="checkbox" name="options[cache_qs]" value="1" <?php echo $options['cache_qs']?'checked':''; ?>/>
			        
			        <div class="hints">
			        <?php _e('Cache requests with query string (parameters).', 'IBINC_PO'); ?>
			        <?php _e('This option has to be enabled for blogs which have post URLs with a question mark on them.', 'IBINC_PO'); ?>
			        <?php _e('This option is disabled by default because there is plugins which use
			        URL parameter to perform specific action that cannot be cached', 'IBINC_PO'); ?>
			        <?php _e('For who is using search engines friendly permalink format is safe to
			        leave this option disabled, no performances will be lost.', 'IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			
			<tr valign="top">
			    <th><?php _e('Allow browser to bypass cache', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <input type="checkbox" name="options[nocache]" value="1" <?php echo $options['nocache']?'checked':''; ?>/>
			        
			        <div class="hints">
			        <?php _e('Do not use cache if browser sends no-cache header (e.g. on explicit page reload).','IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			</table>
			
			
			<h3><?php _e('Filters', 'IBINC_PO'); ?></h3>
			<p>
			    <?php _e('Here you can: exclude pages and posts from the cache, specifying their address (URI); disable cache for specific
			    User Agents (browsers, bot, mobile devices, ...); disable the cache for users that have specific cookies.', 'IBINC_PO'); ?>
			</p>
			
			<table class="form-table">
			<tr valign="top">
			    <th><?php _e('URI to reject', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <textarea wrap="off" rows="5" cols="70" name="options[reject]"><?php echo htmlspecialchars($options['reject']); ?></textarea>
			        
			        <div class="hints">
			        <?php _e('Write one URI per line, each URI has to start with a slash.', 'IBINC_PO'); ?>
			        <?php _e('A specified URI will match the requested URI if the latter starts with the former.', 'IBINC_PO'); ?>
			        <?php _e('If you want to specify a stric matching, surround the URI with double quotes.', 'IBINC_PO'); ?>
			
			        <?php
			        $languages = get_option('gltr_preferred_languages');
			        if (is_array($languages))
			        {
			            echo '<br />';
			            $home = get_option('home');
			            $x = strpos($home, '/', 8); // skips http://
			            $base = '';
			            if ($x !== false) $base = substr($home, $x);
			            echo 'It seems you have Global Translator installed. The URI prefixes below can be added to avoid double caching of translated pages:<br />';
			            foreach($languages as $l) echo $base . '/' . $l . '/ ';
			        }
			        ?>
			        </div>
			    </td>
			</tr>
			
			<tr valign="top">
			    <th><?php _e('Agents to reject', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <textarea wrap="off" rows="5" cols="70" name="options[reject_agents]"><?php echo htmlspecialchars($options['reject_agents']); ?></textarea>
			        
			        <div class="hints">
			        <?php _e('Write one agent per line.', 'IBINC_PO'); ?>
			        <?php _e('A specified agent will match the client agent if the latter contains the former. The matching is case insensitive.', 'IBINC_PO'); ?>
			        </div>
			    </td>
			</tr>
			
			<tr valign="top">
			    <th><?php _e('Cookies matching', 'IBINC_PO'); ?><span class="more-help">[?]</span></th>
			    <td>
			        <textarea wrap="off" rows="5" cols="70" name="options[reject_cookies]"><?php echo htmlspecialchars($options['reject_cookies']); ?></textarea>
			        
			        <div class="hints">
			        <?php _e('Write one cookie name per line.', 'IBINC_PO'); ?>
			        <?php _e('When a specified cookie will match one of the cookie names sent bby the client the cache stops.', 'IBINC_PO'); ?>
			        <?php if (defined('FBC_APP_KEY_OPTION')) { ?>
			        <br />
			        <?php _e('It seems you have Facebook Connect plugin installed. Add this cookie name to make it works
			        with teh cache system:', 'IBINC_PO'); ?>
			        <br />
			        <strong><?php echo get_option(FBC_APP_KEY_OPTION); ?>_user</strong>
			        <?php } ?>
			        </div>
			    </td>
			</tr>
			
			</table>
			
			<p class="submit">
			    <input class="button-primary" type="submit" name="save" value="<?php _e('Update'); ?>">
			</p>
			</form>
		</div>
	<?php
	}
	
	/**
	 * Counts the number of files cached in the cache directoy
	 * returns int
	 */
	function ibinc_file_count(){
		$count = 0;
		if ($handle = @opendir(WP_CONTENT_DIR . '/cache/ibinc-cache')){
			while ($file = readdir($handle)){
				if ($file != '.' && $file != '..'){
					$count++;
				}
			}
			closedir($handle);
		}
		return $count;
	}
	
	/**
	 * Removes a directory and it's content.
	 * @param string $path
	 * returns string
	 */
	function ibinc_delete_path($path){
		if ($path == null) return;
		$handle = @opendir($path);
		if ($handle){
			while ($file = readdir($handle)){
				if ($file != '.' && $file != '..'){
					@unlink($path . '/' . $file);
				}
			}
			closedir($handle);
		}
	}
	
	/**
	 * Builds template for message holder
	 */
	function display_messages() {
		if (isset ( $this->info_message ) && trim ( $this->info_message ) != '') {
			echo '<div id="message" class="updated fade"><p><strong>' . $this->info_message . '</strong></p></div>';
		}
		if (isset ( $this->error_message ) && trim ( $this->error_message ) != '') {
			echo '<div id="message" class="error fade"><p><strong>' . $this->error_message . '</strong></p></div>';
		}
	}
}
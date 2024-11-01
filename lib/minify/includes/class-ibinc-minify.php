<?php
/**
 * Copyright (c) 2012 Khang Minh <betterwp.net>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * This is a wrapper function to help you convert a normal source to a minified source.
 *
 * Please do not use this function before WordPress has been initialized. Otherwise, you might get a fatal error.
 *
 * @param string $src The source file you would like to convert
 * @see http://betterwp.net/wordpress-plugins/ibinc-minify/ for more information
 */
function ibinc_minify($src)
{
	global $ibinc_minify;

	return $ibinc_minify->get_minify_src($ibinc_minify->process_media_source($src));
}

if (!class_exists('IBINC_FRAMEWORK'))
	require_once('class-ibinc-framework.php');

class IBINC_MINIFY extends IBINC_FRAMEWORK {

	/**
	 * Hold all scripts to be printed in <head> section
	 */
	var $header_scripts = array(array()), $header_l10n = array(), $header_dynamic = array(), $wp_scripts_done = array();

	/**
	 * Hold all scripts to be printed just before </body>
	 */
	var $footer_scripts = array(array()), $footer_l10n = array(), $footer_dynamic = array();

	/**
	 * Determine positions to manually put scripts in
	 */
	var $print_positions = array('header' => array(), 'footer' => array(), 'direct' => array(), 'ignore' => array());
	
	/**
	 * Are scripts still queueable?
	 */
	var $queueable = true;
	
	/**
	 * Queued styles to be printed
	 */
	var $styles = array(array()), $media_styles = array('print' => array()), $dynamic_styles = array(), $wp_styles_done = array();
	 
	/**
	 * Are we still able to print styles?
	 */
	var $printable = true;
	 
	/**
	 * Other options
	 */
	 var $ver = '', $base = '', $cache_time = 1800, $buster = '';

	/**
	 * Constructor
	 */	
	function __construct($version = '1.2.0')
	{
		global $wp_scripts;
		// Plugin's title
		$this->plugin_title = 'BetterWP Minify';
		// Plugin's version
		$this->set_version($version);
		$this->set_version('5.1.6', 'php');
		// Basic version checking
		if (!$this->check_required_versions())
			return;

		// The default options
		$options = array(
			'input_minurl' => '',
			'input_cache_dir' => '',
			'input_maxage' => 30,
			'input_custom_buster' => '',
			'enable_min_css' => 'yes',
			'enable_bloginfo' => 'yes',
			'select_buster_type' => 'none',
			'select_time_type' => 60
		);
		$ibinc_js=get_option('ibinc_js');
		$options['enable_min_js']=$ibinc_js['enable_min_js'];
		$options['input_maxfiles']=$ibinc_js['input_maxfiles'];
		$options['input_header']=$ibinc_js['input_header'];
		$options['input_footer']=$ibinc_js['input_footer'];
		$options['input_direct']=$ibinc_js['input_direct'];
		$options['input_ignore']=$ibinc_js['input_ignore'];

		// Super admin only options
		$this->site_options = array('input_minurl', 'input_cache_dir');

		$this->build_properties('IBINC_MINIFY', 'ibinc-minify', $options, 'BetterWP Minify', dirname(dirname(__FILE__)) . '/ibinc-minify.php', 'http://betterwp.net/wordpress-plugins/ibinc-minify/', false);

		$this->add_option_key('IBINC_MINIFY_OPTION_GENERAL', 'ibinc_minify_general', __('Better WordPress Minify Settings', 'ibinc-minify'));
		add_action('init', array($this, 'default_minurl'));
		add_action('init', array($this, 'init'));
	}

	function default_minurl()
	{
		$this->options_default['input_minurl'] = apply_filters('ibinc_minify_min_dir', plugin_dir_url($this->plugin_file) . 'min/');	
	}

	function init_properties()
	{
		$this->parse_positions();
		$this->get_base();
		$this->ver = get_bloginfo('version');
		$this->cache = (int) $this->options['input_maxage'] * (int) $this->options['select_time_type'];
		$this->options['input_cache_dir'] = empty($this->options['input_cache_dir']) ? $this->get_cache_dir() : $this->options['input_cache_dir'];
		$this->buster = $this->get_buster($this->options['select_buster_type']);
	}

	private static function is_loadable()
	{
		// Ignore Geomashup for now
		if (!empty($_GET['geo_mashup_content']) && 'render-map' == $_GET['geo_mashup_content'])
			return false;
		return true;
	}

	function add_hooks()
	{
		// Certain plugins use a single file to show contents, which doesn't make use of wp_head and wp_footer action
		if (!self::is_loadable())
			return;

		// Allow other developers to use IBINC Minify inside wp-admin, be very careful :-)
		$allowed_in_admin = apply_filters('ibinc_minify_allowed_in_admin', false);

		if ((!is_admin() || (is_admin() && $allowed_in_admin)))
		{
			// Minify scripts if needed
			if ('yes' == $this->options['enable_min_js'])
			{
				add_filter('print_scripts_array', array($this, 'minify_scripts'));
				// Hook to common head and footer actions
				add_action('wp_head', array($this, 'print_header_scripts'), 9);
				add_action('wp_head', array($this, 'print_dynamic_header_scripts'), 9);
				add_action('wp_footer', array($this, 'print_footer_scripts'), 100);
				add_action('wp_footer', array($this, 'print_dynamic_footer_scripts'), 100);
				add_action('login_head', array($this, 'print_header_scripts'));
				add_action('login_head', array($this, 'print_dynamic_header_scripts'));
				add_action('login_footer', array($this, 'print_footer_scripts'), 100);
				add_action('login_footer', array($this, 'print_dynamic_footer_scripts'), 100);
				add_action('admin_head', array($this, 'print_header_scripts'), 9);
				add_action('admin_head', array($this, 'print_dynamic_header_scripts'), 9);
				add_action('ibinc_minify_before_header_scripts', array($this, 'print_header_scripts_l10n'));
				add_action('ibinc_minify_before_footer_scripts', array($this, 'print_footer_scripts_l10n'), 100);
			}
			
			// Minify styles if needed
			if ('yes' == $this->options['enable_min_css'])
			{
				add_filter('print_styles_array', array($this, 'minify_styles'));
				add_action('wp_head', array($this, 'print_styles'), 8);
				add_action('wp_head', array($this, 'print_media_styles'), 8);
				add_action('wp_head', array($this, 'print_dynamic_styles'), 8);
				add_action('login_head', array($this, 'print_styles'));
				add_action('login_head', array($this, 'print_media_styles'));
				add_action('login_head', array($this, 'print_dynamic_styles'));
				add_action('admin_head', array($this, 'print_styles'), 8);
				add_action('admin_head', array($this, 'print_media_styles'), 8);
				add_action('admin_head', array($this, 'print_dynamic_styles'), 8);
			}
		}

		if ('yes' == $this->options['enable_bloginfo'])
		{
			add_filter('stylesheet_uri', array($this, 'minify_item'));
			add_filter('locale_stylesheet_uri', array($this, 'minify_item'));
		}
	}
	
	

	function get_cache_dir($minurl = '')
	{
		global $current_blog;

		if (empty($minurl))
			$minurl = $this->options['input_minurl'];
		$temp = @parse_url($minurl);
		if (isset($temp['scheme']) && isset($temp['host']))
		{
			$port = (!empty($temp['port'])) ? ':' . $temp['port'] : '';
			$site_url = $temp['scheme'] . '://' . $temp['host'] . $port;
			$guess_cache = str_replace($site_url, '', $minurl);
		}
		else
			$guess_cache = $minurl;
		// @since 1.0.1
		$multisite_path = (isset($current_blog->path) && '/' != $current_blog->path) ? $current_blog->path : '';
		$guess_cache = str_replace($multisite_path, '', dirname($guess_cache));
		$guess_cache = trailingslashit($_SERVER['DOCUMENT_ROOT']) . trim($guess_cache, '/') . '/cache/';
		return apply_filters('ibinc_minify_cache_dir', str_replace('\\', '/', $guess_cache));
	}

	private static function flush_cache($cache_dir)
	{
		$cache_dir = trailingslashit($cache_dir);
		$deleted = 0;
		if (is_dir($cache_dir))
		{
			if ($dh = opendir($cache_dir))
			{
				while (($file = readdir($dh)) !== false)
				{
					if (preg_match('/^minify_[a-z0-9_\-\.,]+(\.gz)?$/i', $file))
					{
						@unlink($cache_dir . $file);
						$deleted++;
					}
				}
				closedir($dh);
			}
		}
		return $deleted;
	}

	function parse_positions()
	{
		$positions = array(
			'header' 	=> $this->options['input_header'],
			'footer' 	=> $this->options['input_footer'],
			'direct'	=> $this->options['input_direct'],
			'ignore'	=> $this->options['input_ignore']
		);

		foreach ($positions as &$position)
		{
			if (!empty($position))
			{
				$position = explode("\n", $position);
				$position = array_map('trim', $position);
			}
		}				
		
		$this->print_positions = $positions;
	}

	function get_base()
	{
		$temp = @parse_url(get_site_option('siteurl'));
		$port = (!empty($temp['port'])) ? ':' . $temp['port'] : '';
		$site_url = $temp['scheme'] . '://' . $temp['host'] . $port;
		$raw_base = trim(str_replace($site_url, '', get_site_option('siteurl')), '/');
		/* More filtering will ba added in future */
		$this->base = $raw_base;
	}

	function get_buster($type)
	{
		switch ($type)
		{
			case 'mtime':
				if (file_exists($this->options['input_cache_dir']))
					return filemtime($this->options['input_cache_dir']);
			break;

			case 'wpver':
				return $this->ver;
			break;

			case 'tver':
				$theme = get_theme_data(STYLESHEETPATH . '/style.css');
				if (!empty($theme['Version']))
					return $theme['Version'];
				else
					return '';
			break;

			case 'custom':
				return $this->options['input_custom_buster'];
			break;

			case 'none':
			default:
				if (is_admin())
					return __('empty', 'ibinc-minify');
				else
					return '';
			break;
		}
	}
	
	function is_in($handle, $position = 'header')
	{
		if (!isset($this->print_positions[$position]) || !is_array($this->print_positions[$position]))
			return false;
		if (in_array($handle, $this->print_positions[$position]))
			return true;
	}

	function ignores_style($handle, $temp, $deps)
	{
		if (!is_array($deps) || 0 == sizeof($deps) || 'wp' == $this->are_deps_added($handle, ''))
		{
			$temp[] = $handle;
			$this->wp_styles_done[] = $handle;
		}
		else if (!in_array($handle, $this->dynamic_styles))
			$this->dynamic_styles[] = $handle;
	}

	/**
	 * Check if a sciprt has been localized using wp_localize_script()
	 *
	 * @see wp-includes/functions.wp-scripts.php
	 */
	function is_l10n($handle)
	{
		global $wp_scripts;
		// Since 3.3, 'l10n' has been changed into 'data'
		if (isset($wp_scripts->registered[$handle]->extra['l10n']) || isset($wp_scripts->registered[$handle]->extra['data']))
			return true;
		return false;
	}

	/**
	 * Check if media source is local
	 */
	function is_local($src = '')
	{
		$url = @parse_url($src);
		$blog_url = @parse_url(get_option('home'));
		if (false === $url)
			return false;
		// If scheme is set
		if (isset($url['scheme']))
		{
			if (false === strpos($url['host'], $blog_url['host']))
				return false;
			return true;
		}
		else
			// Probably a relative link
			return true;
	}

	/**
	 * Make sure the source is valid.
	 *
	 * @since 1.0.3
	 */
	function is_source_static($src = '')
	{
		// Source that doesn't have .css or .js extesion is dynamic
		if (!preg_match('#[^,]+\.(css|js)$#ui', $src))
			return false;
		// Source that contains ?, =, & is dynamic
		if (strpos($src, '?') === false && strpos($src, '=') === false && strpos($src, '&') === false)
			return true;
		return false;
	}

	/**
	 * Make sure the media file is in expected format before being added to our minify string
	 */
	function process_media_source($src = '')
	{
		$src = trim($src);
		// Absolute url
		if (0 === strpos($src, 'http'))
		{
			// We will need to handle both http and https, even if site URL is still set to http
			$tmp_src = str_replace(array(get_option('siteurl'), str_replace('http://', 'https://', get_option('siteurl'))), '', $src);
			// If there was no difference between tmp_src and src, we need to loop through the base
			if ($tmp_src == $src && !empty($this->base))
			{
				$base_cpns = explode('/', preg_replace('/[\/]+/i', '/', $this->base));
				array_pop($base_cpns);
				foreach ($base_cpns as $key => $cpn)
				{
					$cpn_path = '/' . $cpn;
					for ($i = 0; $i < $key; $i++)
						$cpn_path = '/' . $base_cpns[$i] . $cpn_path;
					$cpn_url = 'http://' . $_SERVER['HTTP_HOST'] . $cpn_path;
					$src = str_replace(array($cpn_url, str_replace('http://', 'https://', $cpn_url)), '', $src);
				}

				$src = $cpn_path . $src;
			}
			else
				$src = $this->base . $tmp_src;
		}
		// Relative absolute url from root
		else if ('/' === substr($src, 0, 1))
			// Add base for wp-includes and wp-admin directory
			if (false !== strpos($src, 'wp-includes') || false !== strpos($src, 'wp-admin'))
				$src = $this->base . $src;
		// @since 1.0.3
		$src = str_replace('./', '/', $src);
		$src = str_replace('\\', '/', $src);
		$src = preg_replace('#[\/]{2,}#iu', '/', $src);
		$src = ltrim($src, '/');
		return esc_attr($src);
	}

	/**
	 *  Have dependencies for the style / script been added / printed?
	 */
	function are_deps_added($handle, $type = '', $media = NULL)
	{
		global $wp_styles, $wp_scripts;

		$type 		= (!empty($type)) ? 'scripts' : '';
		$wp_media 	= ('scripts' == $type) ? $wp_scripts : $wp_styles;
		$deps		= $wp_media->registered[$handle]->deps;
		$return		= 'wp';

		foreach ($deps as $dep)
		{
			$dep_src = $wp_media->registered[$dep]->src;
			$dep_src = ($this->is_local($dep_src)) ? $this->process_media_source($dep_src) : $dep_src;
			$ext_dep = ($this->is_local($dep_src)) ? '' : $dep;
			$is_added = $this->is_added($dep_src, $type, $ext_dep, $media, $dep);
			if ('min' == $is_added)
				$return = 'min';
			else if ('done' == $is_added)
				$return = 'wp';
			if (!$is_added)
				return false;
		}

		return $return;
	}

	function is_added($src, $type = 'scripts', $handle = '', $media = NULL, $dep_handle = '')
	{
		if (!isset($media))
			$media = ('scripts' == $type) ? array_merge($this->header_scripts, $this->footer_scripts) : array_merge($this->styles, $this->media_styles);

		// Loop through media array to find the source
		foreach ($media as $media_string)
			if (in_array($src, $media_string))
				return 'min';

		// Loop throught done array to find the handle
		$done_media = ('scripts' == $type) ? $this->wp_scripts_done : $this->wp_styles_done;
		foreach ($done_media as $media_handle)
			if ($dep_handle == $media_handle)
				return 'done';

		// Also check extra media if needed
		if (!empty($handle))
		{
			$extra_media = ('scripts' == $type) ? array_merge($this->header_dynamic, $this->footer_dynamic) : array_merge($this->dynamic_styles);
			if (in_array($handle, $extra_media))
				return 'wp';
		}		
		return false;
	}

	function append_minify_string(&$media = array(), &$count = 0, $src = '', $done = false, $parent = false, $type = 'scripts')
	{
		$current_pointer = sizeof($media) - 1;
		// Don't append if already added
		if (!$this->is_added($src, $type))
			$media[$current_pointer][] = $src;
		if (false == $parent && $this->options['input_maxfiles'] <= $count || true == $done)
		{
			$current_pointer = sizeof($media) - 1;
			$count = 0;
			if (!$done)
				$media[] = array();
		}
	}

	function get_minify_src($string)
	{
		if (empty($string))
			return '';

		$buster = (!empty($this->buster)) ? '&amp;ver=' . $this->buster : '';
		$scheme_str = is_ssl() && !is_admin() ? 'https://' : 'http://';

		return trailingslashit(str_replace(array('http://', 'https://'), $scheme_str, $this->options['input_minurl'])) . '?f=' . $string . $buster;
	}

	function get_minify_tag($string, $type, $media = '')
	{
		if (empty($string))
			return '';

		switch ($type)
		{
			case 'script':
				return "<script type='text/javascript' src='" . $this->get_minify_src($string) . "'></script>\n";
			break;
			
			case 'style':
			default:			
				return "<link rel='stylesheet' type='text/css' media='all' href='" . $this->get_minify_src($string) . "' />\n";
			break;

			case 'media':
				return "<link rel='stylesheet' type='text/css' media='$media' href='" . $this->get_minify_src($string) . "' />\n";
			break;
		}
	}

	/**
	 * Get the correct href for rtl css.
	 *
	 * This is actually borrowed from wp-includes/class.wp-styles.php:70
	 */
	function rtl_css_href($handle)
	{
		global $wp_styles;

		if (is_bool($wp_styles->registered[$handle]->extra['rtl']))
		{
			$suffix = isset($wp_styles->registered[$handle]->extra['suffix'] ) ? $wp_styles->registered[$handle]->extra['suffix'] : '';
			$rtl_href = str_replace("{$suffix}.css", "-rtl{$suffix}.css", $wp_styles->registered[$handle]->src);
		}
		else
			$rtl_href = $wp_styles->registered[$handle]->extra['rtl'];
		
		return $this->process_media_source($rtl_href);
	}

	function minify_item($src)
	{
		return $this->get_minify_src($this->process_media_source($src));
	}

	/**
	 * Loop through current todo array to build our minify string (min/?f=style1.css,style2.css)
	 *
	 * If the number of stylesheets reaches a limit (by default the limit is 10) this plugin will split the minify string 
	 * into an appropriate number of <link> tags. This is only to beautify your minify strings (they might get too long).
	 * If you don't like such behaviour, change the limit to something higher, e.g. 50.
	 */
	function minify_styles($todo)
	{
		global $wp_styles;

		$total = sizeof($todo);
		$count = 0;
		$queued = 0;
		$temp = array();
		$this->print_positions['style_ignore'] = apply_filters('ibinc_minify_style_ignore', array());
		$this->print_positions['style_direct'] = apply_filters('ibinc_minify_style_direct', array('admin-bar'));
		$this->print_positions['style_allowed'] = apply_filters('ibinc_minify_allowed_styles', 'all');

		foreach ($todo as $key => $handle)
		{
			$count++;
			// Take the src from registred stylesheets, do not proceed if the src is external
			// Also do not proceed if we can not print any more (except for login page)
			$the_style = $wp_styles->registered[$handle];
			$src = $the_style->src;
			$ignore = false;
			// Check for is_bool @since 1.0.2
			if ($this->is_in($handle, 'style_ignore') || is_bool($src))
				$ignore = true;
			else if (!$this->is_source_static($src))
				$ignore = true;
			// If style handle is not allowed
			else if ('all' != $this->print_positions['style_allowed'] && !$this->is_in($handle, 'style_allowed'))
				$ignore = true;
			else if ($this->is_in($handle, 'style_direct'))
			{
				$src = $this->process_media_source($src);
				$the_style->src = $this->get_minify_src($src);
				$the_style->ver = NULL;
				$ignore = true;
			}
			else if ((has_action('login_head') || true == $this->printable) && !empty($src) && $this->is_local($src))
			{
				$src = $this->process_media_source($src);
				// If styles have been printed, ignore this style
				if (did_action('ibinc_minify_after_styles'))
				{
					if (!$this->is_added($src, 'styles', $handle))
						$temp[] = $handle;
					continue;
				}
				// If this style has a different media type rather than 'all', '',
				// we will have to append it to other strings
				$the_style->args = (isset($the_style->args)) ? trim($the_style->args) : '';
				if (!empty($the_style->args) && 'all' != $the_style->args)
				{
					$media = $the_style->args;
					if (!isset($this->media_styles[$media]))
						$this->media_styles[$media] = array();
					$this->media_styles[$media][] = $src;
				}
				// If this style needs conditional statement (e.g. IE-specific stylesheets) 
				// or is an alternate stylesheet (@see http://www.w3.org/TR/REC-html40/present/styles.html#h-14.3.1), 
				// we will not enqueue it (but still minify it).
				else if ((isset($the_style->extra['conditional']) && $the_style->extra['conditional']) || (isset($the_style->extra['alt']) && $the_style->extra['alt']))
				{
					$the_style->src = $this->get_minify_src($src);
					$the_style->ver = NULL;
					$temp[] = $handle;
				}
				else
				{
					$queued++;
					$this->append_minify_string($this->styles, $queued, $src, $count == $total, true, 'styles');
					// If this style has support for rtl language and the locale is rtl, 
					// we will have to append the rtl stylesheet also
					if ('rtl' === $wp_styles->text_direction && isset($the_style->extra['rtl']) && $the_style->extra['rtl'])
						$this->append_minify_string($this->styles, $queued, $this->rtl_css_href($handle), $count == $total, false, 'styles');
				}
			}
			else
				// error: Call-time pass-by-reference deprecated
				$this->ignores_style($handle, &$temp, $the_style->deps);

			if (true == $ignore)
				$this->ignores_style($handle, &$temp, $the_style->deps);
		}

		//$this->printable = false;

		return $temp;
	}

	/**
	 * Main function to print out our stylesheets
	 *
	 * Use actions provided to add other things before or after the output.
	 */	
	function print_styles()
	{
		do_action('ibinc_minify_before_styles');

		// Print <link> tags
		$styles = (array) $this->styles;
		foreach ($styles as $style_array)
		{
			if (0 < sizeof($style_array))
				echo $this->get_minify_tag(implode(',', $style_array), 'style');
		}

		do_action('ibinc_minify_after_styles');
	}
	
	function print_media_styles()
	{
		do_action('ibinc_minify_before_media_styles');

		// Print <link> tags
		$styles = (array) $this->media_styles;
		foreach ($styles as $key => $style_array)
		{
			if (0 < sizeof($style_array))
				echo $this->get_minify_tag(implode(',', $style_array), 'media', $key);
		}

		do_action('ibinc_minify_after_media_styles');
	}

	function print_dynamic_styles()
	{
		global $wp_styles;

		foreach ($this->dynamic_styles as $handle)
			$wp_styles->do_item($handle);
	}

	function ignores_script($handle, $temp, $deps, $type = 'header')
	{
		global $wp_scripts;

		$are_deps_added = $this->are_deps_added($handle, $type);
		$are_deps_min = $this->are_deps_added($handle, $type, $this->header_scripts);
		$wp = ('wp' == $are_deps_added || ('min' == $are_deps_min && 'footer' == $type && did_action('ibinc_minify_printed_header_scripts'))) ? true : false;

		if (!is_array($deps) || 0 == sizeof($deps) || $wp)
		{
			$temp[] = $handle;
			if (!in_array($handle, $this->wp_scripts_done))
				$this->wp_scripts_done[] = $handle;
		}
		else if ('header' == $type)
		{
			if (!in_array($handle, $this->header_dynamic))
				$this->header_dynamic[] = $handle;
		}
		else if ('footer' == $type && 'min' != $are_deps_min)
		{
			if (!in_array($handle, $this->footer_dynamic))
				$this->footer_dynamic[] = $handle;
		}
	}

	/**
	 * Loop through current todo array to build our minify string (min/?f=script1.js,script2.js)
	 *
	 * If the number of scripts reaches a limit (by default the limit is 10) this plugin will split the minify string 
	 * into an appropriate number of <script> tags. This is to beautify your minify strings (or else they might get too long).
	 * If you don't like such behaviour, change the limit to something higher, e.g. 50.
	 */
	function minify_scripts($todo)
	{
		global $wp_scripts;
		
		// Avoid conflict with WordPress 3.1
		if (1 == sizeof($todo) && isset($todo[0]) && 'l10n' == $todo[0])
			return array();

		// @since 1.0.5 - 1.0.6
		$this->print_positions['header']  = apply_filters('ibinc_minify_script_header', $this->print_positions['header']);
		$this->print_positions['footer']  = apply_filters('ibinc_minify_script_footer', $this->print_positions['footer']);
		$this->print_positions['ignore']  = apply_filters('ibinc_minify_script_ignore', $this->print_positions['ignore']);
		$this->print_positions['direct']  = apply_filters('ibinc_minify_script_direct', $this->print_positions['direct']);
		$this->print_positions['allowed'] = apply_filters('ibinc_minify_allowed_scripts', 'all');
		//get js handles for todo scripts
		
		
		$header_count = 0;
		$footer_count = 0;
		$count_f = 0; $count_h = 0;

		$total = sizeof($todo);
		$total_footer = 0;
		foreach ($wp_scripts->groups as $count)
			if (0 < $count)
				$total_footer++;
		// Get the correct total
		foreach ($todo as $script_handle)
		{
			if ($this->is_in($script_handle, 'ignore') || $this->is_in($script_handle, 'direct'))
			{
				$total--;
				if (isset($wp_scripts->groups[$script_handle]) && 1 == $wp_scripts->groups[$script_handle])
					$total_footer--;
			}
		}
		
		$temp = array();

		foreach ($todo as $key => $script_handle)
		{
			$the_script = $wp_scripts->registered[$script_handle];
			// Take the src from registred scripts, do not proceed if the src is external
			$src = $the_script->src;
			$src_arr=explode('/', $src);
			$file=$src_arr[count($src_arr)-1];
			
			$expected_in_footer = ((isset($wp_scripts->groups[$script_handle]) && 0 < $wp_scripts->groups[$script_handle]) || did_action('wp_footer')) ? true : false;
			$ignore_type = ($expected_in_footer) ? 'footer' : 'header';
			if (!empty($src) && $this->is_source_static($src) && $this->is_local($src))
			{
				$src = $this->process_media_source($src);
				// If this script is not allowed
				if ('all' != $this->print_positions['allowed'] && !$this->is_in($script_handle, 'allowed'))
					$this->ignores_script($script_handle, &$temp, $the_script->deps, $ignore_type);
				// If this script does not belong to 'direct' or 'ignore' list
				else if (!$this->is_in($file, 'ignore') && !$this->is_in($file, 'direct'))
				{
					// If this script belongs to footer (logically or 'intentionally') and is not 'forced' to be in header
					if (!$this->is_in($file, 'header') && ($this->is_in($file, 'footer') || $expected_in_footer))
					{
						// If footer scripts have already been printed, ignore this script
						if (did_action('ibinc_minify_printed_footer_scripts'))
						{
							if (!$this->is_added($src, 'scripts', $script_handle))
								$temp[] = $script_handle;
							continue;
						}
						else if ($this->is_added($src, 'scripts', $script_handle))
						// If footer scripts have not yet been printed, ignore this script if if was added before
								continue;

						$count_f++; $footer_count++;
						$this->append_minify_string($this->footer_scripts, $footer_count, $src, $count_f == $total_footer);
						if (true == $this->is_l10n($script_handle))
							$this->footer_l10n[] = $script_handle;
					}
					else if (true == $this->queueable)
					{
						// If header scripts have already been printed, ignore this script
						if (did_action('ibinc_minify_printed_header_scripts'))
						{
							if (!$this->is_added($src, 'scripts', $script_handle))
								$temp[] = $script_handle;
							continue;
						}

						$count_h++; $header_count++;
						$this->append_minify_string($this->header_scripts, $header_count, $src, $count_h == $total - $total_footer);
						if (true == $this->is_l10n($script_handle))
							$this->header_l10n[] = $script_handle;							
					}
					else
						$this->ignores_script($script_handle, &$temp, $the_script->deps, $ignore_type);				
				}
				else
				{
					// If belongs to 'direct', minify it and let WordPress print it the normal way
					if ($this->is_in($script_handle, 'direct'))
					{
						$wp_scripts->registered[$script_handle]->src = $this->get_minify_src($src);
						$wp_scripts->registered[$script_handle]->ver = NULL;
					}

					$this->ignores_script($script_handle, &$temp, $the_script->deps, $ignore_type);
				}
			}
			else
				$this->ignores_script($script_handle, &$temp, $the_script->deps, $ignore_type);
		}

		//$this->queueable = false;

		return $temp;
	}

	/**
	 * Main function to print out our scripts
	 *
	 * Use actions provided to add other things before or after the output.
	 */	
	function print_scripts($action = 'header')
	{
		do_action('ibinc_minify_before_' . $action . '_scripts');

		// Print script tags
		$scripts = ('header' == $action) ? $this->header_scripts : $this->footer_scripts;
		foreach ($scripts as $script_array)
		{
			if (0 < sizeof($script_array))
				echo $this->get_minify_tag(implode(',', $script_array), 'script');
		}

		do_action('ibinc_minify_after_' . $action . '_scripts');
	}

	function print_header_scripts()
	{
		$this->print_scripts();
		do_action('ibinc_minify_printed_header_scripts');
	}

	function print_footer_scripts()
	{
		$this->print_scripts('footer');
		do_action('ibinc_minify_printed_footer_scripts');
	}

	function print_dynamic_scripts($type = 'header')
	{
		global $wp_scripts;

		$scripts = ('header' == $type) ? $this->header_dynamic : $this->footer_dynamic;
		foreach ($scripts as $handle)
			$wp_scripts->do_item($handle);
	}

	function print_dynamic_header_scripts()
	{
		$this->print_dynamic_scripts();
	}

	function print_dynamic_footer_scripts()
	{
		$this->print_dynamic_scripts('footer');
	}

	function print_scripts_l10n($scripts)
	{
		global $wp_scripts;

		foreach ($scripts as $handle)
			if (version_compare($this->ver, '3.3', '>='))
				$wp_scripts->print_extra_script($handle);
			else
				$wp_scripts->print_scripts_l10n($handle);
	}

	function print_header_scripts_l10n()
	{
		$this->print_scripts_l10n($this->header_l10n);
	}

	function print_footer_scripts_l10n()
	{
		$this->print_scripts_l10n($this->footer_l10n);
	}
}
?>
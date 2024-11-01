<?php
/**
 * Class for plugin options administration
 */

class Ibinc_OP_Settings {

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
		add_menu_page (__('Settings'), __('WP SpeedUp'), 1, __FILE__, array (&$this,'admin_plugin_options'));
		add_submenu_page (__FILE__, __( 'General Settings' ), __( 'General Settings' ), 1, __FILE__, array (&$this,'admin_plugin_options'));

	}

	/**
	 * Displays info message if is something wrong with SimpleXMLElement class or curl class 
	 * 
	 * @param string $info_message
	 */

	function admin_plugin_options($info_message = '') {
		$this->admin_handle_other_options ( $info_message );
	}

	/**
	 * Sets or delete wordpress options for plugin administration 
	 * 
	 * @param string $info_message
	 */
	function admin_handle_other_options($info_message = '') {
		if (isset ( $_POST ['SubmitOptions'] )) {
			if (function_exists ( 'current_user_can' ) && ! current_user_can ( 'manage_options' )) {
				die ( __ ( 'Cheatin&#8217; uh?','IBINC_PO' ) );
			}

			if (isset ( $_POST ['ibinc_rem_generator'] )) {
				add_option ( 'ibinc_rem_generator', 'true' );
			} else {
				delete_option ( 'ibinc_rem_generator' );
			}
			if (isset ( $_POST ['ibinc_rem_rsd'] )) {
				add_option ( 'ibinc_rem_rsd', 'true' );
			} else {
				delete_option ( 'ibinc_rem_rsd' );
			}
			if (isset ( $_POST ['ibinc_rem_wlwmanifest'] )) {
				add_option ( 'ibinc_rem_wlwmanifest', 'true' );
			} else {
				delete_option ( 'ibinc_rem_wlwmanifest' );
			}	
			update_option ('ibinc_lz_exclude', $_POST['ibinc_lz_exclude']);
			/*
			if (isset ( $_POST ['ibinc_cache_init'] )) {
				add_option ( 'ibinc_cache_init', 'true' );
			} else {
				delete_option ( 'ibinc_cache_init' );
			}
			*/
			$info_message = 'Options Saved';
			//echo '<script>location.href=""; </script>';
			
		}
		$this->info_message = $info_message;
		$this->error_message = '';
		$this->display_admin_handle_other_options ();
	}

	/**
	 * Builds admin options template page
	 * 
	 */
	function display_admin_handle_other_options() {
	?>
		<div class="wrap" class="paddingTop50">
			<h3>Headers Options</h3>
			<?php $this->display_messages(); ?>
	    	<form action="" method="post">
				<table class="form-table">
					<tr valign="top">
						<th><label for="ibinc_rem_generator"><?php _e('Remove the generator meta tag','IBINC_PO')?></label></th>
						<td><input name="ibinc_rem_generator" type="checkbox" id="ibinc_rem_generator" value="" <?php echo (get_option('ibinc_rem_generator') == 'true') ? 'checked' : ''; ?> /></td>
					</tr>
					<tr valign="top">
						<th><label for="ibinc_rem_rsd"><?php _e('Remove the rsd link','IBINC_PO')?></label></th>
						<td><input name="ibinc_rem_rsd" type="checkbox" id="ibinc_rem_rsd" value="" <?php echo (get_option('ibinc_rem_rsd') == 'true') ? 'checked' : ''; ?> /></td>
					</tr>
					<tr valign="top">
						</tr>
					<!-- 
					<tr valign="top">
						<th scope="row">&nbsp;</th>
						<td>&nbsp;</td>
					</tr>
					 -->
				</table>
				<h3>Lazy Loading Options</h3>
				<table class="form-table">
					<tbody>
					  <tr>
					    <th style="width:300px;"><label for="ibinc_lz_exclude"> <?php _e('Exclude the following containers', 'IBINC_PO'); ?></label><span class="more-help">[?]</span></th>
					    <td>
					    	<input style="width:400px" name="ibinc_lz_exclude" id="ibinc_lz_exclude" type="text" value="<?php echo get_option('ibinc_lz_exclude')?>" />
					    	<div class="hints"><?php _e('You have to include in this list all the containers identifiers (id or class) that you want lazy laoding to exclude. You will need to do this for your javascript sliders.', 'IBINC_PO'); ?></div>
					    </td>
					  </tr>
					</tbody>
				</table>
				<!-- 
				<h3>Caching Options</h3>
				<table class="form-table">
					<tbody>
					  <tr valign="top">
						<th><label for="ibinc_cache_init"><?php _e('Enable cache','IBINC_PO')?></label></th>
						<td><input name="ibinc_cache_init" type="checkbox" id="ibinc_cache_init" value="" <?php echo (get_option('ibinc_cache_init') == 'true') ? 'checked' : ''; ?> /></td>
					  </tr>
					</tbody>
				</table>
				 -->
				<p class="submit">
					<input type="submit" name="SubmitOptions" class="button-primary" value="<?php _e('Save Changes','IBINC_PO'); ?>" /> 
				</p>
			</form>
		</div>
	<?php
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
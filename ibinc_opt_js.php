<?php
/**
 * Class for plugin options administration
 */

class Ibinc_OP_Js {

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
		add_submenu_page (plugin_dir_path ( __FIlE__ ) . 'ibinc_opt_settings.php', __( 'Js Optimization' ), __( 'Js Optimization' ), 1, __FILE__, array (&$this,'admin_handle_other_options'));

	}

	/**
	 * Sets or delete wordpress options for plugin administration 
	 * 
	 * @param string $info_message
	 */
	function admin_handle_other_options($info_message = '') {
		$info_message='';
		if (isset ( $_POST ['SubmitOptions'] )) {
			if (function_exists ( 'current_user_can' ) && ! current_user_can ( 'manage_options' )) {
				die ( __ ( 'Cheatin&#8217; uh?','IBINC_PO' ) );
			}
			delete_option ( 'ibinc_js' );
			$options['enable_min_js']=$_POST['enable_min_js'];
			$options['input_maxfiles']=$_POST['input_maxfiles'];
			$options['input_header']=$_POST['input_header'];
			$options['input_footer']=$_POST['input_footer'];
			$options['input_direct']=$_POST['input_direct'];
			$options['input_ignore']=$_POST['input_ignore'];
			add_option('ibinc_js',$options);
			$info_message = 'Options Saved';
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
	?>
		<div class="wrap" class="paddingTop50">
			<?php 
			$options=get_option('ibinc_js');
			$this->display_messages(); 
			?>
			<form action="" method="post">
	    		<h3>Js Options</h3>
	    		<table class="form-table">
					  <tr>
					    <th style="width:300px;"><label for="enable_min_js"> <?php _e('Minify JS files automatically?', 'IBINC_PO'); ?></label></th>
					    <td><input name="enable_min_js" id="enable_min_js" type="checkbox" value="yes" <?php echo ($options['enable_min_js'] == 'yes') ? 'checked' : ''; ?>/></td>
					  </tr>
					  <tr>
					    <th><label for="input_maxfiles"><?php _e('One minify string will contain', 'IBINC_PO'); ?></label></th>
					    <td><input name="input_maxfiles" id="input_maxfiles" type="text" value="<?php echo $options['input_maxfiles']?>" /><em>file(s) at most.</em></td>
					  </tr>
					  <tr>
					    <th><label for="input_header"><?php _e('Scripts to be minified in header', 'IBINC_PO'); ?></label></th>
					    <td><textarea id="input_header" name="input_header" cols="40" rows="3"><?php echo $options['input_header']?></textarea></td>
					  </tr>
					  <tr>
					    <th><label for="input_footer"><?php _e('Scripts to be minified in footer', 'IBINC_PO'); ?></label></th>
					    <td><textarea id="input_footer" name="input_footer" cols="40" rows="3"><?php echo $options['input_footer']?></textarea></td>
					  </tr>
					  <tr>
					    <th><label for="input_direct"><?php _e('Scripts to be minified and then printed separately', 'IBINC_PO'); ?></label></th>
					    <td><textarea id="input_direct" name="input_direct" cols="40" rows="3"><?php echo $options['input_direct']?></textarea></td>
					  </tr>
					  <tr>
					    <th><label for="input_ignore"><?php _e('Scripts to be ignored (not minified)', 'IBINC_PO'); ?></label></th>
					    <td><textarea id="input_ignore" name="input_ignore" cols="40" rows="3"><?php echo $options['input_ignore']?></textarea></td>
					  </tr>
				</table>
				<p class="submit">
					<input type="submit" name="SubmitOptions" class="button-primary" value="<?php _e('Save Changes','IBINC_PO'); ?>" /> 
				</p>
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
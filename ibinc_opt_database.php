<?php
/**
 * Class for plugin options administration
 */

class Ibinc_OP_Database {

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
		add_submenu_page (plugin_dir_path ( __FIlE__ ) . 'ibinc_opt_settings.php', __( 'Database Optimization' ), __( 'Database Optimization' ), 1, __FILE__, array (&$this,'admin_handle_other_options'));

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
			if (isset($_POST["ibinc_clean_revisions"])) {
				$info_message .= $this->cleanUpSystem('revisions');
			}
			
			if (isset($_POST["ibinc_clean_autodraft"])) {
				$info_message .= $this->cleanUpSystem('autodraft');
			}
			
			if (isset($_POST["ibinc_clean_comments"])) {
				$info_message .= $this->cleanUpSystem('spam');
			}
			
			if (isset($_POST["ibinc_optimize_db"])) {
				$info_message .= $this->cleanUpSystem('optimize_tables');
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
	?>
		<div class="wrap" class="paddingTop50">
			
			<?php $this->display_messages(); ?>
	    	<form action="" method="post">
	    		<h3>Database Options</h3>
				<table class="form-table">
					  <tr>
					    <th style="width:300px;"><label for="ibinc_clean_revisions"> <?php _e('Remove all Post revisions', 'IBINC_PO'); ?><br />(<?php _e($this->getInfo('revisions'), 'IBINC_PO'); ?>)</label></th>
					    <td><input name="ibinc_clean_revisions" id="ibinc_clean_revisions" type="checkbox" value="" /></td>
					  </tr>
					  <tr>
					    <th><label for="ibinc_clean_autodraft"><?php _e('Remove all auto draft posts', 'IBINC_PO'); ?><br />(<?php _e($this->getInfo('autodraft'), 'IBINC_PO'); ?>)</label></th>
					    <td><input name="ibinc_clean_autodraft" id="ibinc_clean_autodraft" type="checkbox" value="" /></td>
					  </tr>
					  <tr>
					    <th><label for="ibinc_clean_comments"><?php _e('Clean marked Spam comments', 'IBINC_PO'); ?><br />(<?php _e($this->getInfo('spam'), 'IBINC_PO'); ?>)</label></th>
					    <td><input name="ibinc_clean_comments" id="ibinc_clean_comments" type="checkbox" value="" /></td>
					  </tr>
					  <tr>
					    <th><label for="ibinc_optimize_db"><?php _e('Optimize database tables', 'IBINC_PO'); ?><br />(<?php _e($this->getInfo('optimize_tables'), 'IBINC_PO'); ?>)</label></th>
					    <td><input name="ibinc_optimize_db" id="ibinc_optimize_db" type="checkbox" value="" /></td>
					  </tr>
				</table>
				<p class="submit">
					<input type="submit" name="SubmitOptions" class="button-primary" value="<?php _e('Process','IBINC_PO'); ?>" /> 
				</p>
			</form>
		</div>
	<?php
	}
	
	/**
	 * Get the info from the database
	 * @param string $infoType
	 * returns string
	 */
	function getInfo($infoType){
		global $wpdb;
		$sql = ""; $message = "";
	
		switch ($infoType) {
			case "revisions":
				$sql = "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'revision'";
				$revisions = $wpdb->get_var( $sql );
				if(!$revisions == 0 || !$revisions == NULL){
					$message .= $revisions.__(' post revisions in your database', 'IBINC_PO');
				}
				else $message .=__('No post revisions found', 'IBINC_PO');
				break;
	
			case "autodraft":
				$sql = "SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'auto-draft'";
				$autodraft = $wpdb->get_var( $sql );
	
				if(!$autodraft == 0 || !$autodraft == NULL){
					$message .= $autodraft.__(' auto draft post(s) in your database', 'IBINC_PO');
				}
				else $message .=__('No auto draft posts found', 'IBINC_PO');
				break;
					
					
			case "spam":
				$sql = "SELECT COUNT(*) FROM $wpdb->comments WHERE comment_approved = 'spam';";
				$comments = $wpdb->get_var( $sql );
				if(!$comments == NULL || !$comments == 0){
					$message .=$comments.__(' spam comments found', 'IBINC_PO').' | <a href="edit-comments.php?comment_status=spam">'.__(' Review Spams</a>', 'IBINC_PO');
				} else
					$message .=__('No spam comments found', 'IBINC_PO');
				break;
				
			case "optimize_tables":
				$total_gain = 0; 
				$local_query = 'SHOW TABLE STATUS FROM `'. DB_NAME.'`';
				$result = mysql_query($local_query);
				if (mysql_num_rows($result)){
					while ($row = mysql_fetch_array($result)){		
						if ($row['Data_free']>0 && $row['Engine']!='InnoDB'){				
							$gain= $row['Data_free'];
							$gain = $gain / 1024 ;
							$total_gain += $gain;
						}
					}
				}
				if(!$total_gain == NULL || $total_gain != 0){
					$message .=round($total_gain,2).__(' KiB will be gained if the database is optimized', 'IBINC_PO');
				} else
					$message .=__('The databse is optimized', 'IBINC_PO');
				break;
	
			default:
				$message .= __('nothing', 'IBINC_PO');
				break;
		}
		return $message;
	}
	
	/**
	 * Performs the optimization action
	 * @param string $cleanupType
	 * returns string
	 */
	function cleanUpSystem($cleanupType){
		global $wpdb;
		$clean = ""; $message = "";
	
		switch ($cleanupType) {
			case "revisions":
				$clean = "DELETE FROM $wpdb->posts WHERE post_type = 'revision'";
				$revisions = $wpdb->query( $clean );
				$message .= $revisions.__(' post revisions deleted<br />', 'IBINC_PO');
				break;
	
			case "autodraft":
				$clean = "DELETE FROM $wpdb->posts WHERE post_status = 'auto-draft'";
				$autodraft = $wpdb->query( $clean );
				$message .= $autodraft.__(' auto drafts deleted<br />', 'IBINC_PO');
				break;
	
			case "spam":
				$clean = "DELETE FROM $wpdb->comments WHERE comment_approved = 'spam';";
				$comments = $wpdb->query( $clean );
				$message .= $comments.__(' spam comments deleted<br />', 'IBINC_PO');
				break;
				
			case "optimize_tables":
				$total_gain = 0;
				$local_query = 'SHOW TABLE STATUS FROM `'. DB_NAME.'`';
				$result = mysql_query($local_query);
				if (mysql_num_rows($result)){
					while ($row = mysql_fetch_array($result)){
						if ($row['Data_free']>0 && $row['Engine']!='InnoDB'){
							$local_query = 'OPTIMIZE TABLE '.$row[0];
				  			$resultat  = mysql_query($local_query);
				  			$tables.=$row[0].', ';
						}
					}
					$message .=__('The following tables: '.substr($tables,0,-2).' have been optimized.', 'IBINC_PO');
				} else {
					$message .= DB_NAME.__("Database Optimized!<br />", 'IBINC_PO');
				}
				
				break;
	
			default:
				$message .= __('NO Actions Taken<br>', 'IBINC_PO');
				break;
		}
		return $message;
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
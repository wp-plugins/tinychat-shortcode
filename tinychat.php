<?php
/*
Plugin Name: TinyChat Shortcode
version: 1.0
Plugin URI: https://wordpress.org/plugins/tinychat-shortcode/
Description: Allow TinyChat In Post And Pages with simple Shortcode.
Author: Dat Nguyen
Author URI: http://wp-plugins.seedceo.com/
*/

global $allow_tinychat;
if(! class_exists( "tinychat_class" ) ){


	class tinychat_class{
		/* Static Variables */
		static $capabilities = "manage_options";
		static $plugin_title = "TinyChat";
		static $menu_slug = "tiny-chat";
		static $menu_title = "Tinychat"; 
		static $menu_page_title = "TinyChat";
		static $submenu_slug = "tiny-chat-info";
		static $submenu_title = "Plugin Info";
		static $submenu_page_title = "Plugin Info";
		static $option_name = "tinychat_options";
		static $database_version = "3";
		static $database_prefix = "tinychat";
		static $stylesheet_slug = "tinychat-stylesheet";
		static $post_prefix = "h29fno22lk32";
		public $_shared = "";
		
		function __construct(){
			$this->_shared = array();
			add_shortcode( 'tinychat', array( __CLASS__, "shortcode" ) );
			add_shortcode( 'TINYCHAT', array( __CLASS__, "shortcode" ) );
			add_shortcode( 'wpvideochat', array( __CLASS__, "shortcode" ) );
			add_shortcode( 'WPvideochat', array( __CLASS__, "shortcode" ) );
			add_action( "admin_menu", array( __CLASS__, "menu_register" ) );
			add_action( 'admin_init', array( __CLASS__, "menu_register_extras"),0);	
			add_filter('widget_text', 'do_shortcode');
			add_filter('the_content', array( __CLASS__, "shortcode_advanced" ),0);
		}
		
		function menu_register_extras(){
			wp_register_style( self::$stylesheet_slug, plugins_url('additional-styles.css', __FILE__) );
		}
		
		function menu_register(){
			$page = add_menu_page( self::$menu_page_title, self::$menu_title, self::$capabilities, self::$menu_slug, array(__CLASS__, "menu_primary"), plugins_url("ap.png", __FILE__) );
			$page2 = add_submenu_page( self::$menu_slug, self::$submenu_title, self::$submenu_page_title, self::$capabilities, self::$submenu_slug, array(__CLASS__, "menu_sub"));
			add_action( 'admin_print_styles-' . $page, array( __CLASS__, "menu_styles" ) );
			add_action( 'admin_print_styles-' . $page2, array( __CLASS__, "menu_styles" ) );
		}
		function menu_styles(){
			wp_enqueue_style( self::$stylesheet_slug );
		}
		function menu_primary(){
			echo '<div class="wrap">';	
			echo '<h2>'.self::$plugin_title.'</h2>';
			self::check_post();
			$option = self::option_get();
			echo '<h3>General Options</h3>';
			self::form_general_options($option);
			echo "<p>&nbsp;</p>";
			echo '<h3>Video Chat Room</h3>';
			$snippets = self::snippet_get_all();
			foreach($snippets as $id=>$snippet){
				echo "<fieldset class='snippet'>";
					echo "<legend><h4>Edit Room ID: ".$snippet->id."</h4></legend>";
					echo "<p><strong>Shortcode: <code>[tinychat function=".$snippet->id."]</code></strong></p>";
					self::form_edit_snippet($snippet);	
				echo "</fieldset>";
			}
			echo "<fieldset class='snippet'>";
				echo "<legend><h4>ADD A NEW ROOM</h4></legend>";
				echo "<p><strong>Shortcode: <code>[tinychat][/tinychat]</code></strong></p>";
				self::form_add_snippet();
			echo "</fieldset>";
			echo '</div>';
		}
		function menu_sub(){
			echo '<div class="wrap">';
			echo '<h2>' . self::$plugin_title . '</h2>';
			echo '<h3>' . self::$submenu_title . '</h3>';
			include( "information.php" );	
			echo '</div>';
		}
		
		function check_post(){
			if( isset( $_REQUEST[self::$post_prefix] ) ){
				$expected = array(
					"opt"=>array(),
					"action"=>"",
					"action_code" => "",
					"verification"=>"");
				$outcome = array_merge($expected, $_REQUEST[self::$post_prefix]);
				extract($outcome);
				if( wp_verify_nonce( $action_code, $action) ){
					if($action === "update_plugin_options"){
						$options = self::option_get();
						foreach($opt as $key=>$value){
							if((int)$value === 1 || (int)$value === 0){
								$options[$key] = (int)$value;
							}
						}
						$res = self::option_set($options);
						if($res === true || $res === NULL){
							self::display_message("Plugin Options Updated");
						}
						else{
							self::display_message("Could Not Update Options, they may not have changed!", false);
						}
					}
					elseif( $action ==="snippet_add" ){
						$opt["snippet_title"] = esc_html($opt["snippet_title"]);
						$temp = "global \$current_user; get_currentuserinfo();  if (is_user_logged_in()){?><script type=\"text/javascript\">var tinychat = { room: \"".$opt["snippet_title"]."\", colorbk: \"0xffffff\", join: \"auto\", api: \"none\", change: \"none\", owner: \"none\", desktop: \"true\", youtube: \"all\"}; </script><script src=\"http://tinychat.com/js/embed.js\"></script><?php } else echo \"Plese Login To Chat!\"; ?>";
						$id = self::snippet_add( array( "name"=>$opt["snippet_title"], "function"=> $temp ) );
						if( $id > 0){
							self::display_message ("Room Added, you can use this room using the shortcode <code>[tinychat function={$id}]</code>");	
						}
						else{
							self::display_message ("Oh dear, could not add the code", false);	
						}
					}
					elseif ($action ==="snippet_edit"){
						if( wp_verify_nonce( $verification, $action.$opt["snippet_id"] ) ){
							$opt["snippet_title"] = esc_html($opt["snippet_title"]);
							$temp = "global \$current_user; get_currentuserinfo();  if (is_user_logged_in()){?><script type=\"text/javascript\">var tinychat = { room: \"".$opt["snippet_title"]."\", colorbk: \"0xffffff\", join: \"auto\", api: \"none\", change: \"none\", owner: \"none\", desktop: \"true\", youtube: \"all\"}; </script><script src=\"http://tinychat.com/js/embed.js\"></script><?php } else echo \"Plese Login To Chat!\"; ?>";
							$id = self::snippet_edit( $opt["snippet_id"],  array( "name"=>$opt["snippet_title"], "function"=> $temp ) );
							if( $id > 0){
								self::display_message ("Room has been updated");	
							}
							else{
								self::display_message ("Oh dear, could not update that code", false);	
							}
						}
					}
					elseif ($action === "snippet_delete"){
						if( wp_verify_nonce( $verification, $action.$opt["snippet_id"] ) ){
							self::snippet_delete( $opt["snippet_id"] );
							self::display_message ("Room has been deleted");	
						}
					}
				}
				else{
					self::display_message( "An error occured, please try again", false );
				}
			}			
		}
		
		function display_message( $message="", $good = true){
			$clas = "updated";
			if( $good === false){$clas='error';}
			echo '<div class="'.$clas.' settings-error" id="setting-error-settings_updated"><p><strong>'.$message.'</strong></p></div>';
		}
		
		function snippet_add(  $snippet = array( "name" => "", "function"=>"" ) ){
			global $wpdb;
			if( $wpdb->insert( $wpdb->prefix.self::$database_prefix, $snippet, array("%s", "%s") ) ){
				return $wpdb->insert_id;
			}
			else{
				return 0;	
			}
		}
		function snippet_edit( $id = 0, $snippet = array( "name" => "", "function"=>"" ) ){
			global $wpdb;
			return $wpdb->update( $wpdb->prefix.self::$database_prefix, $snippet, array( "id" => $id ), array("%s", "%s"), array("%d") );
		}
		function snippet_delete( $snippet_id = 0){
			global $wpdb;
			return $wpdb->get_results( $wpdb->prepare( "DELETE FROM `".$wpdb->prefix.self::$database_prefix."` WHERE `id` = %d LIMIT 1", $snippet_id ) );	
		}
		function snippet_get( $snippet_id = 0 ){
			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `".$wpdb->prefix.self::$database_prefix."` WHERE `id` = %d", $snippet_id ) );	
			if(sizeof($row) > 0){
				$row->function = htmlspecialchars_decode($row->function);
			}
			return $row;
		}
		function snippet_get_all( ){
			global $wpdb;
			$rows = $wpdb->get_results( "SELECT * FROM `".$wpdb->prefix.self::$database_prefix."`" );	
			return $rows;
		}
		function snippet_swap( $snippet_id = 0){
			$snippet = self::snippet_get($snippet_id);
			if(sizeof($snippet) == 0){
				echo self::snippet_404();
			}
			else{
				eval( stripslashes($snippet->function));
			}
		}
		function snippet_404(){
			$option = self::option_get();
			if( $option["show404"] == 1 ){
				if( is_int( $option["fourohfourmsg"] ) && $option["fourohfourmsg"] !== 0 ){
					$snippet = self::snippet_get( $option["fourohfourmsg"] );
					return $snippet->function;
				}
				else{
					return "<span style='font-weight:bold; color:red'>Function does not exist</span>";;
				}
			}
			return "";
		}
		
		function form_add_snippet(){
			?>	
			<form action="?page=<?php echo self::$menu_slug?>" method="post">
				<input type="hidden" name="<?php echo self::$post_prefix?>[action]" value="snippet_add" />
				<?php wp_nonce_field( "snippet_add", self::$post_prefix."[action_code]");?>
				<p>
					<label for="<?php echo self::$post_prefix?>[opt][snippet_title]">Room Title</label><input type="text" class="form-field" name="<?php echo self::$post_prefix?>[opt][snippet_title]" required placeholder="Room Title, e.g. Myroom" pattern="[a-zA-Z0-9]{3,30}" title="Room Name Contains ONLY Letters And Numbers Between 3 to 30 Character. Ex: acbd or 1234 or acbd123"/>
				</p>
				<p>
                	<textarea style="display:none;" name="<?php echo self::$post_prefix?>[opt][snippet_code]" required class="code-field" placeholder="Your Code Room">Code</textarea>
				</p>
				<input type="submit" value="Save Room" />
			</form>
			<?php
		}
		function form_edit_snippet( $snippet ){
			?>	
			<form action="?page=<?php echo self::$menu_slug?>" method="post">
				<input type="hidden" name="<?php echo self::$post_prefix?>[action]" value="snippet_edit" />
				<?php wp_nonce_field( "snippet_edit", self::$post_prefix."[action_code]");?>
				<?php wp_nonce_field( "snippet_edit".$snippet->id, self::$post_prefix."[verification]");?>
				<input type="hidden" name="<?php echo self::$post_prefix?>[opt][snippet_id]" value="<?php echo esc_attr($snippet->id)?>" />
				<p>
					<label for="<?php echo self::$post_prefix?>[opt][snippet_title]">Room Name</label><input type="text" class="form-field" name="<?php echo self::$post_prefix?>[opt][snippet_title]" required placeholder="Room Title, e.g. MyRoom" value='<?php echo esc_attr(stripslashes($snippet->name))?>' pattern="[a-zA-Z0-9]{3,30}" title="Room Name Contains ONLY Letters And Numbers Between 3 to 30 Character. Ex: acbd or 1234 or acbd123"/>
				</p>
					<input type="submit" value="Update Room" /> <a href="?page=<?php echo self::$menu_slug?>&<?php echo self::$post_prefix?>[action]=snippet_delete&<?php echo self::$post_prefix?>[action_code]=<?php echo wp_create_nonce( "snippet_delete")?>&<?php echo self::$post_prefix?>[opt][snippet_id]=<?php echo $snippet->id?>&<?php echo self::$post_prefix?>[verification]=<?php echo wp_create_nonce( "snippet_delete".$snippet->id)?>" class='delete-button' onclick="return confirm('Are you sure you want to delete this Room?')">Delete This Room</a>
			</form>
			<?php	
		}
		function form_general_options($option){
			extract($option);
			?>
			<form action="?page=<?php echo self::$menu_slug;?>" method="post">
				<input type="hidden" name="<?php echo self::$post_prefix?>[action]" value="update_plugin_options" />
				<?php wp_nonce_field( "update_plugin_options", self::$post_prefix."[action_code]");?>
				<input type="hidden" value="0" name="<?php echo self::$post_prefix?>[opt][fourohfourmsg]" />
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row">
								<label for="<?php echo self::$post_prefix?>[opt][show404]"><strong>Show the code not found message?</strong></label>
							</th>
							<td>
								Yes: <input type="radio" name="<?php echo self::$post_prefix?>[opt][show404]" <?php checked($show404, 1, true);?> value="1" /><br />No: <input type="radio" name="<?php echo self::$post_prefix?>[opt][show404]" <?php checked($show404, 0, true);?> value="0" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="<?php echo self::$post_prefix?>[opt][total_uninstall]"><strong>Remove all plugin data on uninstall</strong><br /><em>only applies when the plugin is deleted via the plugins menu</em></label>
							</th>
							<td>
								Yes: <input type="radio" name="<?php echo self::$post_prefix?>[opt][total_uninstall]" <?php checked($total_uninstall, 1, true);?> value="1" /><br />No: <input type="radio" name="<?php echo self::$post_prefix?>[opt][total_uninstall]" <?php checked($total_uninstall, 0, true);?> value="0" />
							</td>
						</tr>
					</tbody>
				</table>
				<p><input type="submit" class="button-primary" value="Update Options" /></p>
			</form>	
			<?php
		}
		
		function option_get(){
			$defaults = array(
					"show404" => 0,
					"fourohfourmsg" => 0, 
					"dbVersion" => 0,
					"use_advanced_filter" => 0,
					"preparse" => 0,
					"total_uninstall" => 0,
				);
			$options = get_option(self::$option_name,$defaults);
			return array_merge($defaults, $options);
		}
		function option_set( $new_options = array() ){
			return update_option( self::$option_name, $new_options);
		}

		function shortcode($args, $content=""){
			$option = self::option_get();
			$default_args = array('debug' => 0,'silentdebug' => 0, 'function' => 0, 'mode'=>'new');
			extract( shortcode_atts( $default_args, $args));
			$four0four_used = false;
			//Debug settings
			if($debug == 1){
				error_reporting(E_ALL);
				ini_set("display_errors","1");
			}
			
			if($function == 0):
				if( $mode == "new" || ($option["preparse"] == 0 && $mode == "new") ){
					$content = strip_tags($content);
					$content = preg_replace("/\[{1}([\/]*)([a-zA-z\/]{1}[a-zA-Z0-9]*[^\'\"])([a-zA-Z0-9 \!\"\£\$\%\^\&\*\*\(\)\_\-\+\=\|\\\,\.\/\?\:\;\@\'\#\~\{\}\¬\¦\`\<\>]*)([\/]*)([\]]{1})/ix","<$1$2$3>",$content,"-1");
					$content = htmlspecialchars($content, ENT_NOQUOTES);
					$content = str_replace("&amp;#8217;","'",$content);
					$content = str_replace("&amp;#8216;","'",$content);
					$content = str_replace("&amp;#8242;","'",$content);
					$content = str_replace("&amp;#8220;","\"",$content);
					$content = str_replace("&amp;#8221;","\"",$content);
					$content = str_replace("&amp;#8243;","\"",$content);
					$content = str_replace("&amp;#039;","'",$content);
					$content = str_replace("&#039;","'",$content);
					$content = str_replace("&amp;#038;","&",$content);
					$content = str_replace("&amp;gt;",'>',$content);
					$content = str_replace("&amp;lt;",'<',$content);
					$content = htmlspecialchars_decode($content);
				}
				else{
					$content =(htmlspecialchars($content,ENT_QUOTES));
					$content = str_replace("&amp;#8217;","'",$content);
					$content = str_replace("&amp;#8216;","'",$content);
					$content = str_replace("&amp;#8242;","'",$content);
					$content = str_replace("&amp;#8220;","\"",$content);
					$content = str_replace("&amp;#8221;","\"",$content);
					$content = str_replace("&amp;#8243;","\"",$content);
					$content = str_replace("&amp;#039;","'",$content);
					$content = str_replace("&#039;","'",$content);
					$content = str_replace("&amp;#038;","&",$content);
					$content = str_replace("&amp;lt;br /&amp;gt;"," ", $content);
					$content = htmlspecialchars_decode($content);
					$content = str_replace("<br />"," ",$content);
					$content = str_replace("<p>"," ",$content);
					$content = str_replace("</p>"," ",$content);
					$content = str_replace("[br/]","<br/>",$content);
					$content = str_replace("\\[","&#91;",$content);
					$content = str_replace("\\]","&#93;",$content);
					$content = str_replace("[","<",$content);
					$content = str_replace("]",">",$content);
					$content = str_replace("&#91;",'[',$content);
					$content = str_replace("&#93;",']',$content);
					$content = str_replace("&gt;",'>',$content);
					$content = str_replace("&lt;",'<',$content);
				}
			else:
				//function selected
				$snippet = self::snippet_get($function);
				if( sizeof( $snippet ) == 0){
					$four0four_used = true;
					$content = self::snippet_404();
				}
				else{
					$content = stripslashes($snippet->function);
				}
			endif;
			ob_start();
			eval($content);
			if($debug == 1||$silentdebug == 1){
				if($silentdebug == 1){
					echo "\n\n<!-- ALLOW TINYCHAT SILENT DEBUG MODE - - > \n\n\n";
				}else{
					echo "<p align='center'>Allow TINYCHAT Debug</p>";
				}
				if($four0four_used){
					$content = "Function id : $function : cannot be found<br/>";
				}else{
					$content =(htmlspecialchars($content,ENT_QUOTES));
				}
				echo "<pre>".$content."</pre>";
				if($silentdebug == 1){
					echo "\n\n\n<- - END ALLOW TINYCHAT SILENT DEBUG MODE -->\n\n";
				}else{
					echo "<p align='center'>End Allow TINYCHAT Debug</p>";
				}
			}
			return ob_get_clean();			
		}
		
		function shortcode_advanced($args){
			$options = self::option_get();
			if( isset( $options['use_advanced_filter'] ) ){
				
				if( $options['use_advanced_filter'] == "1" ){
					remove_shortcode("tinychat");
					remove_shortcode("TINYCHAT");
					remove_shortcode("allowtinychat");
					remove_shortcode("ALLOWTINYCHAT");
					
					$args = str_ireplace("[tinychat]","<?php ",$args);
					$args = str_ireplace("[/tinychat]"," ?>",$args);
					
					$args = str_ireplace("[tinychat useadvancedfilter]","<?php ",$args);
					$args = str_ireplace("[/tinychat useadvancedfilter]"," ?>",$args);
					
					$args = str_ireplace("[allowtinychat]","<?php ",$args);
					$args = str_ireplace("[/allowtinychat]"," ?>",$args);
					
					$args = str_ireplace("[allowtinychat useadvancedfilter]","<?php ",$args);
					$args = str_ireplace("[/allowtinychat useadvancedfilter]"," ?>",$args);
					
					$args = preg_replace( "#\[tinychat(.*?)function=([0-9]*)(.*?)\]#", "<?php tinychat_class::snippet_swap( $2 ) ?>",$args);
					$args = preg_replace( "#\[allowtinychat(.*?)function=([0-9]*)(.*?)\]#", "<?php tinychat_class::snippet_swap( $2 ) ?>",$args);
					ob_start();
					eval("?>".$args);
					$return = ob_get_clean();
					return $return;
				}
				else{
					return $args;	
				}
				
			}
			$args = str_ireplace("[tinychat useadvancedfilter]","<?php ",$args);
			$args = str_ireplace("[/tinychat useadvancedfilter]"," ?>",$args);
			
			$args = str_ireplace("[allowtinychat useadvancedfilter]","<?php ",$args);
			$args = str_ireplace("[/allowtinychat useadvancedfilter]"," ?>",$args);
			
			ob_start();
			eval("?>".$args);
			$returned = ob_get_clean();
			return $returned;	
		}
		
		function hook_activation(){
			self::db_check();	
		}
		function hook_uninstall(){
			$option = self::option_get();
			if($option["total_uninstall"] === 1){
				global $wpdb;
				$wpdb->query("DROP TABLE `".$wpdb->prefix.self::$database_prefix."`");
				delete_option( self::$option_name );
			}
		}
		
		function db_check(){
			$opt = self::option_get();
			if($opt["dbVersion"] != self::$database_version){
				self::db_upgrade();
			}
		}
		function db_upgrade(){
			global $wpdb;
			$sql = "RENAME TABLE `".$wpdb->prefix."allowtinychat_functions` TO `".$wpdb->prefix.self::$database_prefix."`";
			$wpdb->get_results($sql);
			$sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.self::$database_prefix."(
				id int NOT NULL AUTO_INCREMENT,
				name varchar(100) NOT NULL,
				function longtext NOT NULL,
				PRIMARY KEY(id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
			//need to manually change existing function columns
			$wpdb->get_results("ALTER TABLE `".$wpdb->prefix.self::$database_prefix."` CHANGE `function` `function` LONGTEXT NOT NULL ");
			$opt = self::option_get();
			$opt["dbVersion"] = self::$database_version;
			self::option_set($opt);
		}
	}
	
	function allow_tinychat_init(){
		global $allow_tinychat;
		$allow_tinychat = new tinychat_class();	
	}
	add_action("init","allow_tinychat_init");
	register_activation_hook( __FILE__ , array( "tinychat_class" , "hook_activation" ) );
	register_uninstall_hook( __FILE__, array( "tinychat_class", "hook_uninstall" ) );
}
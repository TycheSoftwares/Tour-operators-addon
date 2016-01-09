<?php 
/*
Plugin Name: Tour Operators Addon
Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/tour-operators-addon-for-woocommerce-booking-appointment-plugin/
Description: This is an addon for the WooCommerce Booking & Appointment Plugin which lets you to add and Manage Tour Operators.
Version: 1.6
Author: Tyche Softwares
Author URI: http://www.tychesoftwares.com/
*/

/*require 'plugin-updates/plugin-update-checker.php';
$ExampleUpdateChecker = new PluginUpdateChecker(
	'http://www.tychesoftwares.com/plugin-updates/woocommerce-booking-plugin/info.json',
	__FILE__
);*/

include('print-tickets.php');

global $TourUpdateChecker;
$TourUpdateChecker = '1.6';

// this is the URL our updater / license checker pings. This should be the URL of the site with EDD installed
define( 'EDD_SL_STORE_URL_TOUR_BOOK', 'http://www.tychesoftwares.com/' ); // IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system

// the name of your product. This is the title of your product in EDD and should match the download title in EDD exactly
define( 'EDD_SL_ITEM_NAME_TOUR_BOOK', 'Tour Operators Addon for the WooCommerce Booking and Appointment Plugin' ); // IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system

if( !class_exists( 'EDD_TOUR_BOOK_Plugin_Updater' ) ) {
	// load our custom updater if it doesn't already exist
	include( dirname( __FILE__ ) . '/plugin-updates/EDD_TOUR_BOOK_Plugin_Updater.php' );
}

// retrieve our license key from the DB
$license_key = trim( get_option( 'edd_sample_license_key_tour_book' ) );

// setup the updater
$edd_updater = new EDD_TOUR_BOOK_Plugin_Updater( EDD_SL_STORE_URL_TOUR_BOOK, __FILE__, array(
		'version' 	=> '1.6', 		// current version number
		'license' 	=> $license_key, 	// license key (used get_option above to retrieve from DB)
		'item_name' => EDD_SL_ITEM_NAME_TOUR_BOOK, 	// name of this plugin
		'author' 	=> 'Ashok Rane'  // author of this plugin
)
);

function is_bkap_tours_active() {
	if (is_plugin_active('bkap-tour-operators/tour_operators_addon.php')) {
		return true;
	}
	else {
		return false;
	}
}

load_plugin_textdomain('tour_operators', false, dirname( plugin_basename( __FILE__ ) ) . '/');
{
/**
 * tour_operators class
 **/
if (!class_exists('tour_operators')) {

	class tour_operators {

		public function __construct() {
			register_activation_hook( __FILE__, array(&$this, 'operators_activate'));
			register_deactivation_hook( __FILE__, array(&$this, 'operators_deactivate'));
			
			add_action( 'admin_notices', array( &$this, 'tour_operator_error_notice' ) );
			
			add_action('bkap_add_submenu', array(&$this, 'operator_tour_submenu'), 11 );
			add_action('bkap_before_add_to_cart_button',array(&$this, 'add_comment_field'), 10, 1);
		
			add_filter('bkap_addon_add_cart_item_data', array(&$this, 'tours_add_cart_item_data'), 10, 2);
			
			add_filter('bkap_get_item_data', array(&$this, 'get_item_data'), 10, 2 );
			add_action('bkap_update_order', array(&$this, 'tours_order_item_meta'), 10,2);
			add_filter('bkap_save_product_settings', array(&$this, 'tour_settings_save'),10,2);
			add_action( 'woocommerce_single_product_summary', array(&$this, 'wc_add_tour_operator') );
			// Add the tour operators tab
			add_action('bkap_add_tabs',array(&$this,'tours_tab'),30,1);
			add_action('bkap_after_listing_enabled', array(&$this, 'assign_tours'),30,1);
			add_action( 'show_user_profile', array(&$this, 'extra_user_profile_fields') );
			add_action( 'edit_user_profile', array(&$this, 'extra_user_profile_fields') );
			add_action( 'personal_options_update', array(&$this, 'save_extra_user_profile_fields') );
			add_action( 'edit_user_profile_update', array(&$this, 'save_extra_user_profile_fields') );
			add_filter('the_posts', array(&$this, 'filter_posts') , 1 );
			// tour operator data on the view bookings page
			add_filter('bkap_bookings_table_data',array(&$this,'tour_column_data'),20,1);
			// CSV file data
			add_filter('bkap_bookings_export_data',array(&$this,'tours_generate_data_export'),20,1);
			
            add_filter('user_has_cap', array($this, 'user_has_cap'), 10, 3);
            
            //Hook to add checkbox for send tickets to tour operators
            add_action('bkap_after_global_holiday_field', array('tour_operators_print_tickets','checkbox_settings'));
            add_filter('bkap_save_global_settings',array('tour_operators_print_tickets','save_global_settings'), 10, 1);
            add_action('woocommerce_order_status_completed',array('tour_operators_print_tickets','send_tickets'), 10, 1);
            if ( get_option( 'woocommerce_version' ) >= "2.3" ) {
            	add_action( 'woocommerce_email_customer_details', array('tour_operators_print_tickets','tour_operators_details'), 11, 3 );
            }
            else {
            	add_action( 'woocommerce_email_after_order_table', array('tour_operators_print_tickets','tour_operators_details'), 11, 3 );
            }

    		add_action('admin_init', array(&$this, 'edd_sample_register_option_tour'));
			add_action('admin_init', array(&$this, 'edd_sample_deactivate_license_tour'));
			add_action('admin_init', array(&$this, 'edd_sample_activate_license_tour'));
	   }
		
	   function tour_operator_error_notice() {
	       if ( !is_plugin_active( 'woocommerce-booking/woocommerce-booking.php' ) ) {
	           echo "<div class=\"error\"><p>Tour Operators Addon is enabled but not effective. It requires WooCommerce Booking and Appointment plugin in order to work.</p></div>";
	       }
	   }
	   
	function edd_sample_activate_license_tour() {
					
				// listen for our activate button to be clicked
				if( isset( $_POST['edd_license_activate'] ) ) {
			
					// run a quick security check
					if( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) )
						return; // get out if we didn't click the Activate button
			
					// retrieve the license from the database
					$license = trim( get_option('edd_sample_license_key_tour_book' ) );
						
			
					// data to send in our API request
					$api_params = array(
							'edd_action'=> 'activate_license',
							'license' 	=> $license,
							'item_name' => urlencode( EDD_SL_ITEM_NAME_TOUR_BOOK ) // the name of our product in EDD
					);
			
					// Call the custom API.
					$response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_TOUR_BOOK ), array( 'timeout' => 15, 'sslverify' => false ) );
			
					// make sure the response came back okay
					if ( is_wp_error( $response ) )
						return false;
			
					// decode the license data
					$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			
					// $license_data->license will be either "active" or "inactive"
			
					update_option( 'edd_sample_license_status_tour_book', $license_data->license );
			
				}
			}
			
			
			/***********************************************
			 * Illustrates how to deactivate a license key.
			* This will descrease the site count
			***********************************************/
			
			function edd_sample_deactivate_license_tour() {
					
				// listen for our activate button to be clicked
				if( isset( $_POST['edd_license_deactivate'] ) ) {
			
					// run a quick security check
					if( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) )
						return; // get out if we didn't click the Activate button
			
					// retrieve the license from the database
					$license = trim( get_option( 'edd_sample_license_key_tour_book' ) );
						
			
					// data to send in our API request
					$api_params = array(
							'edd_action'=> 'deactivate_license',
							'license' 	=> $license,
							'item_name' => urlencode( EDD_SL_ITEM_NAME_TOUR_BOOK ) // the name of our product in EDD
					);
			
					// Call the custom API.
					$response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_TOUR_BOOK ), array( 'timeout' => 15, 'sslverify' => false ) );
			
					// make sure the response came back okay
					if ( is_wp_error( $response ) )
						return false;
			
					// decode the license data
					$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			
					// $license_data->license will be either "deactivated" or "failed"
					if( $license_data->license == 'deactivated' )
						delete_option( 'edd_sample_license_status_tour_book' );
			
				}
			}
			
			
			
			/************************************
			 * this illustrates how to check if
			* a license key is still valid
			* the updater does this for you,
			* so this is only needed if you
			* want to do something custom
			*************************************/
			
			function edd_sample_check_license() {
					
				global $wp_version;
					
				$license = trim( get_option( 'edd_sample_license_key_tour_book' ) );
					
				$api_params = array(
						'edd_action' => 'check_license',
						'license' => $license,
						'item_name' => urlencode( EDD_SL_ITEM_NAME_TOUR_BOOK )
				);
					
				// Call the custom API.
				$response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_TOUR_BOOK ), array( 'timeout' => 15, 'sslverify' => false ) );
					
					
				if ( is_wp_error( $response ) )
					return false;
					
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );
					
				if( $license_data->license == 'valid' ) {
					echo 'valid'; exit;
					// this license is still valid
				} else {
					echo 'invalid'; exit;
					// this license is no longer valid
				}
			}
			
			function edd_sample_register_option_tour() {
				// creates our settings in the options table
				register_setting('edd_tour_book_license', 'edd_sample_license_key_tour_book', array(&$this, 'edd_sanitize_license_tour' ));
			}
			
			
			function edd_sanitize_license_tour( $new ) {
				$old = get_option( 'edd_sample_license_key_tour_book' );
				if( $old && $old != $new ) {
					delete_option( 'edd_sample_license_status_tour_book' ); // new license has been entered, so must reactivate
				}
				return $new;
			}
			
			function edd_sample_license_page_tours() {
				$license 	= get_option( 'edd_sample_license_key_tour_book' );
				$status 	= get_option( 'edd_sample_license_status_tour_book' );
					
				?>
							<div class="wrap">
								<h2><?php _e('Plugin License Options'); ?></h2>
								<form method="post" action="options.php">
								
									<?php settings_fields('edd_tour_book_license'); ?>
									
									<table class="form-table">
										<tbody>
											<tr valign="top">	
												<th scope="row" valign="top">
													<?php _e('License Key'); ?>
												</th>
												<td>
													<input id="edd_sample_license_key_tour_book" name="edd_sample_license_key_tour_book" type="text" class="regular-text" value="<?php esc_attr_e( $license ); ?>" />
													<label class="description" for="edd_sample_license_key"><?php _e('Enter your license key'); ?></label>
												</td>
											</tr>
											<?php if( false !== $license ) { ?>
												<tr valign="top">	
													<th scope="row" valign="top">
														<?php _e('Activate License'); ?>
													</th>
													<td>
														<?php if( $status !== false && $status == 'valid' ) { ?>
															<span style="color:green;"><?php _e('active'); ?></span>
															<?php wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' ); ?>
															<input type="submit" class="button-secondary" name="edd_license_deactivate" value="<?php _e('Deactivate License'); ?>"/>
														<?php } else {
															wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' ); ?>
															<input type="submit" class="button-secondary" name="edd_license_activate" value="<?php _e('Activate License'); ?>"/>
														<?php } ?>
													</td>
												</tr>
											<?php } ?>
										</tbody>
									</table>	
									<?php submit_button(); ?>
								
								</form>
							<?php
						}

			function filter_posts( $posts ) {

			// create an array to hold the posts we want to show
			if(is_admin()):
				$new_posts = array();
				$user = new WP_User(get_current_user_id());
				if($user->roles[0]=='tour_operator') {
				// loop through all the post objects
					foreach( $posts as $post ) {
						if($post->post_type == 'page') {
							return $posts;
						}
						elseif($post->post_type != 'shop_order' ) {
							$include = false;
							$booking_settings = get_post_meta($post->ID, 'woocommerce_booking_settings', true);
							if($post->post_author == get_current_user_id() || (isset($booking_settings["booking_tour_operator"]) && $booking_settings["booking_tour_operator"]==get_current_user_id())) {
								$include = '1';
							}
							else {
								$inlcude = '0';
							}
							if ( $include == '1') {
								$new_posts[] = $post;
							}
						}
						elseif($post->post_type == 'shop_order') {
							global $wpdb;
							$check_query = "SELECT a.post_id FROM `".$wpdb->prefix."booking_history` as a join `".$wpdb->prefix."booking_order_history` as b on a.id=b.booking_id
										and b.order_id='".$post->ID."'";
										
							$results_check = $wpdb->get_results ($check_query);
							$flag = false;
							if(!empty($results_check )) {
								foreach($results_check as $res) {
									$product_id = $res->post_id;
									$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
		
									if(!isset($booking_settings["booking_tour_operator"]) ||(isset($booking_settings["booking_tour_operator"]) && $booking_settings["booking_tour_operator"]!=get_current_user_id())) {
										$flag = false;
									}
									elseif(isset($booking_settings["booking_tour_operator"]) && $booking_settings["booking_tour_operator"]==get_current_user_id()) {
										$flag = true;
										break;
									}
									else {
										$flag = false;
									}
								}
							}
								
							if($flag) {
							   $new_posts[] = $post;
							} 
						}	
					}
				}
				else {
						return $posts;
				}
			// send the new post array back to be used by WordPress
				return $new_posts;
			else:
				return $posts;
			endif;
		}
		
		function user_has_cap($all_caps, $caps, $args){
			 global $post,$shop_order;
			 global $wpdb;
			 if(isset($post->ID)) {
				if($args[0] == 'edit_post') {
					$flag = false;
					if ($post->post_status == "wc-cancelled" || $post->post_status == "wc-refunded") {
						$flag = true;
					}
					$check_query = "SELECT a.post_id FROM `".$wpdb->prefix."booking_history` as a join `".$wpdb->prefix."booking_order_history` as b on a.id=b.booking_id
								and b.order_id='".$post->ID."'";

					$results_check = $wpdb->get_results ( $check_query );
			
					if(!empty($results_check )) {
						foreach($results_check as $res) {
							$product_id = $res->post_id;
					
							$user_capab = get_user_meta(get_current_user_id(),'wp_capabilities');
							if (array_key_exists('administrator',$user_capab[0])) {
								$flag = true;
							}
							else {
								$flag = false;
							}
						}
					}
					else {
						$flag = true;
					}
					
					if(!$flag) {
				   		unset($all_caps['edit_shop_orders']);
					 	unset($all_caps['edit_others_shop_orders']);
          			} 
				}
			}		
			return $all_caps;		
		}
		
		function operators_activate(){
			global $wp_roles;
			$wp_roles = new WP_Roles();
			$wp_roles->remove_role("tour_operator");	
			$result = add_role('tour_operator', 'Tour Operator', array(
			'read' => true, // True allows that capability
			'edit_posts' => false,
			'delete_posts' => false, // Use false to explicitly deny
			));
			
			$result->add_cap('operator_bookings');
			$result->add_cap('read_posts');
			$result->add_cap('read_pages');
			$result->add_cap('edit_posts');
			$result->add_cap('read_products');
			$result->add_cap('edit_products');
			$result->add_cap('read_shop_orders');
			$result->add_cap('edit_shop_orders');
			$result->add_cap('edit_published_shop_orders');
			$result->add_cap('edit_others_shop_orders');

		}

		function operators_deactivate(){
			global $wp_roles;
			$wp_roles = new WP_Roles();
			$wp_roles->remove_role("tour_operator");	
		}
		
		function add_comment_field($settings){
			if(is_plugin_active('bkap-tour-operators/tour_operators_addon.php')){
			if(isset($settings['booking_show_comment']) && $settings['booking_show_comment'] == 'on')
			 echo  bkap_get_book_t('book.item-comments')."<textarea name='comments' id='comments'></textarea>";
			}
		}
		
		function tours_add_cart_item_data($cart_arr, $product_id) {
			$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
			if(isset($booking_settings['booking_show_comment']) && $booking_settings['booking_show_comment'] == 'on')
				if (isset($_POST['comments'])) {
					$cart_arr['comments'] = $_POST['comments'];
				}
				else {
					$cart_arr['comments'] = '';
				}
	
			return $cart_arr;
		
		}
	/*	function display_price($product_id)
			{
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true);
				
					$currency_symbol = get_woocommerce_currency_symbol();
					$show_price = 'show';
					print('<div id="show_addon_price" name="show_addon_price" class="show_addon_price" style="display:'.$show_price.';">'.$currency_symbol.' 0</div>');
				
			}*/
		function get_cart_item_from_session( $cart_item, $values ) {
		
			if (isset($values['bkap_booking'])) :
				$cart_item['bkap_booking'] = $values['bkap_booking'];
				$booking_settings = get_post_meta($cart_item['product_id'], 'woocommerce_booking_settings', true);
				if($cart_item['bkap_booking'][0]['comments'] != '') {
					$cart_item = $this->add_cart_item( $cart_item );
				}
			endif;
			return $cart_item;
		}	
		
		function get_item_data( $other_data, $cart_item ) {
			$booking_settings = get_post_meta($cart_item['product_id'], 'woocommerce_booking_settings', true);
			if(isset($booking_settings["booking_show_comment"]) && $booking_settings["booking_show_comment"] == 'on' && is_plugin_active('bkap-tour-operators/tour_operators_addon.php')) { 
				if (isset($cart_item['bkap_booking'])) :
					$price = '';
					foreach ($cart_item['bkap_booking'] as $booking) :
						if(isset($booking['comments'])):
							$price = $booking['comments'];
						endif;
					endforeach;
					if(!empty($price)) {
						$other_data[] = array(
								'name'    => bkap_get_book_t('book.item-comments'),
								'display' => $price
						);
					}
				endif;
			}
			
			return $other_data;
		}
			
		function tours_order_item_meta( $values,$order) {
			global $wpdb;
			$product_id = $values['product_id'];
			$booking = $values['bkap_booking'];
			$order_item_id = $order->order_item_id;
			$order_id = $order->order_id;
			if(isset($values['bkap_booking'][0]['comments']) && !empty($values['bkap_booking'][0]['comments'])) {
				woocommerce_add_order_item_meta($order_item_id,  bkap_get_book_t('book.item-comments'),$values['bkap_booking'][0]['comments'], true );
			}	
		}
				

		function tour_settings_save($booking_settings, $product_id){
			if(isset($_POST['booking_tour_operator']) && !empty($_POST["booking_tour_operator"])) {
				$booking_settings['booking_tour_operator'] = $_POST['booking_tour_operator']; 
				if(isset($_POST['show_tour_operator'])) {
					$booking_settings['show_tour_operator'] = 'on';
				}
				if(isset($_POST['booking_show_comment'])) {
					$booking_settings['booking_show_comment'] = 'on';
				}
			}
			return $booking_settings;
		}

		function wc_add_tour_operator() {
			$booking_settings = get_post_meta(get_the_ID(), 'woocommerce_booking_settings', true);
			if(isset($booking_settings["show_tour_operator"]) && $booking_settings["show_tour_operator"]=='on' && isset($booking_settings["booking_tour_operator"]) && $booking_settings["booking_tour_operator"]>0) {
				$booking_tour_operator = $booking_settings["booking_tour_operator"];
				$user = get_userdata( $booking_tour_operator );	
				if(isset($user->user_login)) {
					?>
					<div class="2nd-tile">
					<?php 
					echo "Tour Operator: ".$user->user_login;  ?>
					</div>
	<?php   	}
			}
		}

		/*********************************************************
		 * Add the rental addon vertical tab
		********************************************************/
		function tours_tab($product_id) {
			?>
			<li><a id="tours"> <?php _e( 'Tour Operators', 'woocommerce-booking' );?> </a></li>
			<?php
		}
		
		function assign_tours($product_id){
			$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);


			if(isset($booking_settings["booking_tour_operator"]) and !empty($booking_settings["booking_tour_operator"])){
				$booking_tour_operator = $booking_settings["booking_tour_operator"];
			if(isset($booking_settings["show_tour_operator"]) and !empty($booking_settings["show_tour_operator"]))
				$show_tour_operator = $booking_settings["show_tour_operator"];
			}
			if(isset($booking_settings["booking_show_comment"]) and !empty($booking_settings["booking_show_comment"]))
				$show_comment = $booking_settings["booking_show_comment"];
			
			?>
			<div id="tours_page" style="display:none;">
			<table class='form-table'>
			<tr id="tour_operators">
			<th>
			<label for="booking_tour_operator">  <?php _e( 'Select Tour Operator:', 'woocommerce-booking' );?> </label>

			</th>

			<td>


			<select id="booking_tour_operator" name="booking_tour_operator">
			<option label="">Select Operator</option>
			<?php
									$blogusers = get_users('blog_id=1&orderby=nicename&role=tour_operator');
			foreach ($blogusers as $user) {
			if(isset($booking_tour_operator) && $booking_tour_operator == $user->ID)
				$selected  = 'selected';
				else
				$selected = '';
			
			echo "<option value='".$user->ID."' $selected>".$user->user_login."</option>";
			}
			
			if(isset($show_tour_operator) && $show_tour_operator  == 'on')
			 $tour_show = 'checked';
			else
			 $tour_show = '';
			if(isset($show_comment) && $show_comment  == 'on')
			 $comment_show = 'checked';
			else
			 $comment_show = '';
			?>
			</select>

			<img class="help_tip" width="16" height="16" data-tip="<?php _e('Select Tour Operator', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />

			</td>

			</tr>
			<tr>
			<th>
			<label for="booking_tour_operator"> <?php _e( 'Show Tour Operator:', 'woocommerce-booking' );?> </label>

			</th>

			<td>
			<input type="checkbox" name="show_tour_operator" id="show_tour_operator" value="yes" <?php echo $tour_show;?>></input>
			<img class="help_tip" width="16" height="16" style="margin-left:128px;" data-tip="<?php _e('Please select this checkbox if you want to show Tour Operator on product page', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png"/>
				</td>
			</tr>
			<tr>
			<th>
			<label for="booking_show_comment"> <?php _e( 'Show Comment Field:', 'woocommerce-booking' );?> </label>

			</th>

			<td>
			<input type="checkbox" name="booking_show_comment" id="booking_show_comment" value="yes" <?php echo $comment_show;?>></input>
			<img class="help_tip" width="16" height="16" style="margin-left:128px;" data-tip="<?php _e('Please select this checkbox if you want to show comment field on product page', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png"/>
				</td>
			</tr>
			</table>
			</div>
			<?php

		}



		function operator_tour_submenu() 
		{
			add_submenu_page(
				'booking_settings', // Third party plugin Slug 
				'Tour Operators', 
				'Tour Operators', 
				'manage_woocommerce', 
				'manage_tours', 
				array(&$this,'operator_tours_page')
			);
			 add_submenu_page(
				'booking_settings', // Third party plugin Slug 
				'View Bookings', 
				'View Bookings', 
				'operator_bookings', 
				'operator_bookings', 
				array(&$this,'operator_bookings_page')
			);
			 // License menu page
			 $page = add_submenu_page('booking_settings', __( 'Activate Tour Operators License', 'woocommerce-booking' ), __( 'Activate Tour Operators License', 'woocommerce-booking' ), 'manage_woocommerce', 'tours_license_page', array(&$this, 'edd_sample_license_page_tours' ));
		}

		function operator_tours_page(){
			?>
			<div class="wrap">
			<h2>
			Tour Operators
				</h2>
			<table class="wp-list-table widefat fixed users" cellspacing="0">
				<thead>
				<tr>
					<th scope="col" id="username" class="manage-column column-username sortable desc" style=""><a href="users.php?update=remove&amp;orderby=login&amp;order=asc"><span>Username</span><span class="sorting-indicator"></span></a></th><th scope="col" id="email" class="manage-column column-email sortable desc" style=""><a href="users.php?update=remove&amp;orderby=email&amp;order=asc"><span>E-mail</span><span class="sorting-indicator"></span></a></th><th scope="col" id="role" class="manage-column column-role" style="">Paypal Account</th><th scope="col" id="role" class="manage-column column-role" style="">Address</th>
				<tbody>
				
				<?php
				$blogusers = get_users('blog_id=1&orderby=nicename&role=tour_operator');
				if(empty($blogusers)){
				 echo "<tr><td colspan='4' align='center'><b>Tour Operator list is empty currently</b</td></tr>";
				}else{
					foreach ($blogusers as $user) {
						echo '<tr>';
						echo '<td><a href="user-edit.php?user_id='.$user->ID.'">' . $user->user_login . '</a></td>';
						echo '<td>' . $user->user_email . '</td>';
						echo '<td>' . esc_attr( get_the_author_meta( 'paypal', $user->ID ) ). ' </td>';
						echo '<td>' .esc_attr( get_the_author_meta( 'address', $user->ID ) ). '</td>';
						echo '</tr>';
					}
				}
			?>
				
				</tbody>
				</thead>


			</table>
			</div>
			<?php
		}



		function save_extra_user_profile_fields( $user_id ) {

			if ( !current_user_can( 'edit_user', $user_id ) ) { return false; }
			$userid =  new WP_User( $user_id );
			if($userid->roles[0]=='tour_operator'){
				update_user_meta( $user_id, 'address', $_POST['address'] );
				update_user_meta( $user_id, 'paypal', $_POST['paypal'] );
				update_user_meta( $user_id, 'phone', $_POST['phone'] );
			}
		}
		function extra_user_profile_fields( $user ) { 
			$userid =  new WP_User( $user->ID );
			if($userid->roles[0]=='tour_operator'){?>
			<h3><?php _e("Extra profile information", "blank"); ?></h3>

			<table class="form-table">
			<tr>
			<th><label for="address"><?php _e("Address"); ?></label></th>
			<td>
			<input type="text" name="address" id="address" value="<?php echo esc_attr( get_the_author_meta( 'address', $user->ID ) ); ?>" class="regular-text" /><br />
			<span class="description"><?php _e("Please enter your address."); ?></span>
			</td>
			</tr>
			<th><label for="paypal"><?php _e("Paypal Account Number"); ?></label></th>
			<td>
			<input type="text" name="paypal" id="paypal" value="<?php echo esc_attr( get_the_author_meta( 'paypal', $user->ID ) ); ?>" class="regular-text" /><br />
			<span class="description"><?php _e("Please enter your Paypal account number."); ?></span>
			</td>
			</tr>
			<tr>
			<th><label for="paypal"><?php _e("Phone"); ?></label></th>
			<td>
			<input type="text" name="phone" id="phone" value="<?php echo esc_attr( get_the_author_meta( 'phone', $user->ID ) ); ?>" class="regular-text" /><br />
			<span class="description"><?php _e("Please enter your Phone number."); ?></span>
			</td>
			</tr>
			</table>
			<?php } 
		}
		function operator_bookings_page(){
			// Call the View Bookings page function here, so all the entries are passed on...
			view_bookings::bkap_woocommerce_history_page();
		}
		
		function tour_column_data($booking_data) {
			$user = new WP_User( get_current_user_id() );
			foreach( $booking_data as $key => $value ) {
				if( $user->roles[0] == 'tour_operator' ) {
					$booking_settings = get_post_meta($value->product_id, 'woocommerce_booking_settings', true);
					if(isset($booking_settings['booking_tour_operator']) &&  $booking_settings['booking_tour_operator'] == get_current_user_id()){
					}
					else {
						// Unset the entries that do not belong to this tour operator (user)
						unset($booking_data[$key]);
					}
				}
			}
			return $booking_data;
		}
		
		function tours_generate_data_export($report) {
			$user = new WP_User( get_current_user_id() );
			foreach( $report as $key => $value ) {
				if( $user->roles[0] == 'tour_operator' ) {
					$booking_settings = get_post_meta($value->product_id, 'woocommerce_booking_settings', true);
					if(isset($booking_settings['booking_tour_operator']) &&  $booking_settings['booking_tour_operator'] == get_current_user_id()){
					}
					else {
						// Unset the entries that do not belong to this tour operator (user)
						unset($report[$key]);
					}
				}
			}
			return $report;
		}
		}
	}
	$tour_operators = new tour_operators();
}
?>
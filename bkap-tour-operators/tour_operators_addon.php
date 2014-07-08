<?php 
/*
Plugin Name: Tour Operators Addon - WooCommerce Booking Plugin 
Plugin URI: http://www.tychesoftwares.com/store/premium-plugins/woocommerce-booking-plugin
Description: This plugin lets you add and Mange Tour Operators.
Version: 1.1
Author: Ashok Rane
Author URI: http://www.tychesoftwares.com/
*/

/*require 'plugin-updates/plugin-update-checker.php';
$ExampleUpdateChecker = new PluginUpdateChecker(
	'http://www.tychesoftwares.com/plugin-updates/woocommerce-booking-plugin/info.json',
	__FILE__
);*/

global $TourUpdateChecker;
$TourUpdateChecker = '1.1';

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
		'version' 	=> '1.1', 		// current version number
		'license' 	=> $license_key, 	// license key (used get_option above to retrieve from DB)
		'item_name' => EDD_SL_ITEM_NAME_TOUR_BOOK, 	// name of this plugin
		'author' 	=> 'Ashok Rane'  // author of this plugin
)
);

load_plugin_textdomain('bkap_deposits', false, dirname( plugin_basename( __FILE__ ) ) . '/');
{
/**
 * bkap_deposits class
 **/
if (!class_exists('tour_operators')) {

	class tour_operators {

		public function __construct() {
			register_activation_hook( __FILE__, array(&$this, 'operators_activate'));
			register_deactivation_hook( __FILE__, array(&$this, 'operators_deactivate'));
			add_action('bkap_add_submenu', array(&$this, 'operator_tour_submenu'), 11 );
			add_action('bkap_before_add_to_cart_button',array(&$this, 'add_comment_field'), 10, 1);
		
			add_filter('bkap_add_cart_item_data', array(&$this, 'add_cart_item_data'), 10, 2);
			
			//add_filter('operator_get_cart_item_from_session', array(&$this, 'get_cart_item_from_session'),10,2);
			add_filter('bkap_get_item_data', array(&$this, 'get_item_data'), 10, 2 );
			add_action('bkap_operator_update_order', array(&$this, 'order_item_meta'), 10,2);
			add_filter('bkap_save_product_settings', array(&$this, 'tour_settings_save'),10,2);
			add_action( 'woocommerce_single_product_summary', array(&$this, 'wc_add_tour_operator') );
			add_action('bkap_after_listing_enabled', array(&$this, 'assign_tours'));
			add_action( 'show_user_profile', array(&$this, 'extra_user_profile_fields') );
			add_action( 'edit_user_profile', array(&$this, 'extra_user_profile_fields') );
			add_action( 'personal_options_update', array(&$this, 'save_extra_user_profile_fields') );
			add_action( 'edit_user_profile_update', array(&$this, 'save_extra_user_profile_fields') );
			add_filter('the_posts', array(&$this, 'filter_posts') , 1 );
		//	add_action('bkap_display_price_div', array(&$this, 'display_price'),10,1);
			
            add_filter('user_has_cap', array($this, 'user_has_cap'), 10, 3);
            
    		add_action('admin_init', array(&$this, 'edd_sample_register_option_tour'));
			add_action('admin_init', array(&$this, 'edd_sample_deactivate_license_tour'));
			add_action('admin_init', array(&$this, 'edd_sample_activate_license_tour'));
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
				if($user->roles[0]=='tour_operator')
				{
				//
				// loop through all the post objects
				//
				
					foreach( $posts as $post ) 
					{
						if($post->post_type == 'page')
						{
							return $posts;
						}
						elseif($post->post_type != 'shop_order' )
						{
							$include = false;
							$booking_settings = get_post_meta($post->ID, 'woocommerce_booking_settings', true);
							if($post->post_author == get_current_user_id() || (isset($booking_settings["booking_tour_operator"]) && $booking_settings["booking_tour_operator"]==get_current_user_id()))
							{
								$include = '1';
							}
							else
								$inlcude = '0';
							if ( $include == '1') 
							{
								$new_posts[] = $post;
							}
						}
						elseif($post->post_type == 'shop_order')
						{
							global $wpdb;
							$check_query = "SELECT a.post_id FROM `".$wpdb->prefix."booking_history` as a join `".$wpdb->prefix."booking_order_history` as b on a.id=b.booking_id
										and b.order_id='".$post->ID."'";
										
		
							$results_check = $wpdb->get_results ($check_query);
							$flag = false;
							if(!empty($results_check ))
							{
								foreach($results_check as $res)
								{
									$product_id = $res->post_id;
									$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
		
									if(!isset($booking_settings["booking_tour_operator"]) ||(isset($booking_settings["booking_tour_operator"]) && $booking_settings["booking_tour_operator"]!=get_current_user_id()))
										$flag = false;
									elseif(isset($booking_settings["booking_tour_operator"]) && $booking_settings["booking_tour_operator"]==get_current_user_id())
									{
										$flag = true;
										break;
									}
									else
										$flag = false;
								}
							}
								
							if($flag)	
							{
							   $new_posts[] = $post;
							} 
						}	
					}
				}
				else
				{
						return $posts;
				}
			//
			// send the new post array back to be used by WordPress
			//
			
			return $new_posts;
			else:
			return $posts;
			endif;
		}
		function user_has_cap($all_caps, $caps, $args){
		
			 global $post,$shop_order;
			 global $wpdb;
			 if(isset($post->ID))
			 {
				if($args[0] == 'edit_post')
				{
					$check_query = "SELECT a.post_id FROM `".$wpdb->prefix."booking_history` as a join `".$wpdb->prefix."booking_order_history` as b on a.id=b.booking_id
								and b.order_id='".$post->ID."'";

					$results_check = $wpdb->get_results ( $check_query );
					$flag = false;
			
					if(!empty($results_check ))
					{
						foreach($results_check as $res)
						{
							$product_id = $res->post_id;
				//			$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
					
							$user_capab = get_user_meta(get_current_user_id(),'wp_capabilities');
							
					/*		if(!isset($booking_settings["booking_tour_operator"]) ||(isset($booking_settings["booking_tour_operator"]) && $booking_settings["booking_tour_operator"]!=get_current_user_id()))
							$flag = false;
							elseif(isset($booking_settings["booking_tour_operator"]) && $booking_settings["booking_tour_operator"]==get_current_user_id())
							{$flag = true;
							}*/
							if (array_key_exists('administrator',$user_capab[0]))
								$flag = true;
							else
								$flag = false;
						}
					}
					
					if(!$flag)	
					{
				   		unset($all_caps['edit_shop_orders']);
			//	    	unset($all_caps['edit_published_shop_orders']);
					 	unset($all_caps['edit_others_shop_orders']);
          			} 
          			else
					{
					
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
			 echo  book_t('book.item-comments')."<textarea name='comments' id='comments'></textarea>";
			}
		}
		function add_cart_item_data($cart_arr, $product_id)
		{
		
			$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
			if(isset($booking_settings['booking_show_comment']) && $booking_settings['booking_show_comment'] == 'on')
				$cart_arr['comments'] = $_POST['comments'];
	
			return $cart_arr;
		
		}
	/*	function display_price($product_id)
			{
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true);
				
					$currency_symbol = get_woocommerce_currency_symbol();
					$show_price = 'show';
					print('<div id="show_addon_price" name="show_addon_price" class="show_addon_price" style="display:'.$show_price.';">'.$currency_symbol.' 0</div>');
				
			}*/
		function get_cart_item_from_session( $cart_item, $values ) 
		{
		
			if (isset($values['booking'])) :
				$cart_item['booking'] = $values['booking'];
				$booking_settings = get_post_meta($cart_item['product_id'], 'woocommerce_booking_settings', true);
				if($cart_item['booking'][0]['comments'] != '')
				{
					
					$cart_item = $this->add_cart_item( $cart_item );
					
				}
				endif;
				return $cart_item;
		}	
		function get_item_data( $other_data, $cart_item ) 
			{
				$booking_settings = get_post_meta($cart_item['product_id'], 'woocommerce_booking_settings', true);
				if(isset($booking_settings["booking_show_comment"]) && $booking_settings["booking_show_comment"] == 'on' && is_plugin_active('bkap-tour-operators/tour_operators_addon.php')){ 
				if (isset($cart_item['booking'])) :
				$price = '';
				foreach ($cart_item['booking'] as $booking) :
					if(isset($booking['comments'])):
						$price = $booking['comments'];
					endif;
					
				endforeach;
				if(!empty($price))
				$other_data[] = array(
									'name'    => book_t('book.item-comments'),
									'display' => $price
							);
				endif;
				}
				
				
				return $other_data;
			}
		function order_item_meta( $values,$order) 
			{
				global $wpdb;
				$product_id = $values['product_id'];
				$booking = $values['booking'];
				$order_item_id = $order->order_item_id;
				$order_id = $order->order_id;
				if(isset($values['booking'][0]['comments']) && !empty($values['booking'][0]['comments']))
				woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-comments'),$values['booking'][0]['comments'], true );
				
					
			}
				

		function tour_settings_save($booking_settings, $product_id){


			if(isset($_POST['booking_tour_operator']) && !empty($_POST["booking_tour_operator"]))

			{
		
				$booking_settings['booking_tour_operator'] = $_POST['booking_tour_operator']; 
				if(isset($_POST['show_tour_operator']))
				$booking_settings['show_tour_operator'] = 'on';
				if(isset($_POST['booking_show_comment']))
				$booking_settings['booking_show_comment'] = 'on';

			}
			return $booking_settings;
		}

 
		
		function wc_add_tour_operator()
		{
			
				$booking_settings = get_post_meta(get_the_ID(), 'woocommerce_booking_settings', true);
				if(isset($booking_settings["show_tour_operator"]) && $booking_settings["show_tour_operator"]=='on' && isset($booking_settings["booking_tour_operator"]) && $booking_settings["booking_tour_operator"]>0)
				{
						$booking_tour_operator = $booking_settings["booking_tour_operator"];
						$user = get_userdata( $booking_tour_operator );	
						if(isset($user->user_login))
							?>
										<div class="2nd-tile">
											<?php 
				echo "Tour Operator: ".$user->user_login;  ?>
			</div>
			<?php }
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
			<script type="text/javascript">
					jQuery(".woo-nav-tab-wrapper").append("<a href=\"javascript:void(0);\" class=\"nav-tab\" id=\"tours\" onclick=\"tab_tour_display('tours')\"> <?php _e( 'Tour Operators', 'woocommerce-booking' );?> </a>");
					function tab_tour_display(id){

						jQuery( "#tours_page").show();
						jQuery( "#payments_page").hide();
						jQuery( "#date_time" ).hide();
						jQuery( "#listing_page" ).hide();
						jQuery( "#seasonal_pricing" ).hide();
						jQuery( "#list" ).attr("class","nav-tab");
						jQuery( "#addnew" ).attr("class","nav-tab");
						jQuery( "#payments" ).attr("class","nav-tab");
						jQuery( "seasonalpricing" ).attr("class","nav-tab");
						jQuery( "#tours" ).attr("class","nav-tab nav-tab-active");
					
					}
				</script>
			<div id="tours_page" style="display:none;">
			<table class='form-table'>
			<tr id="tour_operators">
			<th>
			<label for="booking_tour_operator"> <b> <?php _e( 'Select Tour Operator:', 'woocommerce-booking' );?> </b> </label>

			</th>

			<td>


			<select id="booking_tour_operator" name="booking_tour_operator">
			<option label="">Select Operator</option>
			<?php
									$blogusers = get_users('blog_id=1&orderby=nicename&role=tour_operator');
			foreach ($blogusers as $user) {
			if($booking_tour_operator == $user->ID)
				$selected  = 'selected';
				else
				$selected = '';
			
			echo "<option value='".$user->ID."' $selected>".$user->user_login."</option>";
			}
			
			if($show_tour_operator  == 'on')
			 $tour_show = 'checked';
			else
			 $tour_show = '';
			if($show_comment  == 'on')
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
			<label for="booking_tour_operator"> <b> <?php _e( 'Show Tour Operator:', 'woocommerce-booking' );?> </b> </label>

			</th>

			<td>
			<input type="checkbox" name="show_tour_operator" id="show_tour_operator" value="yes" <?php echo $tour_show;?>></input><img class="help_tip" width="16" height="16" data-tip="<?php _e('Please select this checkbox if you want to show Tour Operator on product page', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png"/>
				</td>
			</tr>
			<tr>
			<th>
			<label for="booking_show_comment"> <b> <?php _e( 'Show Comment Field:', 'woocommerce-booking' );?> </b> </label>

			</th>

			<td>
			<input type="checkbox" name="booking_show_comment" id="booking_show_comment" value="yes" <?php echo $comment_show;?>></input><img class="help_tip" width="16" height="16" data-tip="<?php _e('Please select this checkbox if you want to show comment field on product page', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png"/>
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
		/*
			if(isset($_GET["action"]) && $_GET["action"] == 'add'){
			$error = $success = '';
			if(isset($_POST["submit"]) && $_POST["submit"] == 'Submit'){

			$user_name = $_POST["username"];
			$user_email = $_POST["email"];
			$password = $_POST["password"];
			$user_id = username_exists( $user_name );
			if ( !$user_id and email_exists($user_email) == false ) {
			$random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
			$user_id = wp_create_user( $user_name, $password, $user_email );
			if($user_id>0)
			{ $success = "Tour Operator is added successfully";
			update_user_meta( $user_id, 'address', $_POST['address'] );
			update_user_meta( $user_id, 'phone', $_POST['phone'] );
			update_user_meta( $user_id, 'paypal', $_POST['paypal'] );
			$u = new WP_User( $user_id );
			$u->remove_role( 'subscriber' );

			// Add role
			$u->add_role( 'tour_operator' );
			}else{
				print_r($user_id);
			}
			} else {
				$error = __('User already exists.');
				
			}
			}
			?>
			<div class="wrap">
			<?php if(!empty($success)){?>
			<div id="message" class="updated fade"><p><strong><?php _e( $success, 'woocommerce-booking' ); ?></strong></p><p><span><a href="admin.php?page=manage_tours">Back to Tour Operator Page</a></div>
			</div>
			<?php } else{ 
			if(!empty($error)){?>
			
			
			<div id="message" class="updated fade"><p><strong><?php _e( $error, 'woocommerce-booking' ); ?></strong></p></div>
			<?php } ?>
			<h2>Add Tour Operator</h2>
			<form action="" method="post" id="tour_operator">
			<table class="wp-list-table" cellspacing="0">
			<tr>
			<th><label for="username"><?php _e("Username"); ?></label></th>
			<td>
			<input type="text" name="username" id="username" value="" class="regular-text" /><br />
			<span class="description"><?php _e("Please enter username."); ?></span>
			</td>
			</tr>
			<tr>
			<th><label for="email"><?php _e("Email"); ?></label></th>
			<td>
			<input type="text" name="email" id="email" value="" class="regular-text" /><br />
			<span class="description"><?php _e("Please enter email."); ?></span>
			</td>
			</tr>
			<tr>
			<th><label for="password"><?php _e("Password"); ?></label></th>
			<td>
			<input type="password" name="password" id="password" value="" class="regular-text" /><br />
			<span class="description"><?php _e("Please enter password."); ?></span>
			</td>
			</tr>
				<tr>
			<th><label for="address"><?php _e("Address"); ?></label></th>
			<td>
			<input type="text" name="address" id="address" value="" class="regular-text" /><br />
			<span class="description"><?php _e("Please enter address."); ?></span>
			</td>
			</tr>
			<tr>
			<th><label for="paypal"><?php _e("Paypal Account Number"); ?></label></th>
			<td>
			<input type="text" name="paypal" id="paypal" value="" class="regular-text" /><br />
			<span class="description"><?php _e("Please enter Paypal account number."); ?></span>
			</td>
			</tr>
			<tr>
			<th><label for="paypal"><?php _e("Phone"); ?></label></th>
			<td>
			<input type="text" name="phone" id="phone" value="" class="regular-text" /><br />
			<span class="description"><?php _e("Please enter Phone number."); ?></span>
			</td>
			</tr>
				<tr>
				<td colspan="2" align="center">
				<input name="submit" type="submit" value="Submit">
				</td>
				</tr>
				
				</table>
				</form>
				</div>
				<?php
			}	
			}else{
			/*
			$user_name='mari4';
			$user_email = 'mari4@gmail.com';
			$user_id = username_exists( $user_name );
			if ( !$user_id and email_exists($user_email) == false ) {
				$random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
				$user_id = wp_create_user( $user_name, 'mari', $user_email );
			} else {
				$random_password = __('User already exists.  Password inherited.');
			}
			$u = new WP_User( $user_id );
			$u->remove_role( 'subscriber' );

			// Add role
			$u->add_role( 'tour_operator' );
			*/
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
			//}
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
			global $wpdb;

				

			$query_order = "SELECT DISTINCT order_id FROM `" . $wpdb->prefix . "woocommerce_order_items`  ";

			$order_results = $wpdb->get_results( $query_order );

			

			$var = $today_checkin_var = $today_checkout_var = $booking_time_ = "";

			

			$booking_time_label = book_t('book.item-cart-time');

			
				foreach ( $order_results as $id_key => $id_value )
			{
				$order = new WC_Order( $id_value->order_id );
		
				$order_items = $order->get_items();
		
				$today_query = "SELECT * FROM `".$wpdb->prefix."booking_history` AS a1,`".$wpdb->prefix."booking_order_history` AS a2 WHERE a1.id = a2.booking_id AND a2.order_id = '".$id_value->order_id."'";
				$results_date = $wpdb->get_results ( $today_query );
				
				$c = 0;
				foreach ($order_items as $items_key => $items_value )
				{
					if(isset($items_value['product_id'])){
						$booking_settings = get_post_meta($items_value['product_id'], 'woocommerce_booking_settings', true);
						if(isset($booking_settings['booking_tour_operator']) &&  $booking_settings['booking_tour_operator'] == get_current_user_id()){
							$start_date = $end_date = $booking_time = "";
							if ( isset($results_date[$c]->start_date) )
							{
							if (isset($results_date[$c]) && isset($results_date[$c]->start_date) && ( $results_date[$c]->post_id == $items_value['product_id'] )) $start_date = $results_date[$c]->start_date;
							
							if (isset($results_date[$c]) && isset($results_date[$c]->end_date)) $end_date = $results_date[$c]->end_date;
							
							if ($start_date == '0000-00-00' || $start_date == '1970-01-01') $start_date = '';
							if ($end_date == '0000-00-00' || $end_date == '1970-01-01') $end_date = '';
							
							if (isset($items_value['Booking Time']))
							{
								$booking_time = $items_value['Booking Time'];
							}
							
							$var .= "<tr>
							<td>".$id_value->order_id."</td>
							<td>".$order->billing_first_name." ".$order->billing_last_name."</td>
							<td>".$items_value['name']."</td>
							<td>".$start_date."</td>
							<td>".$end_date."</td>
							<td>".$booking_time."</td>
							<td>".$items_value['line_total']."</td>
							<td>".$order->completed_date."</td>
							<td><a href=\"post.php?post=". $id_value->order_id."&action=edit\">View Order</a></td>
							</tr>";

								

							

						

							if ( $start_date != "" ) $c++;

							}

						}
					}
				}

			}
			$swf_path = plugins_url()."/woocommerce-booking/TableTools/media/swf/copy_csv_xls.swf";
			?>
			<script>
						
						jQuery(document).ready(function() {
						 	var oTable = jQuery('.datatable').dataTable( {
									"bJQueryUI": true,
									"sScrollX": "",
									"bSortClasses": false,
									"aaSorting": [[0,'desc']],
									"bAutoWidth": true,
									"bInfo": true,
									"sScrollY": "100%",	
									"sScrollX": "100%",
									"bScrollCollapse": true,
									"sPaginationType": "full_numbers",
									"bRetrieve": true,
									"oLanguage": {
													"sSearch": "Search:",
													"sInfo": "Showing _START_ to _END_ of_TOTAL_ entries",
													"sInfoEmpty": "Showing 0 to 0 of 0 entries",
													"sZeroRecords": "No matching records found",
													"sInfoFiltered": "(filtered from _MAX_total entries)",
													"sEmptyTable": "No data available in table",
													"sLengthMenu": "Show _MENU_ entries",
													"oPaginate": {
																	"sFirst":    "First",
																	"sPrevious": "Previous",
																	"sNext":     "Next",
																	"sLast":     "Last"
																  }
												 },
									 "sDom": 'T<"clear"><"H"lfr>t<"F"ip>',
							         "oTableTools": {
											            "sSwfPath": "<?php echo plugins_url(); ?>/woocommerce-booking/TableTools/media/swf/copy_csv_xls_pdf.swf"
											        }
									 
						} );
					} );
						
						       
						</script>
						
						
			<div style="float: left;">

			<h2><strong>All Bookings</strong></h2>

			</div>

			<div>

			<table id="booking_history" class="display datatable" >

				<thead>

					<tr>

						<th><?php _e( 'Order ID' , 'woocommerce-booking' ); ?></th>

						<th><?php _e( 'Customer Name' , 'woocommerce-booking' ); ?></th>

						<th><?php _e( 'Product Name' , 'woocommerce-booking' ); ?></th>

						<th><?php _e( 'Check-in Date' , 'woocommerce-booking' ); ?></th>

						<th><?php _e( 'Check-out Date' , 'woocommerce-booking' ); ?></th>

						<th><?php _e( 'Booking Time' , 'woocommerce-booking' ); ?></th>

						<th><?php _e( 'Amount' , 'woocommerce-booking' ); ?></th>

						<th><?php _e( 'Booking Date' , 'woocommerce-booking' ); ?></th>

						<th><?php _e( 'Action' , 'woocommerce-booking' ); ?></th>

					</tr>

				</thead>

				<tbody>

					<?php echo $var;?>

				</tbody>

			</table>

			</div>

			<?php

				

						

							
			}
		}
	}
	$tour_operators = new tour_operators();
}
?>
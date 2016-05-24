<?php 
/*
Plugin Name: Tour Operators Addon
Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/tour-operators-addon-for-woocommerce-booking-appointment-plugin/
Description: This is an addon for the WooCommerce Booking & Appointment Plugin which lets you to add and Manage Tour Operators.
Version: 1.7.1
Author: Tyche Softwares
Author URI: http://www.tychesoftwares.com/
*/

/*require 'plugin-updates/plugin-update-checker.php';
$ExampleUpdateChecker = new PluginUpdateChecker(
	'http://www.tychesoftwares.com/plugin-updates/woocommerce-booking-plugin/info.json',
	__FILE__
);*/

global $TourUpdateChecker;
$TourUpdateChecker = '1.7.1';

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
		'version' 	=> '1.7.1', 		// current version number
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

register_uninstall_hook( __FILE__, 'tours_delete' );

function tours_delete() {

    global $wpdb;

    // delete all the option records which are present for imported GCal events and are not yet mapped
    $delete_imported_events = "DELETE FROM `" . $wpdb->prefix. "options`
	                       WHERE option_name like 'tours_imported_events_%'";
    $wpdb->query( $delete_imported_events );

    // delete the item IDs which were imported/exported to GCal
    $delete_item_ids = "DELETE FROM `" . $wpdb->prefix . "usermeta`
                        WHERE meta_key = 'tours_event_item_ids'";
    $wpdb->query( $delete_item_ids );

    // delete the GCal event IDs
    $delete_event_ids = "DELETE FROM `" . $wpdb->prefix . "usermeta`
                        WHERE meta_key = 'tours_event_uids_ids'";
    $wpdb->query( $delete_event_ids );

    //delete the user settings for Gcal
    $delete_sync_mode = "DELETE FROM `" . $wpdb->prefix . "usermeta`
                        WHERE meta_key = 'tours_calendar_sync_integration_mode'";
    $wpdb->query( $delete_sync_mode );

    $delete_calendar = "DELETE FROM `" . $wpdb->prefix . "usermeta`
                        WHERE meta_key = 'tours_calendar_details_1'";
    $wpdb->query( $delete_calendar );

    $delete_view_booking = "DELETE FROM `" . $wpdb->prefix . "usermeta`
                            WHERE meta_key = 'tours_add_to_calendar_view_booking'";
    $wpdb->query( $delete_view_booking );

    $delete_email_notification = "DELETE FROM `" . $wpdb->prefix . "usermeta`
                                    WHERE meta_key = 'tours_add_to_calendar_email_notification'";
    $wpdb->query( $delete_email_notification );
    
    $delete_ics_feed_urls = "DELETE FROM `" . $wpdb->prefix . "usermeta`
                                    WHERE meta_key = 'tours_ics_feed_urls'";
    $wpdb->query( $delete_ics_feed_urls );
    
    // delete the settings from the Addon Settings tab
    delete_option( 'bkap_send_tickets_to_tour_operators' );
    
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

			add_action( 'admin_enqueue_scripts', array(&$this, 'tours_enqueue_scripts_css' ) );
			
			add_filter('bkap_addon_add_cart_item_data', array(&$this, 'tours_add_cart_item_data'), 10, 2);
			
			add_filter('bkap_get_item_data', array(&$this, 'get_item_data'), 10, 2 );
			add_action('bkap_update_order', array(&$this, 'tours_order_item_meta'), 10,2);
			add_filter('bkap_save_product_settings', array(&$this, 'tour_settings_save'),10,2);
			add_action( 'woocommerce_single_product_summary', array(&$this, 'wc_add_tour_operator') );
			// Add the tour operators tab
			add_action('bkap_add_tabs',array(&$this,'tours_tab'),30,1);
			add_action('bkap_after_listing_enabled', array(&$this, 'assign_tours'),30,1);
			add_action( 'show_user_profile', array( &$this, 'extra_user_profile_fields' ), 10, 1 );
			add_action( 'edit_user_profile', array(&$this, 'extra_user_profile_fields') );
			add_action( 'personal_options_update', array( &$this, 'save_extra_user_profile_fields' ), 10, 1 );
			add_action( 'edit_user_profile_update', array(&$this, 'save_extra_user_profile_fields') );
			add_filter('the_posts', array(&$this, 'filter_posts') , 1 );
			
            add_filter('user_has_cap', array($this, 'user_has_cap'), 10, 4 );
            
            // Re-direct to the View Booking page
            add_action( 'admin_init', array( &$this, 'tours_load_view_booking_page' ) );
            	
            // add gcal settings
            add_action( 'show_user_profile', array( &$this, 'tours_gcal_settings' ), 11, 1 );
            add_action( 'personal_options_update', array(&$this, 'tours_save_gcal_fields'), 11, 1 );
            	
            // include files for GCal
            add_action( 'wp_loaded', array( &$this, 'tours_include_files' ) );
            add_action( 'admin_init', array( &$this, 'tours_include_files_admin' ) );
            	
            add_action('woocommerce_order_status_completed',array('tour_operators_print_tickets','send_tickets'), 10, 1);
            if ( get_option( 'woocommerce_version' ) >= "2.3" ) {
            	add_action( 'woocommerce_email_customer_details', array('tour_operators_print_tickets','tour_operators_details'), 11, 3 );
            }
            else {
            	add_action( 'woocommerce_email_after_order_table', array('tour_operators_print_tickets','tour_operators_details'), 11, 3 );
            }

            // Add the new settings tab for the addon
            add_action( 'bkap_add_addon_settings', array( &$this, 'bkap_tours_addon' ), 9 );
            // Wordpress settings API
            add_action('admin_init', array( &$this, 'bkap_tours_plugin_options' ) );
            // when the update is run, ensure the settings are copied to the new record in wp_options
            add_action( 'admin_init', array( &$this, 'bkap_tours_update_db_check' ) );
            
    		add_action('admin_init', array(&$this, 'edd_sample_register_option_tour'));
			add_action('admin_init', array(&$this, 'edd_sample_deactivate_license_tour'));
			add_action('admin_init', array(&$this, 'edd_sample_activate_license_tour'));
			
			add_action( 'admin_init', array( &$this, 'tours_add_user_cap' ) );
	   }
		
	   /**
	    * Add capabilities for the tour operator to allow for managing ,
	    * editing/deleting products assigned to him.
	    * @since 1.8
	    */
	   function tours_add_user_cap() {
	       $role = get_role( 'tour_operator' );
	       $role->add_cap( 'manage_woocommerce_products' );
	       $role->add_cap( 'edit_product' );
	       $role->add_cap( 'read_product' );
	       $role->add_cap( 'delete_product' );
	       $role->add_cap( 'edit_products' );
	       $role->add_cap( 'edit_others_products' );
	       $role->add_cap( 'publish_products' );
	       $role->add_cap( 'read_private_products' );
	       $role->add_cap( 'delete_products' );
	       $role->add_cap( 'delete_private_products' );
	       $role->add_cap( 'delete_published_products' );
	       $role->add_cap( 'edit_private_products' );
	       $role->add_cap( 'edit_published_products' );
	       $role->add_cap( 'edit_others_posts' );
	       $role->add_cap( 'edit_products' );	   
	   
	   }
	   function tour_operator_error_notice() {
	       if ( !is_plugin_active( 'woocommerce-booking/woocommerce-booking.php' ) ) {
	           echo "<div class=\"error\"><p>Tour Operators Addon is enabled but not effective. It requires WooCommerce Booking and Appointment plugin in order to work.</p></div>";
	       }
	   }
	   
	   function tours_include_files() {
	       include_once( 'print-tickets.php' );
	       include_once( 'tours-calendar-sync.php' );
	   }
	   
	   function tours_include_files_admin() {
	       include_once( 'tours-calendar-sync.php' );
	       include_once( 'tours-import-bookings.php' );
	       include_once( 'tours-view-bookings.php' );
	   }
	   
	   function bkap_tours_addon() {
	   
	       if ( isset( $_GET[ 'action' ] ) ) {
	           $action = $_GET[ 'action' ];
	       } else {
	           $action = '';
	       }
	       if ( 'addon_settings' == $action ) {
	           ?>
	          				<div id="content">
	          					<form method="post" action="options.php">
	          						<?php settings_errors(); ?>
	          					    <?php settings_fields( 'bkap_tours_settings' ); ?>
	          				        <?php do_settings_sections( 'woocommerce_booking_page' ); ?> 
	          						<?php submit_button(); ?>
	          			        </form>
	          			    </div>
	      				<?php 
	          		}
	   	   }
	   	   
	   	   function bkap_tours_plugin_options() {
	   	       
	   	       // First, we register a section. This is necessary since all future options must belong to a section
	   	       add_settings_section(
	   	       'bkap_tours_settings_section',         // ID used to identify this section and with which to register options
	   	       __( 'Tour Operator Addon Settings', 'tour_operators' ),                  // Title to be displayed on the administration page
	   	       array( $this, 'bkap_tours_callback' ), // Callback used to render the description of the section
	   	       'woocommerce_booking_page'     // Page on which to add this section of options
	   	       );
	   	       
	   	       add_settings_field(
	   	       'bkap_send_tickets_to_tour_operators',
	   	       __( 'Send Notification emails to Tour operators:', 'tour_operators' ),
	   	       array( $this, 'bkap_tours_enable_email_callback' ),
	   	       'woocommerce_booking_page',
	   	       'bkap_tours_settings_section',
	   	       array( __( 'Please select this checkbox if you want to send notification emails to tour operators when the order is completed.', 'tour_operators' ) )
	   	       );
	   	       
	   	       register_setting(
	   	       'bkap_tours_settings',
	   	       'bkap_send_tickets_to_tour_operators'
	   	           );
	   	       
	   	   }
	   	   
	   	   function bkap_tours_callback() {
	   	   }
	   	   
	   	   function bkap_tours_enable_email_callback( $args ) {
	   	       	
	   	       // First, we read the option
	   	       $enable_emails = get_option( 'bkap_send_tickets_to_tour_operators' );
	   	       // This condition added to avoid the notice displyed while Check box is unchecked.
	   	       if( isset( $enable_emails ) &&  '' == $enable_emails ) {
	   	           $enable_emails = 'off';
	   	       }
	   	       // Next, we update the name attribute to access this element's ID in the context of the display options array
	   	       // We also access the show_header element of the options collection in the call to the checked() helper function
	   	       $html = '<input type="checkbox" id="bkap_send_tickets_to_tour_operators" name="bkap_send_tickets_to_tour_operators" value="on" ' . checked( 'on', $enable_emails, false ) . '/>';
	   	       // Here, we'll take the first argument of the array and add it to a label next to the checkbox
	   	       $html .= '<label for="bkap_send_tickets_to_tour_operators"> '  . $args[0] . '</label>';
	   	       	
	   	       echo $html;
	   	   }
	   	   
	   	   function bkap_tours_update_db_check() {
	   	       global $wpdb;
	   	       
	   	       $option_query = "SELECT * FROM `" . $wpdb->prefix . "options`
	                               WHERE option_name = %s";
	   	       $results_option = $wpdb->get_results( $wpdb->prepare( $option_query, 'bkap_send_tickets_to_tour_operators' ) );
	   	       
	   	       if ( isset( $results_option ) && count( $results_option ) > 0 ) {
	   	       } else {
	   	           $saved_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
	   	           if ( isset( $saved_settings->booking_send_tickets_to_tour_operators ) && $saved_settings->booking_send_tickets_to_tour_operators == 'on' ) {
	   	               add_option( 'bkap_send_tickets_to_tour_operators', 'on' );
	   	           } else {
	   	               add_option( 'bkap_send_tickets_to_tour_operators', '' );
	   	           }
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
			if( is_admin() ): // checks if the url being accessed is in the admin section
				$new_posts = array();
				$user = new WP_User( get_current_user_id() );
				if( isset( $user->roles[ 0 ] ) && $user->roles[ 0 ] == 'tour_operator' ) {
				// loop through all the post objects
					foreach( $posts as $post ) {
						if( $post->post_type == 'page' ) {
							return $posts;
						} elseif( $post->post_type != 'shop_order' ) {
							$include = false;
							$booking_settings = get_post_meta( $post->ID, 'woocommerce_booking_settings', true );
							if( $post->post_author == get_current_user_id() || ( isset( $booking_settings[ "booking_tour_operator" ] ) && $booking_settings[ "booking_tour_operator" ] == get_current_user_id() ) ) {
								$include = '1';
							} else if ( isset( $post->post_parent ) && $post->post_parent != 0 ) { // check for variations of variable products
							    $booking_settings = get_post_meta( $post->post_parent, 'woocommerce_booking_settings', true );
							    if( ( isset( $booking_settings[ "booking_tour_operator" ] ) && $booking_settings[ "booking_tour_operator" ] == get_current_user_id() ) ) {
							        $include = '1';
							    }
							} else {
								$inlcude = '0';
							}
							
							if ( $include == '1' ) {
								$new_posts[] = $post;
							}
						} elseif( $post->post_type == 'shop_order' ) {
							global $wpdb;
							
							$check_query = "SELECT a.post_id FROM `".$wpdb->prefix."booking_history` as a join `".$wpdb->prefix."booking_order_history` as b on a.id=b.booking_id
										and b.order_id='".$post->ID."'";
										
							$results_check = $wpdb->get_results ( $check_query );
							
							$flag = false;
							
							if( !empty( $results_check ) ) {

							    foreach( $results_check as $res ) {
									$product_id = $res->post_id;
									$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
		
									if( !isset( $booking_settings[ "booking_tour_operator" ] ) || ( isset( $booking_settings[ "booking_tour_operator" ] ) && $booking_settings[ "booking_tour_operator" ] != get_current_user_id() ) ) {
										$flag = false;
									} elseif( isset( $booking_settings[ "booking_tour_operator" ] ) && $booking_settings[ "booking_tour_operator" ] == get_current_user_id() ) {
										$flag = true;
										break;
									} else {
										$flag = false;
									}
								}
							}
								
							if( $flag ) {
							   $new_posts[] = $post;
							} 
						}	
					}
				} else {
					return $posts;
				}
			
                // send the new post array back to be used by WordPress
				return $new_posts;
			else:
				return $posts;
			endif;
		}
		
		function user_has_cap($all_caps, $caps, $args, $user ){
			 global $post;
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
					
							if ( isset( $user->roles[0] ) && ( 'administrator' == $user->roles[0] || 'tour_operator' == $user->roles[0] ) ) {
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
			$product_id = $values[ 'product_id' ];
			if ( isset( $values[ 'bkap_booking' ] ) ) {
    			$booking = $values[ 'bkap_booking' ];
    			$order_item_id = $order->order_item_id;
    			$order_id = $order->order_id;
    			if( isset( $values[ 'bkap_booking' ][ 0 ][ 'comments' ] ) && !empty( $values[ 'bkap_booking' ][ 0 ][ 'comments' ] ) ) {
    				wc_add_order_item_meta( $order_item_id, bkap_get_book_t( 'book.item-comments' ), $values[ 'bkap_booking' ][ 0 ][ 'comments' ], true );
    			}	
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



		function operator_tour_submenu() {
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
				array( 'tours_view_bookings', 'operator_bookings_page' )
			);
			 add_submenu_page(
			     'booking_settings', // Third party plugin Slug
			     'Import Bookings',
			     'Import Bookings',
			     'operator_bookings',
			     'tours_import_bookings',
			     array( 'tours_import_bookings','tours_import_bookings_page' )
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

			if ( !current_user_can( 'edit_user', $user_id ) ) { 
			    return false; 
			}
			
			$userid =  new WP_User( $user_id );
			if( isset( $userid->roles[ 0 ] ) && $userid->roles[ 0 ] == 'tour_operator' ) {
				update_user_meta( $user_id, 'address', $_POST['address'] );
				update_user_meta( $user_id, 'paypal', $_POST['paypal'] );
				update_user_meta( $user_id, 'phone', $_POST['phone'] );
			}
		}
		
		function extra_user_profile_fields( $user ) { 
			$userid =  new WP_User( $user->ID );
			if( isset( $userid->roles[0] ) && $userid->roles[0] == 'tour_operator' ){?>
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
			<?php 
			} 
		}
		
		function tours_load_view_booking_page() {
		    $url = '';
		
		    if ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == 'operator_bookings' ) {
		        if ( isset( $_GET[ 'item_id' ] ) && $_GET[ 'item_id' ] != 0 ) {
		
		            ob_start();
		            $templatefilename = 'approve-booking.php';
		
		            $path_array = explode( '/', dirname( __FILE__ ) );
		            $plugin_name = array_pop( $path_array );
		
		            $path_array = implode( '/', $path_array );
		
		            if ( file_exists( $path_array . '/woocommerce-booking/' . $templatefilename ) ) {
		
		                $template = $path_array . '/woocommerce-booking/' . $templatefilename;
		                include( $template );
		            }
		             
		            $content = ob_get_contents();
		            ob_end_clean();
		
		            $args = array( 'slug'    => 'edit-booking',
		                'title'   => 'Edit Booking',
		                'content' => $content );
		            $pg = new bkap_approve_booking ( $args );
		        }
		    }
		}
		
		function tours_enqueue_scripts_css() {
		
		    $plugin_version_number = get_option( 'woocommerce_booking_db_version' );
		
		    $user_capab = get_user_meta(get_current_user_id(),'wp_capabilities');
		
		    // check current screen
		    $screen = get_current_screen();

		    // include the file on the profile page
		    if ('profile' == $screen->base && 'yes' == get_option( 'bkap_allow_tour_operator_gcal_api' ) && array_key_exists( 'tour_operator', $user_capab[0] ) ) {
		        wp_enqueue_style( 'tours-style', plugins_url('/css/profile.class.css', __FILE__ ) , '', $plugin_version_number , false );
		    }
		    
		    // include the file on the Import Bookings page
		    if ( isset( $_GET[ 'page' ] ) && 'tours_import_bookings' == $_GET[ 'page' ] ) {
		        wp_enqueue_style( 'tours-style', plugins_url('/css/profile.class.css', __FILE__ ) , '', $plugin_version_number , false );
		    }
		}
		
		function tours_gcal_settings( $user ) {
		
		    $userid =  new WP_User( $user->ID );
		
		    if ( 'yes' == get_option( 'bkap_allow_tour_operator_gcal_api' ) && isset( $userid->roles[ 0 ] ) && $userid->roles[ 0 ] == 'tour_operator' ) {
		        ?>
		    		    
		        		    <h3><?php _e( 'WooCommerce Booking and Appointment Google Calendar Sync Settings', 'woocommerce-booking' );?></h3>
		        		    
		        		    <h4><?php _e( 'Tour Operator Calendar Sync Settings', 'woocommerce-booking' ); ?></h4>
		        		    <table class="form-table">
		            			<tr>
		                            <th><label for="tours_calendar_sync_integration_mode"><?php _e('Integration Mode', 'woocommerce-booking' ); ?></label></th>
		            			<td>
		            			<?php
		
		                			$sync_directly = "";
		                			$sync_manually = "";
		                			$sync_disable = "checked";
		                			
		                			$sync_setting = esc_attr( get_the_author_meta( 'tours_calendar_sync_integration_mode', $user->ID ) );
		                			
		                			if ( $sync_setting == 'manually' ) {
		                			    $sync_manually = "checked";
		                			    $sync_disable = "";
		                			} else if( $sync_setting == 'directly' ) {
		                			    $sync_directly = "checked";
		                			    $sync_disable = "";
		                			}
		            			
		            			?>
		                            <input type="radio" name="tours_calendar_sync_integration_mode" id="tours_calendar_sync_integration_mode" value="directly" <?php echo $sync_directly; ?> /> <?php  _e( 'Sync Automatically', 'woocommerce-booking' ) ?> &nbsp;&nbsp;
		                            <input type="radio" name="tours_calendar_sync_integration_mode" id="tours_calendar_sync_integration_mode" value="manually" <?php echo $sync_manually; ?> /> <?php  _e( 'Sync Manually', 'woocommerce-booking' ) ?> &nbsp;&nbsp;
		                            <input type="radio" name="tours_calendar_sync_integration_mode" id="tours_calendar_sync_integration_mode" value="disabled" <?php echo $sync_disable; ?> /> <?php _e( 'Disabled', 'woocommerce-booking' ) ?>
		    
		                            <span class="description"><?php _e('<br>Select method of integration. "Sync Automatically" will add the booking events to the Google calendar, which is set in the "Calendar to be used" field, automatically when a customer places an order. <br>"Sync Manually" will add an "Add to Calendar" button in emails received by admin on New customer order and on the View Booking Calendar page.<br>"Disabled" will disable the integration with Google Calendar.<br>Note: Import of the events will work manually using .ics link.', 'woocommerce-booking' ); ?></span>
		            			
		                			<script type="text/javascript">
		                                jQuery( document ).ready( function() {
		                                    var isChecked = jQuery( "#tours_calendar_sync_integration_mode:checked" ).val();
		                                    if( isChecked == "directly" ) {
		                                       i = 0;
		                                       jQuery( ".form-table" ).each( function() {
		                                            if( i == 6 ) {
		                                                k = 0;
		                                                var row = jQuery( this ).find( "tr" );
		                                                jQuery.each( row , function() {
		                                                    if( k == 7 ) {
		                                                        jQuery( this ).fadeOut();
		                                                    } else {
		                                                        jQuery( this ).fadeIn();
		                                                    }
		                                                    k++;
		                                                });
		                                            } else {
		                                                jQuery( this ).fadeIn();
		                                            }
		                                            i++;
		                                        } );
		                                    } else if( isChecked == "manually" ) {
		                                        i = 0;
		                                        jQuery( ".form-table" ).each( function() {
		                                            if( i == 6 ) {
		                                                k = 0;
		                                                var row = jQuery( this ).find( "tr" );
		                                                jQuery.each( row , function() {
		                                                	if( k != 7 && k != 0 ) {
		                                                        jQuery( this ).fadeOut();
		                                                    } else {
		                                                        jQuery( this ).fadeIn();
		                                                    }
		                                                    k++;
		                                                });
		                                            } else {
		                                                jQuery( this ).fadeIn();
		                                            }
		                                            i++;
		                                        });
		                                    } else if( isChecked == "disabled" ) {
		                                        i = 0;
		                                        jQuery( ".form-table" ).each( function() {
		                                            if( i == 6 ) {
		                                                k = 0;
		                                                var row = jQuery( this ).find( "tr" );
		                                                jQuery.each( row , function() {
		                                                	if( k != 0 ) {
		                                                        jQuery( this ).fadeOut();
		                                                    } else {
		                                                        jQuery( this ).fadeIn();
		                                                    }
		                                                    k++;
		                                                });
		                                            } else {
		                                                jQuery( this ).fadeIn();
		                                            }
		                                            i++;
		                                        });
		                                    }
		                                    jQuery( "input[type=radio][id=tours_calendar_sync_integration_mode]" ).change( function() {
		                                        var isChecked = jQuery( this ).val();
		                                        if( isChecked == "directly" ) {
		                                            i = 0;
		                                            jQuery( ".form-table" ).each( function() {
		                                                if( i == 6 ) {
		                                                    k = 0;
		                                                    var row = jQuery( this ).find( "tr" );
		                                                    jQuery.each( row , function() {
		                                                        if( k == 7 ) {
		                                                            jQuery( this ).fadeOut();
		                                                        } else {
		                                                            jQuery( this ).fadeIn();
		                                                        }
		                                                        k++;
		                                                    });
		                                                } else {
		                                                    jQuery( this ).fadeIn();
		                                                }
		                                                i++;
		                                            } );
		                                        } else if( isChecked == "manually" ) {
		                                            i = 0;
		                                            jQuery( ".form-table" ).each( function() {
		                                                if( i == 6 ) {
		                                                    k = 0;
		                                                    var row = jQuery( this ).find( "tr" );
		                                                    jQuery.each( row , function() {
		                                                        if( k != 7 && k != 0 ) {
		                                                            jQuery( this ).fadeOut();
		                                                        } else {
		                                                            jQuery( this ).fadeIn();
		                                                        }
		                                                        k++;
		                                                    });
		                                                } else {
		                                                    jQuery( this ).fadeIn();
		                                                }
		                                                i++;
		                                            });
		                                        } else if( isChecked == "disabled" ) {
		                                            i = 0;
		                                            jQuery( ".form-table" ).each( function() {
		                                                if( i == 6 ) {
		                                                    k = 0;
		                                                    var row = jQuery( this ).find( "tr" );
		                                                    jQuery.each( row , function() {
		                                                        if( k != 0 ) {
		                                                            jQuery( this ).fadeOut();
		                                                        } else {
		                                                            jQuery( this ).fadeIn();
		                                                        }
		                                                        k++;
		                                                    });
		                                                } else {
		                                                    jQuery( this ).fadeIn();
		                                                }
		                                                i++;
		                                            });
		                                        }
		                                    })
		                                });
		                            </script>
		                        </td>
		    
		            			</tr>
		            			
		            			<tr>
		                            <th><label for="tours_calendar_instructions"><?php _e('Instructions', 'woocommerce-booking' ); ?></label></th>
		            			<td>
		            			<?php 
		            			$path_array = explode( '/', dirname( __FILE__ ) );
		            			$plugin_name = array_pop( $path_array );
		            			
		            			$path_array = implode( '/', $path_array );
		            			_e( 'To set up Google Calendar API, please click on "Show me how" link and carefully follow these steps:
		            			
		                        <span class="description" ><a href="#tours-instructions" id="show_instructions" data-target="api-instructions" class="tours-info_trigger" title="' . __ ( 'Click to toggle instructions', 'woocommerce-booking') . '">' . __( 'Show me how', 'woocommerce-booking' ) . '</a></span>', 'woocommerce-booking' );
		                        ?> <div class="description tours-info_target api-instructions" style="display: none;">
		                                <ul style="list-style-type:decimal;">
		                                    <li><?php _e( 'Google Calendar API requires php V5.3+ and some php extensions.', 'woocommerce-booking' ) ?> </li>
		                                    <li><?php printf( __( 'Go to Google APIs console by clicking %s. Login to your Google account if you are not already logged in.', 'woocommerce-booking' ), '<a href="https://code.google.com/apis/console/" target="_blank">https://code.google.com/apis/console/</a>' ) ?></li>
		                                    <li><?php _e( 'Create a new project using the left side pane. Click on \'Home\' option. Name the project "Bookings" (or use your chosen name instead).', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Click on API Manager from left side pane.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Click "Calendar API" under Google Apps APIs and Click on Enable button.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Go to "Credentials" menu in the left side pane and click on "New Credentials" dropdown.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Click on "OAuth client ID" option. Then click on Configure consent screen.', 'woocommerce-booking' )?></li>
		                                    <li><?php _e( 'Enter a Product Name, e.g. Bookings and Appointments, inside the opening pop-up. Click Save.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Select "Web Application" option, enter the Web client name and create the client ID.', 'woocommerce-booking' )?></li>
		                                    <li><?php _e( 'Click on New Credentials dropdown and select "Service account key".', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Click "Service account" and select "New service account" and enter the name. Now select key type as "P12" and create the service account.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'A file with extension .p12 will be downloaded.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php printf( __( 'Using your FTP client program, copy this key file to folder: %s . This file is required as you will grant access to your Google Calendar account even if you are not online. So this file serves as a proof of your consent to access to your Google calendar account. Note: This file cannot be uploaded in any other way. If you do not have FTP access, ask the website admin to do it for you.', 'woocommerce-booking' ), $path_array .'/woocommerce-booking/includes/gcal/key/' ) ?></li>
		                                    <li><?php _e( 'Enter the name of the key file to "Key file name" setting of Booking. Exclude the extention .p12.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Copy "Email address" setting from Manage service account of Google apis console and paste it to "Service account email address" setting of Booking.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php printf( __( 'Open your Google Calendar by clicking this link: %s', 'woocommerce-booking' ), '<a href="https://www.google.com/calendar/render" target="_blank">https://www.google.com/calendar/render</a>' ) ?></li>
		                                    <li><?php _e( 'Create a new Calendar by selecting "my Calendars > Create new calendar" on left side pane. <b>Try NOT to use your primary calendar.</b>', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Give a name to the new calendar, e.g. Bookings calendar. <b>Check that Calendar Time Zone setting matches with time zone setting of your WordPress website.</b> Otherwise there will be a time shift.', 'woocommerce-booking' ) ?></li>		
		                                    <li><?php _e( 'Paste already copied "Email address" setting from Manage service account of Google apis console to "Person" field under "Share with specific person".', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Set "Permission Settings" of this person as "Make changes to events".', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Click "Add Person".', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Click "Create Calendar".', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Select the created calendar and click "Calendar settings".', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Copy "Calendar ID" value on Calendar Address row.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Paste this value to "Calendar to be used" field of Booking settings.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Select the desired Integration mode: Sync Automatically or Sync Manually.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Click "Save Settings" on Booking settings.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'After these stages, you have set up Google Calendar API. To test the connection, click the "Test Connection" link.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'If you get a success message, you should see a test event inserted to the Google Calendar and you are ready to go. If you get an error message, double check your settings.', 'woocommerce-booking' ) ?></li>
		                                </ul>
		                            </div>
		        
		                        <script type="text/javascript">
		                            function toggle_target (e) {
		                            	if ( e && e.preventDefault ) { 
		                                    e.preventDefault();
		                                }
		                            	if ( e && e.stopPropagation ) {
		                                    e.stopPropagation();
		                                }
		                            	var target = jQuery(".tours-info_target.api-instructions" );
		                            	if ( !target.length ) {
		                                    return false;
		                                }
		                                
		                            	if ( target.is( ":visible" ) ) {
		                                    target.hide( "fast" );
		                                } else {
		                                    target.show( "fast" );
		                                }
		                            
		                            	return false;
		                            }
		                            jQuery(function () {
		                            	jQuery(document).on("click", ".tours-info_trigger", toggle_target);
		                            });
		                        </script>
		            			</td>
		            			</tr>
		            			
		            			<tr>
		                            <th><label for="tours_calendar_key_file_name"><?php _e('Key file name', 'woocommerce-booking' ); ?></label></th>
		            			<td>
		            			<?php
		
		                			$gcal_key_file_arr = get_the_author_meta( 'tours_calendar_details_1', $user->ID );
		                			
		                			if( isset( $gcal_key_file_arr[ 'tours_calendar_key_file_name' ] ) ) {
		                			    $gcal_key_file = $gcal_key_file_arr[ 'tours_calendar_key_file_name' ];
		                			} else {
		                			    $gcal_key_file = '';
		                			}
		                			
		            			?>
		                            <input id="tours_calendar_details_1[tours_calendar_key_file_name]" name= "tours_calendar_details_1[tours_calendar_key_file_name]" value="<?php echo $gcal_key_file; ?>" size="90" name="gcal_key_file" type="text" />
		                            
		                            <span class="description"><?php _e('<br>Enter key file name here without extention, e.g. ab12345678901234567890-privatekey.', 'woocommerce-booking' ); ?></span>
		            			</td>
		            			</tr>
		            			
		            			<tr>
		                            <th><label for="tours_calendar_service_acc_email_address"><?php _e('Service account email address', 'woocommerce-booking' ); ?></label></th>
		            			<td>
		            			<?php
		
		                		    $gcal_service_account_arr = get_the_author_meta( 'tours_calendar_details_1', $user->ID );
		                            if( isset( $gcal_service_account_arr[ 'tours_calendar_service_acc_email_address' ] ) ) {
		                                $gcal_service_account = $gcal_service_account_arr[ 'tours_calendar_service_acc_email_address' ];
		                            } else {
		                                $gcal_service_account = '';
		                            }
		                			
		            			?>
		                            <input id="tours_calendar_details_1[tours_calendar_service_acc_email_address]" name="tours_calendar_details_1[tours_calendar_service_acc_email_address]" value="<?php echo $gcal_service_account; ?>" size="90" name="gcal_service_account" type="text"/>
		                            
		                            <span class="description"><?php _e('<br>Enter Service account email address here, e.g. 1234567890@developer.gserviceaccount.com.', 'woocommerce-booking' ); ?></span>
		            			</td>
		            			</tr>
		            			
		            			<tr>
		                            <th><label for="tours_calendar_id"><?php _e('Calendar to be used', 'woocommerce-booking' ); ?></label></th>
		            			<td>
		            			<?php
		
		            			$gcal_selected_calendar_arr = get_the_author_meta( 'tours_calendar_details_1', $user->ID );
		            			if( isset( $gcal_selected_calendar_arr[ 'tours_calendar_id' ] ) ) {
		            			    $gcal_selected_calendar = $gcal_selected_calendar_arr[ 'tours_calendar_id' ];
		            			} else {
		            			    $gcal_selected_calendar = '';
		            			}
		            
		            			?>
		                            <input id="tours_calendar_details_1[tours_calendar_id]" name="tours_calendar_details_1[tours_calendar_id]" value="<?php echo $gcal_selected_calendar; ?>" size="90" name="gcal_selected_calendar" type="text" />
		                            
		                            <span class="description"><?php _e('<br>Enter the ID of the calendar in which your bookings will be saved, e.g. abcdefg1234567890@group.calendar.google.com.', 'woocommerce-booking' ); ?></span>
		            			</td>
		            			</tr>
		            			
		            			<tr>
		            			<th></th>
		            			<td>
		                            <script type='text/javascript'>
		                                jQuery( document ).on( 'click', '#test_connection', function( e ) {
		                                    e.preventDefault();
		                                    var data = {
		                             		   gcal_api_test_result: '',
		                              		  gcal_api_pre_test: '',
		                                	    gcal_api_test: 1,
		                                	    user_id: <?php echo $user->ID; ?>,
                                	    		product_id: 0,
		                                	    action: 'display_nag'
		                        	        };
		                                    jQuery( '#test_connection_ajax_loader' ).show();
		                                    jQuery.post( '<?php echo get_admin_url(); ?>/admin-ajax.php', data, function( response ) {
		                                        jQuery( '#test_connection_message' ).html( response );
		                                        jQuery( '#test_connection_ajax_loader' ).hide();
		                                        });
		                            
		                                });
		                        </script>
		                			
		                			<a href='profile.php' id='test_connection'> <?php _e( 'Test Connection', 'woocommerce-booking' ); ?></a>
		                            <img src='<?php echo plugins_url(); ?>/woocommerce-booking/images/ajax-loader.gif' id='test_connection_ajax_loader'>
		                            <div id='test_connection_message'></div>
		            			</td>
		            			</tr>
		            			
		            			<tr>
		                            <th><label for="tours_add_to_calendar_view_booking"><?php _e('Show Add to Calendar button on View Bookings page', 'woocommerce-booking' ); ?></label></th>
		            			<td>
		            			<?php
		            			
		                		    $tours_add_to_calendar_view_bookings = "";
		                            if( 'on' == esc_attr( get_the_author_meta( 'tours_add_to_calendar_view_booking', $user->ID ) ) ) {
		                                $tours_add_to_calendar_view_bookings = "checked";
		                            }
		            
		            			?>
		                            <input type="checkbox" name="tours_add_to_calendar_view_booking" id="tours_add_to_calendar_view_booking" value="on" <?php echo $tours_add_to_calendar_view_bookings; ?> />
		                            
		                            <span class="description"><?php _e('Show "Add to Calendar" button on the Booking -> View Bookings page.<br><i>Note: This button can be used to export the already placed orders with future bookings from the current date to the calendar used above.</i>', 'woocommerce-booking' ); ?></span>
		            			</td>
		            			</tr>
		            			
		            			<tr>
		                            <th><label for="tours_add_to_calendar_email_notification"><?php _e('Show Add to Calendar button in New Order email notification', 'woocommerce-booking' ); ?></label></th>
		            			<td>
		            			<?php
		            			
		                		    $tours_add_to_calendar_email_notification = "";
		                            if( "on" == esc_attr( get_the_author_meta( 'tours_add_to_calendar_email_notification', $user->ID ) ) ) {
		                                $tours_add_to_calendar_email_notification = "checked";
		                            }
		            
		            			?>
		                            <input type="checkbox" name="tours_add_to_calendar_email_notification" id="tours_add_to_calendar_email_notification" value="on" <?php echo $tours_add_to_calendar_email_notification; ?> />
		                            
		                            <span class="description"><?php _e('Show "Add to Calendar" button in the New Order email notification.', 'woocommerce-booking' ); ?></span>
		            			</td>
		            			</tr>
		            			
		        			</table>
		        			
		        			<h3><?php _e( 'Import Events', 'woocommerce-booking' ); ?></h3>
		        			<br>
		        			<?php _e( 'Events will be imported using the ICS Feed url. Each event will create a new WooCommerce Order. The event\'s date & time will be set as the item\'s Booking Date & Time. <br>Lockout will be updated for the product for the set Booking Date & Time.', 'woocommerce-booking' ); ?>
		        		    <table class="form-table">
		            			<tr>
		                            <th><label for="tours_ics_feed_url_instructions"><?php _e('Instructions', 'woocommerce-booking' ); ?></label></th>
		            			<td>
		                            <?php _e( 'To set up Import events using ics feed urls, please click on "Show me how" link and carefully follow these steps:', 'woocommerce-booking' ); ?>
		                            <span class="ics-feed-description" >
		                            <a href="#tours-ics-feed-instructions" id="show_instructions" data-target="api-instructions" class="tours_ics_feed-info_trigger" title="<?php _e( 'Click to toggle instructions', 'woocommerce-booking' ); ?>"> <?php _e( 'Show me how', 'woocommerce-booking' ); ?> </a></span>
		            
		                            <div class="ics-feed-description tours_ics_feed-info_target api-instructions" style="display: none;">
		                                <ul style="list-style-type:decimal;">
		                                    <li><?php printf( __( 'Open your Google Calendar by clicking this link: %s', 'woocommerce-booking' ), '<a href="https://www.google.com/calendar/render" target="_blank">https://www.google.com/calendar/render</a>' ) ?></li>
		                                    <li><?php _e( 'Select the calendar to be imported and click "Calendar settings".', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Click on "ICAL" button in Calendar Address option.', 'woocommerce-booking' ) ?></li>		
		                                    <li><?php _e( 'Copy the basic.ics file URL.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Paste this link in the text box under Google Calendar Sync tab -> Import Events section.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Save the URL.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'Click on "Import Events" button to import the events from the calendar.', 'woocommerce-booking' ) ?></li>
		                                    <li><?php _e( 'You can import multiple calendars by using ics feeds. Add them using the Add New Ics Feed url button.', 'woocommerce-booking' ) ?></li>
		                                </ul>
		                            </div>
		                            
		                            <script type="text/javascript">
		                            function tours_ics_feed_toggle_target (e) {
		                            	if ( e && e.preventDefault ) { 
		                                    e.preventDefault();
		                                }
		                            	if ( e && e.stopPropagation ) {
		                                    e.stopPropagation();
		                                }
		                            	var target = jQuery( ".tours_ics_feed-info_target.api-instructions" );
		                            	if ( !target.length ) {
		                                    return false;
		                                }
		                                
		                            	if ( target.is( ":visible" ) ) {
		                                    target.hide( "fast" );
		                                } else {
		                                    target.show( "fast" );
		                                }
		                            
		                            	return false;
		                            }
		                            jQuery( function () { 
		                            	jQuery(document).on( "click", ".tours_ics_feed-info_trigger", tours_ics_feed_toggle_target );
		                            });
		                            </script>
		            			</td>
		            			</tr>
		            			
		            			<tr>
		                            <th><label for="tours_ics_feed_url"><?php _e('iCalendar/.ics Feed URL', 'woocommerce-booking' ); ?></label></th>
		            			<td>
		            			
		                            <table id="tours_ics_url_list">
		                            <?php 
		                                $ics_feed_urls = get_the_author_meta( 'tours_ics_feed_urls', $user->ID );
		                                if( $ics_feed_urls == '' || $ics_feed_urls == '{}' || $ics_feed_urls == '[]' || $ics_feed_urls == 'null' ) {
		                                    $ics_feed_urls = array();
		                                }
		                
		                                if( count( $ics_feed_urls ) > 0 ) {
		                                    foreach ( $ics_feed_urls as $key => $value ) {
		                                        echo "<tr id='$key'>
		                                            <td class='ics_feed_url'>
		                                                <input type='text' id='tours_ics_fee_url_$key' size='60' value='" . $value. "'>
		                                            </td>
		                                            <td class='ics_feed_url'>
		                                                <input type='button' value='Save' id='save_ics_url' class='save_button' name='$key' disabled='disabled'>
		                                            </td>
		                                            <td class='ics_feed_url'>
		                                                <input type='button' class='save_button' id='$key' name='import_ics' value='Import Events'>
		                                            </td>
		                                            <td class='ics_feed_url'>
		                                                <input type='button' class='save_button' id='$key' value='Delete' name='delete_ics_feed'>
		                                            </td>
		                                            <td class='ics_feed_url'>
		                                                <div id='import_event_message' style='display:none;'>
		                                                    <img src='" . plugins_url() . "/woocommerce-booking/images/ajax-loader.gif'>
		                                                </div>
		                                                <div id='success_message' ></div>
		                                            </td>
		                                        </tr>";
		                                    }
		                                } else {
		                                    echo "<tr id='0' >
		                                        <td class='ics_feed_url'>
		                                            <input type='text' id='tours_ics_fee_url_0' size='60' >
		                                        </td>
		                                        <td class='ics_feed_url'>
		                                            <input type='button' value='Save' id='save_ics_url' class='save_button' name='0' >
		                                        </td>
		                                        <td class='ics_feed_url'>
		                                            <input type='button' class='save_button' id='0' name='import_ics' value='Import Events' disabled='disabled'>
		                                        </td>
		                                        <td class='ics_feed_url'>
		                                            <input type='button' class='save_button' id='0' name='delete_ics_feed' value='Delete' disabled='disabled'>
		                                        </td>
		                                        <td class='ics_feed_url'>
		                                            <div id='import_event_message' style='display:none;'>
		                                                <img src='" . plugins_url() . "/woocommerce-booking/images/ajax-loader.gif'>
		                                            </div>
		                                            <div id='success_message' ></div>
		                                        </td>
		                                    </tr>";
		                                }
		                                echo'</table>';
		                
		                                echo "<input type='button' class='save_button' id='add_new_ics_feed' name='add_new_ics_feed' value='Add New Ics feed url'>";
		                                echo "<script type='text/javascript'>
		                                    jQuery( document ).ready( function() {
		                                        
		                                        jQuery( '#add_new_ics_feed' ).on( 'click', function() {
		                                            var rowCount = jQuery( '#tours_ics_url_list tr' ).length;
		                                            jQuery( '#tours_ics_url_list' ).append( '<tr id=\'' + rowCount + '\'><td class=\'ics_feed_url\'><input type=\'text\' id=\'tours_ics_fee_url_' + rowCount + '\' size=\'60\' ></td><td class=\'ics_feed_url\'><input type=\'button\' value=\'Save\' id=\'save_ics_url\' class=\'save_button\' name=\'' + rowCount + '\'></td><td class=\'ics_feed_url\'><input type=\'button\' class=\'save_button\' id=\'' + rowCount + '\' name=\'import_ics\' value=\'Import Events\' disabled=\'disabled\'></td><td class=\'ics_feed_url\'><input type=\'button\' class=\'save_button\' id=\'' + rowCount + '\' value=\'Delete\' disabled=\'disabled\'  name=\'delete_ics_feed\' ></td><td class=\'ics_feed_url\'><div id=\'import_event_message\' style=\'display:none;\'><img src=\'" . plugins_url() . "/woocommerce-booking/images/ajax-loader.gif\'></div><div id=\'success_message\' ></div></td></tr>' );
		                                        });
		                                    
		                                        jQuery( document ).on( 'click', '#save_ics_url', function() {
		                                            var key = jQuery( this ).attr( 'name' );
		                                            var data = {
		                                                user_id: " . $user->ID . ",
		                                                ics_url: jQuery( '#tours_ics_fee_url_' + key ).val(),
		                                                action: 'tours_save_ics_url_feed'
		                                            };
		                                            jQuery.post( '" . get_admin_url() . "/admin-ajax.php', data, function( response ) {
		                                                if( response == 'yes' ) {
		                                                    jQuery( 'input[name=\'' + key + '\']' ).attr( 'disabled','disabled' );
		                                                    jQuery( 'input[id=\'' + key + '\']' ).removeAttr( 'disabled' );
		                                                } 
		                                            });
		                                        });
		                                        
		                                        jQuery( document ).on( 'click', 'input[type=\'button\'][name=\'delete_ics_feed\']', function() {
		                                            var key = jQuery( this ).attr( 'id' );
		                                            var data = {
		                                                user_id: " . $user->ID . ",
		                                                ics_feed_key: key,
		                                                action: 'tours_delete_ics_url_feed'
		                                            };
		                                            jQuery.post( '" . get_admin_url() . "/admin-ajax.php', data, function( response ) {
		                                                if( response == 'yes' ) {
		                                                    jQuery( 'table#tours_ics_url_list tr#' + key ).remove();
		                                                } 
		                                            });
		                                        });
		                                        
		                                        jQuery( document ).on( 'click', 'input[type=\'button\'][name=\'import_ics\']', function() {
		                                            jQuery( '#import_event_message' ).show();
		                                            var key = jQuery( this ).attr( 'id' );
		                                            var data = {
		                                                user_id: " . $user->ID . ",
		                                                ics_feed_key: key,
		                                                action: 'tours_import_events'
		                                            };
		                                            jQuery.post( '" . get_admin_url() . "/admin-ajax.php', data, function( response ) {
		                                                jQuery( '#import_event_message' ).hide();
		                                                jQuery( '#success_message' ).html( response );  
		                                                jQuery( '#success_message' ).fadeIn();  
		                                                setTimeout( function() {
		                                                    jQuery( '#success_message' ).fadeOut();
		                                                },3000 );
		                                            });
		                                        });
		                                    });
		                                </script>";
		                                ?>
		            			</td>
		            			</tr>
		        			</table> 
		                <?php 
		    		    }
		    		}
		    		
		    		function tours_save_gcal_fields( $user_id ) {
		    		
		    		    if ( !current_user_can( 'edit_user', $user_id ) ) {
		    		        return false;
		    		    }
		    		    	
		    		    $userid =  new WP_User( $user_id );
		    		    	
		    		
		    		    if( isset( $userid->roles[ 0 ] ) && 'tour_operator' == $userid->roles[0] ){
		    		
		    		        if ( isset( $_POST[ 'tours_calendar_sync_integration_mode' ] ) ) {
		    		            update_user_meta( $user_id, 'tours_calendar_sync_integration_mode', $_POST[ 'tours_calendar_sync_integration_mode' ] );
		    		        }
		    		
		    		        $calendar_details = array();
		    		        $calendar_details[ 'tours_calendar_key_file_name' ] = '';
		    		        $calendar_details[ 'tours_calendar_service_acc_email_address' ] = '';
		    		        $calendar_details[ 'tours_calendar_id' ] = '';
		    		
		    		        if ( isset( $_POST[ 'tours_calendar_details_1' ][ 'tours_calendar_key_file_name' ] ) ) {
		    		            $calendar_details[ 'tours_calendar_key_file_name' ] = $_POST[ 'tours_calendar_details_1' ][ 'tours_calendar_key_file_name' ];
		    		        }
		    		
		    		        if ( isset( $_POST[ 'tours_calendar_details_1' ][ 'tours_calendar_service_acc_email_address' ] ) ) {
		    		            $calendar_details[ 'tours_calendar_service_acc_email_address' ] = $_POST[ 'tours_calendar_details_1' ][ 'tours_calendar_service_acc_email_address' ];
		    		        }
		    		
		    		        if ( isset( $_POST[ 'tours_calendar_details_1' ][ 'tours_calendar_id' ] ) ) {
		    		            $calendar_details[ 'tours_calendar_id' ] = $_POST[ 'tours_calendar_details_1' ][ 'tours_calendar_id' ];
		    		        }
		    		
		    		        update_user_meta( $user_id, 'tours_calendar_details_1', $calendar_details );
		    		
		    		        if ( isset( $_POST[ 'tours_add_to_calendar_view_booking' ] ) ) {
		    		            update_user_meta( $user_id, 'tours_add_to_calendar_view_booking', $_POST[ 'tours_add_to_calendar_view_booking' ] );
		    		        } else {
		    		            update_user_meta( $user_id, 'tours_add_to_calendar_view_booking', '' );
		    		        }
		    		
		    		        if ( isset( $_POST[ 'tours_add_to_calendar_email_notification' ] ) ) {
		    		            update_user_meta( $user_id, 'tours_add_to_calendar_email_notification', $_POST[ 'tours_add_to_calendar_email_notification' ] );
		    		        } else {
		    		            update_user_meta( $user_id, 'tours_add_to_calendar_email_notification', '' );
		    		        }
		    		    }
		    		
		    		}
		}
	}
	$tour_operators = new tour_operators();
}
?>
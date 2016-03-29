<?php 
// Schedule an action if it's not already scheduled
if ( ! wp_next_scheduled( 'tours_import_events' ) ) {
    wp_schedule_event( time(), '24_hrs', 'tours_import_events' );
}

// Hook into that action that'll fire once every day
add_action( 'tours_import_events', 'tours_import_events_cron' );
function tours_import_events_cron() {
    $tours_calendar_sync = new tours_calendar_sync();

    // get the list of tour operators
    $args = array( 'role' => 'tour_operator', 'fields' => array( 'ID' ) );
    $users = get_users( $args );

    // for each tour operator
    foreach ( $users as $user_key => $user_value ) {
        $_POST[ 'user_id' ] = $user_value->ID;
        $tours_calendar_sync->tours_setup_import();
    }

}

class tours_calendar_sync {
    
    public function __construct() {
                
        $this->plugin_dir = plugin_dir_path( __FILE__ );
        $this->plugin_url = plugins_url( basename( dirname( __FILE__ ) ) );
        
        add_action( 'woocommerce_checkout_update_order_meta', array( &$this, 'tours_google_calendar_update_order_meta' ), 12 );
        
        add_action( 'woocommerce_order_item_meta_end', array( &$this, 'tours_add_to_calendar_admin'), 15, 4 );
        
        add_action( 'wp_ajax_tours_save_ics_url_feed', array( &$this, 'tours_save_ics_url_feed' ) );
        
        add_action( 'wp_ajax_tours_delete_ics_url_feed', array( &$this, 'tours_delete_ics_url_feed' ) );
        
        add_action( 'wp_ajax_tours_import_events', array( &$this, 'tours_setup_import' ) );
        
//        add_action( 'wp_ajax_bkap_admin_booking_calendar_events', array( &$this, 'bkap_admin_booking_calendar_events' ) );

        $path_array = explode( '/', dirname( __FILE__ ) );
        $plugin_name = array_pop( $path_array );
        
        $path_array = implode( '/', $path_array );
        
        require_once $path_array . '/woocommerce-booking/includes/iCal/SG_iCal.php';
       
    }
    
    
    function tours_google_calendar_update_order_meta( $order_id ) {
        global $wpdb;
        
        $gcal = new BKAP_Gcal();
        
        $user = get_user_by( 'email', get_option( 'admin_email' ) );
        $admin_id = $user->ID;
        
        $order_item_ids   =   array();
        $sub_query        =   "";
        
        foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
        
            // if tour operator addon is active, pass the tour operator user Id else the admin ID
            if ( function_exists( 'is_bkap_tours_active' ) && is_bkap_tours_active() ) {
                $post_id = bkap_common::bkap_get_product_id( $values[ 'product_id' ] );
                $booking_settings = get_post_meta( $post_id, 'woocommerce_booking_settings', true );
    
                if( isset( $booking_settings[ 'booking_tour_operator' ] ) &&  $booking_settings[ 'booking_tour_operator' ] != 0 ) {
                    $user_id = $booking_settings[ 'booking_tour_operator' ];
                }
            }
        
            if ( isset( $values[ 'bkap_booking' ] ) && isset( $user_id ) && $user_id != $admin_id ) {
        
                if( $gcal->get_api_mode( $user_id ) == "directly" ) {
                    $_data    =   $values[ 'data' ];
                    $_booking    =   $values[ 'bkap_booking' ][0];
        
                    $post_title  =   $_data->post->post_title;
                    // Fetch line item
                    if ( count( $order_item_ids ) > 0 ) {
                        $order_item_ids_to_exclude  = implode( ",", $order_item_ids );
                        $sub_query                  = " AND order_item_id NOT IN (".$order_item_ids_to_exclude.")";
                    }
            
                    $query               =   "SELECT order_item_id,order_id FROM `".$wpdb->prefix."woocommerce_order_items`
    					              WHERE order_id = %s AND order_item_name = %s".$sub_query;
        
                    $results             =   $wpdb->get_results( $wpdb->prepare( $query, $order_id, $post_title ) );
        
                    $order_item_ids[]    =   $results[0]->order_item_id;
        
                    // check the booking status, if pending confirmation, then do not insert event in the calendar
                    $booking_status = wc_get_order_item_meta( $results[0]->order_item_id, '_wapbk_booking_status' );
        
                    if ( ( isset( $booking_status ) && 'pending-confirmation' != $booking_status ) || ( ! isset( $booking_status )) ) {
        
                        $event_details = array();
        
                        $event_details[ 'hidden_booking_date' ] = $_booking[ 'hidden_date' ];
        
                        if ( isset( $_booking[ 'hidden_date_checkout' ] ) && $_booking[ 'hidden_date_checkout' ] != '' ) {
                            $event_details[ 'hidden_checkout_date' ] = $_booking[ 'hidden_date_checkout' ];
                        }
        
                        if ( isset( $_booking[ 'time_slot' ] ) && $_booking[ 'time_slot' ] != '' ) {
                            $event_details[ 'time_slot' ] = $_booking[ 'time_slot' ];
                        }
        
                        $event_details[ 'billing_email' ] = $_POST[ 'billing_email' ];
                        $event_details[ 'billing_first_name' ] = $_POST[ 'billing_first_name' ];
                        $event_details[ 'billing_last_name' ] = $_POST[ 'billing_last_name' ];
                        $event_details[ 'billing_address_1' ] = $_POST[ 'billing_address_1' ];
                        $event_details[ 'billing_address_2' ] = $_POST[ 'billing_address_2' ];
                        $event_details[ 'billing_city' ] = $_POST[ 'billing_city' ];
        
                        $event_details[ 'billing_phone' ] = $_POST[ 'billing_phone' ];
                        $event_details[ 'order_comments' ] = $_POST[ 'order_comments' ];
                        $event_details[ 'order_id' ] = $order_id;
        
        
                        if ( isset( $_POST[ 'shipping_first_name' ] ) && $_POST[ 'shipping_first_name' ] != '' ) {
                            $event_details[ 'shipping_first_name' ] = $_POST[ 'shipping_first_name' ];
                        }
                        if ( isset( $_POST[ 'shipping_last_name' ] ) && $_POST[ 'shipping_last_name' ] != '' ) {
                            $event_details[ 'shipping_last_name' ] = $_POST[ 'shipping_last_name' ];
                        }
                        if( isset( $_POST[ 'shipping_address_1' ] ) && $_POST[ 'shipping_address_1' ] != '' ) {
                            $event_details[ 'shipping_address_1' ] = $_POST[ 'shipping_address_1' ];
                        }
                        if ( isset( $_POST[ 'shipping_address_2' ] ) && $_POST[ 'shipping_address_2' ] != '' ) {
                            $event_details[ 'shipping_address_2' ] = $_POST[ 'shipping_address_2' ];
                        }
                        if ( isset( $_POST[ 'shipping_city' ] ) && $_POST[ 'shipping_city' ] != '' ) {
                            $event_details[ 'shipping_city' ] = $_POST[ 'shipping_city' ];
                        }
        
                        $event_details[ 'product_name' ] = $post_title;
                        $event_details[ 'product_qty' ] = $values[ 'quantity' ];
        
                        $event_details[ 'product_total' ] = $_booking[ 'price' ] * $values[ 'quantity' ];
        
                        $gcal->insert_event( $event_details, $results[0]->order_item_id, $user_id, false );
                    }
                }
            }
        }
        
    }
    
    function tours_add_to_calendar_admin( $item_id, $item, $order, $sent_admin = true ) {
        
        if ( ! is_account_page() && ! is_wc_endpoint_url( 'order-received' ) && true === $sent_admin ) {
            
            // check if it's a bookable product
            $post_id = bkap_common::bkap_get_product_id( $item[ 'product_id' ] );
            
            $bookable = bkap_common::bkap_get_bookable_status( $post_id );
            
            if ( $bookable ) {
            
                // check if tour operators are allowed to setup GCal
                if ( 'yes' == get_option( 'bkap_allow_tour_operator_gcal_api' ) ) {
                    $booking_settings = get_post_meta( $post_id, 'woocommerce_booking_settings', true );
        
                    if( isset( $booking_settings[ 'booking_tour_operator' ] ) &&  $booking_settings[ 'booking_tour_operator' ] != 0 ) {
                        // check the tour operator settings
                        
                        $sync_setting = esc_attr( get_the_author_meta( 'tours_calendar_sync_integration_mode', $booking_settings[ 'booking_tour_operator' ] ) );
                        
                        if( "on" == esc_attr( get_the_author_meta( 'tours_add_to_calendar_email_notification', $booking_settings[ 'booking_tour_operator' ] ) ) && 'manually' == $sync_setting ) {
                            $bkap_calendar_sync = new bkap_calendar_sync();
                            $bkap = $bkap_calendar_sync->bkap_create_gcal_obj( $item_id, $item, $order );
                            $bkap_calendar_sync->bkap_add_buttons_emails( $bkap );
                        }
                    }
        
                }

            }   
        }
        
    }
    
    function tours_save_ics_url_feed() {
        
        $user_id = $_POST[ 'user_id' ];
        $ics_table_content = '';
        if( isset( $_POST[ 'ics_url' ] ) ) {
            $ics_url = $_POST[ 'ics_url' ];
        } else {
            $ics_url = '';
        }
        
        if( $ics_url != '' ) {
            $ics_feed_urls = get_the_author_meta( 'tours_ics_feed_urls', $user_id );
            if( $ics_feed_urls == '' || $ics_feed_urls == '{}' || $ics_feed_urls == '[]' || $ics_feed_urls == 'null' ) {
                $ics_feed_urls = array();
            }
        
            if( !in_array( $ics_url, $ics_feed_urls ) ) {
                array_push( $ics_feed_urls, $ics_url );
                update_user_meta( $user_id, 'tours_ics_feed_urls', $ics_feed_urls );
                $ics_table_content = 'yes';
            }
        }
        
        echo $ics_table_content;
        die();
    }
    
    function tours_delete_ics_url_feed() {
        
        $user_id = $_POST[ 'user_id' ];
        
        $ics_table_content = '';
        if( isset( $_POST[ 'ics_feed_key' ] ) ) {
            $ics_url_key = $_POST[ 'ics_feed_key' ];
        } else {
            $ics_url_key = '';
        }
    
        if( $ics_url_key != '' ) {
            $ics_feed_urls = get_the_author_meta( 'tours_ics_feed_urls', $user_id );
            if( $ics_feed_urls == '' || $ics_feed_urls == '{}' || $ics_feed_urls == '[]' || $ics_feed_urls == 'null' ) {
                $ics_feed_urls = array();
            }
    
            unset( $ics_feed_urls[ $ics_url_key ] );
            update_user_meta( $user_id, 'tours_ics_feed_urls', $ics_feed_urls );
            $ics_table_content = 'yes';
        }
    
        echo $ics_table_content;
        die();
    }
    
    function tours_setup_import() {
    
        global $wpdb;
    
        $user_id = $_POST[ 'user_id' ];
    
        if( isset( $_POST[ 'ics_feed_key' ] ) ) {
            $ics_url_key = $_POST[ 'ics_feed_key' ];
        } else {
            $ics_url_key = '';
        }
         
        $ics_feed_urls = get_the_author_meta( 'tours_ics_feed_urls', $user_id );
        if( $ics_feed_urls == '' || $ics_feed_urls == '{}' || $ics_feed_urls == '[]' || $ics_feed_urls == 'null' ) {
            $ics_feed_urls = array();
        }
        mail( 'pinalj1612@gmail.com','ics feed urls',print_r($ics_feed_urls,true));
        if( count( $ics_feed_urls ) > 0 && isset( $ics_feed_urls[ $ics_url_key ] ) ) {
            $ics_feed = $ics_feed_urls[ $ics_url_key ];
        } else {
            $ics_feed = '';
        }
    
        if ( $ics_feed == '' && count( $_POST ) <= 1 ) { // it means it was called using cron, so we need to auto import for all the calendars saved
            if ( isset( $ics_feed_urls ) && count( $ics_feed_urls ) > 0 ) {
    
                foreach ( $ics_feed_urls as $ics_feed ) {
                    $ical = new SG_iCalReader( $ics_feed );
                    $ical_array = $ical->getEvents();
                    $this->tours_import_events( $ical_array );
                }
            }
        } else {
            $ical = new SG_iCalReader( $ics_feed );
            $ical_array = $ical->getEvents();
    
            $this->tours_import_events( $ical_array );
    
        }
    
        die();
    
    }
    
    function tours_import_events( $ical_array ) {
    
        global $wpdb;
    
        $user_id = $_POST[ 'user_id' ];
    
        $event_uids = get_the_author_meta( 'tours_event_uids_ids', $user_id );
        if( $event_uids == '' || $event_uids == '{}' || $event_uids == '[]' || $event_uids == 'null' ) {
            $event_uids = array();
        }
    
        if( isset( $ical_array ) ) {
    
            // get the last stored event count
            $option_name = 'tours_imported_events_' . $user_id . '_%';
            $options_query = "SELECT option_name FROM `" . $wpdb->prefix. "options`
                                        WHERE option_name like %s";
    
            $results = $wpdb->get_results( $wpdb->prepare( $options_query, $option_name ) );
            mail( 'pinalj1612@gmail.com','results',print_r($results,true));
            if (isset( $results ) && count( $results ) > 0 ) {
                $last_count = 0;
                foreach ( $results as $results_key => $option_name ) {
                    $explode_array = explode( '_', $option_name->option_name );
                    $current_id = $explode_array[4];
    
                    if ( $last_count < $current_id ) {
                        $last_count = $current_id;
                    }
                }
    
                $i = $last_count + 1;
    
            } else {
                $i = 0;
            }
    
            foreach( $ical_array as $key_event => $value_event ) {
    
                //Do stuff with the event $event
                if( !in_array( $value_event->uid, $event_uids ) ) {
                    mail( 'pinalj1612@gmail.com',$i, print_r($event_uids,true));
                    $option_name = 'tours_imported_events_' . $user_id . '_' . $i;
                    add_option( $option_name, json_encode( $value_event ) );
    
    
                    array_push( $event_uids, $value_event->uid );
                    update_user_meta( $user_id, 'tours_event_uids_ids', $event_uids );
    
    
                }
                $i++;
            }
            echo "All the Events are Imported.";
        }
    }
    
}
$tours_calendar_sync = new tours_calendar_sync();
?>
<?php
class tours_view_bookings {
    
    public function __construct() {
        
        // tour operator data on the view bookings page
        add_filter('bkap_bookings_table_data',array(&$this,'tour_column_data'),20,1);
        
        // CSV file data
        add_filter('bkap_bookings_export_data',array(&$this,'tours_generate_data_export'),20,1);
        
        // Views on the View Bookings page
        add_filter( 'bkap_bookings_table_views', array( &$this, 'tours_booking_views' ), 20, 1 );	
        
        // modify the total # of items
        add_filter( 'bkap_total_count', array( &$this, 'tours_total_count' ), 20, 1 );
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
    
    function tours_booking_views( $views ) {
        
        $user = new WP_User( get_current_user_id() );
        
        if( $user->roles[0] == 'tour_operator' ) {
            
            $current                  = isset( $_GET['status'] ) ? $_GET['status'] : '';
            
            $bookings_count = $this->tours_bookings_count();
            
            $total_count              = '&nbsp;<span class="count">(' . $bookings_count[ 'total_count' ] . ')</span>';
            $future_count             = '&nbsp;<span class="count">(' . $bookings_count[ 'future_count' ] . ')</span>';
            $today_checkin_count      = '&nbsp;<span class="count">(' . $bookings_count[ 'today_checkin_count' ] . ')</span>';
            $today_checkout_count     = '&nbsp;<span class="count">(' . $bookings_count[ 'today_checkout_count' ] . ')</span>';
            $unpaid                   = '&nbsp;<span class="count">(' . $bookings_count[ 'unpaid' ] . ')</span>';
            $pending_confirmation     = '&nbsp;<span class="count">(' . $bookings_count[ 'pending_confirmation' ] . ')</span>';
            $reserved_by_gcal         = '&nbsp;<span calss="count">(' . $bookings_count[ 'gcal_reserved' ] . ')</span>';
            
            $views = array(
                'all'		=> sprintf( '<a href="%s"%s>%s</a>', remove_query_arg( array( 'status', 'paged' ) ), $current === 'all' || $current == '' ? ' class="current"' : '', __( 'All', 'woocommerce-booking' ) . $total_count ),
                'future'	=> sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'future', 'paged' => FALSE ) ), $current === 'future' ? ' class="current"' : '', __( 'Bookings From Today Onwards', 'woocommerce-booking' ) . $future_count ),
                'today_checkin'	=> sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'today_checkin', 'paged' => FALSE ) ), $current === 'today_checkin' ? ' class="current"' : '', __( 'Todays Check-ins', 'woocommerce-booking' ) . $today_checkin_count ),
                'today_checkout'	=> sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'today_checkout', 'paged' => FALSE ) ), $current === 'today_checkout' ? ' class="current"' : '', __( 'Todays Check-outs', 'woocommerce-booking' ) . $today_checkout_count ),
                'unpaid'	=> sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'unpaid', 'paged' => FALSE ) ), $current === 'unpaid' ? ' class="current"' : '', __( 'Unpaid', 'woocommerce-booking' ) . $unpaid ),
                'pending_confirmation'	=> sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'pending_confirmation', 'paged' => FALSE ) ), $current === 'pending_confirmation' ? ' class="current"' : '', __( 'Pending Confirmation', 'woocommerce-booking' ) . $pending_confirmation ),
                'gcal_reservations'	=> sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'gcal_reservations', 'paged' => FALSE ) ), $current === 'gcal_reservations' ? ' class="current"' : '', __( 'Reserved By GCal', 'woocommerce-booking' ) . $reserved_by_gcal )
            );
            
        }
        
        return $views;
    }
    
    function tours_bookings_count() {
        
        global $wpdb;
        
        $bookings_count = array( 'total_count' => 0,
                                    'future_count' => 0,
                                    'today_checkin_count' => 0,
                                    'today_checkout_count' => 0,
                                    'unpaid' => 0,
                                    'pending_confirmation' => 0,
                                    'gcal_reserved' => 0                      
        );
        
        //Today's date
        $current_time = current_time( 'timestamp' );
        $current_date = date( "Y-m-d", $current_time );
        
        $start_date   = $end_date = '';
        
        if ( isset( $args['start-date'] ) ) {
            $start_date = $args['start-date'];
        }
        
        if ( isset( $args['end-date'] ) ) {
            $end_date = $args['end-date'];
        }
        
        if ( $start_date != '' && $end_date != '' && $start_date != '1970-01-01' && $end_date != '1970-01-01' ) {
            	
        }
        else {
            $today_query = "SELECT a2.order_id,a1.start_date,a1.end_date,a1.post_id FROM `".$wpdb->prefix."booking_history` AS a1,`".$wpdb->prefix."booking_order_history` AS a2 WHERE a1.id = a2.booking_id";
        }
        
        $results_date = $wpdb->get_results ( $today_query );
        
        foreach ( $results_date as $key => $value ) {
            $post_data = get_post( $value->order_id );
            	
            if ( isset( $post_data->post_status ) && $post_data->post_status != 'wc-refunded' && $post_data->post_status != 'trash' && $post_data->post_status != 'wc-cancelled' && $post_data->post_status != '' && $post_data->post_status != 'wc-failed' ) {
        
                // Order details
                $order   =   new WC_Order( $value->order_id );
                $created_via = get_post_meta( $value->order_id, '_created_via', true );
        
                $get_items = $order->get_items();
        
                foreach( $get_items as $item_id => $item_values ) {
                    $booking_status = '';
                    $duplicate_of = bkap_common::bkap_get_product_id( $item_values[ 'product_id' ] );
                    if ( $value->post_id == $duplicate_of ) {
                        
                        // check if it belongs to this tour operator
                        $booking_settings = get_post_meta( $duplicate_of, 'woocommerce_booking_settings', true );
                        if( isset( $booking_settings[ 'booking_tour_operator' ] ) &&  $booking_settings[ 'booking_tour_operator' ] == get_current_user_id() ) {
                        
                            if ( isset( $item_values[ 'wapbk_booking_status' ] ) ) {
                                $booking_status = $item_values[ 'wapbk_booking_status' ];
                            }
            
                            if ( isset( $booking_status ) ) {
                                // if it's not cancelled, add it to the All count
                                if ( 'cancelled' != $booking_status ) {
                                    $bookings_count['total_count'] += 1;
                                }
            
                                // Unpaid count
                                if ( 'confirmed' == $booking_status ) {
                                    $bookings_count[ 'unpaid' ] += 1;
                                } else if( 'pending-confirmation' == $booking_status ) { // pending confirmation count
                                    $bookings_count[ 'pending_confirmation' ] += 1;
                                } else if ( 'paid' == $booking_status || '' == $booking_status ) {
            
                                    if ( $value->start_date >= $current_date ) { // future count
                                        $bookings_count['future_count'] += 1;
                                    }
                                    if ( $value->start_date == $current_date ) { // today's checkin's
                                        $bookings_count['today_checkin_count'] += 1;
                                    }
                                    if ( $value->end_date == $current_date ) { // today's checkouts
                                        $bookings_count['today_checkout_count'] += 1;
                                    }
            
                                }
                                if ( isset( $created_via ) && $created_via == 'GCal' ) {
                                    $bookings_count[ 'gcal_reserved' ] += 1;
                                }
                            }
                        }
                    }
                }
        
            }
        }
        
        return $bookings_count;
    }
    
    function tours_total_count( $total ) {
        
        $user = new WP_User( get_current_user_id() );
        
        if( $user->roles[0] == 'tour_operator' ) {
        
            $booking_count = $this->tours_bookings_count();
            $total = $booking_count[ 'total_count' ];
        }
        return $total;
    }
}
$tours_view_bookings = new tours_view_bookings();
?>
<?php 
class tours_import_bookings {
    
    public function __construct() {
        
        // Import Bookings item count
        add_filter( 'bkap_import_bookings_count', array( &$this, 'tours_import_data_count' ), 10, 1 );
        // Import Bookings data
        add_filter( 'bkap_import_bookings_table_data', array( &$this, 'tours_import_data' ), 10, 1 );

    }
    
    /**
     * 
     */
    function tours_import_bookings_page() {
        $import_bookings = new import_bookings();
        $import_bookings->bkap_woocommerce_import_page();
    }

    /**
     * 
     * @param unknown $total_items
     * @return number
     */
    function tours_import_data_count( $total_items ) {
        
        $user = new WP_User( get_current_user_id() );
        
        if( isset( $user->roles[0] ) && $user->roles[0] == 'tour_operator' ) {
            $total_items = 0;
            
            global $wpdb;
            
            $option_name = 'tours_imported_events_' . $user->ID . '_%';
            
            $options_query = "SELECT * FROM `" . $wpdb->prefix. "options`
                            WHERE option_name like %s";
            
            $results = $wpdb->get_results( $wpdb->prepare( $options_query, $option_name ) );
            $count = count ( $results );
            
            // add records for the product calendars
            $options_query = "SELECT option_name, option_value FROM `" . $wpdb->prefix. "options`
                                        WHERE option_name like 'bkap_imported_events_%'";
            
            $imported_product_results = $wpdb->get_results( $options_query );
            
            $args       = array( 'post_type' => 'product', 'posts_per_page' => -1 );
            $product    = query_posts( $args );

            $product_ids = array();
            foreach($product as $k => $v){
                $product_ids[] = $v->ID;
            }
            if ( is_array( $product_ids ) && count( $product_ids ) > 0 ) {
                foreach( $product_ids as $k => $v ){
                    $duplicate_of  = bkap_common::bkap_get_product_id( $v );
                    $is_bookable = bkap_common::bkap_get_bookable_status( $duplicate_of );
                    if ( $is_bookable ) {
                        $booking_settings = get_post_meta( $duplicate_of, 'woocommerce_booking_settings' , true );
            
                        if( isset( $booking_settings[ 'booking_tour_operator' ] ) &&  $booking_settings[ 'booking_tour_operator' ] == $user->ID ) {
                            $event_uids = get_post_meta( $duplicate_of, 'bkap_event_uids_ids', true );
                        }
                    }
            
                    if ( is_array( $imported_product_results ) && count( $imported_product_results ) > 0 ) {
                        foreach ( $imported_product_results as $key => $value ) {
                            $event_details = json_decode( $value->option_value );
                            $uid = $event_details->uid;
                            if ( is_array( $event_uids ) && count( $event_uids ) > 0 ) {
                                if ( in_array( $uid, $event_uids ) ) {
                                    $results[ $count ]->option_name = $value->option_name;
                                    $results[ $count ]->option_value = $value->option_value;
                                    $count++;
                                }
                            }
                        }
                    }
                }
            }
            
            if (isset( $results ) && count( $results ) > 0 ) {
                $total_items = count( $results );
            } 
        }
        return $total_items;        
    }
    
    /**
     * 
     * @param unknown $import_bookings
     * @return multitype:
     */
    function tours_import_data( $import_bookings ) {
        
        $user = new WP_User( get_current_user_id() );
        $class_obj = new WAPBK_Import_Bookings_Table();
        $per_page         = $class_obj->per_page;
        if( isset( $user->roles[0] ) && $user->roles[0] == 'tour_operator' ) {
            $import_bookings = array();
            // add records for the tour operator's calendar
            global $wpdb;
            $option_name = 'tours_imported_events_' . $user->ID . '_%';
            $options_query = "SELECT option_name, option_value FROM `" . $wpdb->prefix. "options`
                            WHERE option_name like %s";
            
            $results = $wpdb->get_results( $wpdb->prepare( $options_query, $option_name ) );
            
            $count = count ( $results );
            
            // add records for the product calendars
            $options_query = "SELECT option_name, option_value FROM `" . $wpdb->prefix. "options`
                                        WHERE option_name like 'bkap_imported_events_%'";
            
            $imported_product_results = $wpdb->get_results( $options_query );
            
            $args       = array( 'post_type' => 'product', 'posts_per_page' => -1 );
            $product    = query_posts( $args );
             
            $product_ids = array();
            foreach($product as $k => $v){
                $product_ids[] = $v->ID;
            }
            
            if ( is_array( $product_ids ) && count( $product_ids ) > 0 ) {
                foreach( $product_ids as $k => $v ){
                    $duplicate_of  = bkap_common::bkap_get_product_id( $v );
            
                    $is_bookable = bkap_common::bkap_get_bookable_status( $duplicate_of );
            
                    if ( $is_bookable ) {
                        $booking_settings = get_post_meta( $duplicate_of, 'woocommerce_booking_settings' , true );
            
                        if( isset( $booking_settings[ 'booking_tour_operator' ] ) &&  $booking_settings[ 'booking_tour_operator' ] == $user->ID ) {
                            $event_uids = get_post_meta( $duplicate_of, 'bkap_event_uids_ids', true );
            
                        }
                    }
            
            
                    if ( is_array( $imported_product_results ) && count( $imported_product_results ) > 0 ) {
                        foreach ( $imported_product_results as $key => $value ) {
                            $event_details = json_decode( $value->option_value );
                            $uid = $event_details->uid;
            
                            if ( is_array( $event_uids ) && count( $event_uids ) > 0 ) {
                                if ( in_array( $uid, $event_uids ) ) {
                                    $results[ $count ]->option_name = $value->option_name;
                                    $results[ $count ]->option_value = $value->option_value;
                                    $count++;
                                }
                            }
                             
                        }
                    }
            
                }
            }
            
            if ( isset( $_GET[ 'paged' ] ) && $_GET[ 'paged' ] > 1 ) {
                $page_number = $_GET[ 'paged' ] - 1;
            } else {
                $page_number = 0;
            }
            
            if( count( $results ) > $per_page ) {
                $results = array_chunk( $results, $per_page );
                if( isset( $results[ $page_number ] ) ) {
                    $results = $results[ $page_number ];
                } else {
                    $results = array();
                }
            }
            
            $class_obj = new WAPBK_Import_Bookings_Table();
            $import_bookings = $class_obj->bkap_create_data( $results );
        }
        
        return $import_bookings;
        
    }

}
$tours_import_bookings = new tours_import_bookings();
?>
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
        
        if( $user->roles[0] == 'tour_operator' ) {
            $total_items = 0;
            
            global $wpdb;
            
            $option_name = 'tours_imported_events_' . $user->ID . '_%';
            
            $options_query = "SELECT * FROM `" . $wpdb->prefix. "options`
                            WHERE option_name like %s";
            
            $results = $wpdb->get_results( $wpdb->prepare( $options_query, $option_name ) );
            
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
        
        if( $user->roles[0] == 'tour_operator' ) {
            $import_bookings = array();
            
            // add records for the tour operator's calendar
            global $wpdb;
            
            $option_name = 'tours_imported_events_' . $user->ID . '_%';
            $options_query = "SELECT option_name, option_value FROM `" . $wpdb->prefix. "options`
                            WHERE option_name like %s";
            
            $results = $wpdb->get_results( $wpdb->prepare( $options_query, $option_name ) );
            
            $class_obj = new WAPBK_Import_Bookings_Table();
            $import_bookings = $class_obj->bkap_create_data( $results );
       
        }

        return $import_bookings;
        
    }

}
$tours_import_bookings = new tours_import_bookings();
?>
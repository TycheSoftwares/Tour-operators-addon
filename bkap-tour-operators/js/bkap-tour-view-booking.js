jQuery( function ( $ ) {

	// Add buttons to product screen.
	var $product_screen = $( '.edit-php.post-type-bkap_booking' ),
		$create_action  = $product_screen.find( '.page-title-action:first' ),
		$calendar_create_button = $( '#bkap_create_booking' );
		//$bkap_add_order = $( '#bkap_add_order' );

	$create_action.hide();
	if ( $calendar_create_button ) {
		$calendar_create_button.hide();
	}
	//$bkap_add_order.hide();
});
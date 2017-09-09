jQuery( function ( $ ) {

	// Add buttons to product screen.
	var $product_screen = $( '.edit-php.post-type-bkap_booking' ),
		$create_action  = $product_screen.find( '.page-title-action:first' ),
		$bkap_add_order = $( '#bkap_add_order' );

	$create_action.hide();
	$bkap_add_order.hide();
});
<?php

class tour_operators_print_tickets {
    
    public static $email = '';
	
	public static function send_tickets ($order_id){
		
		$send_emails_to_tour_operators = 'on';
		
		$send_emails_to_tour_operators = get_option( 'bkap_send_tickets_to_tour_operators' );
		
		if( $send_emails_to_tour_operators == 'on' ) {
		    tour_operators_print_tickets::$email = 'YES';
			$order_obj = new WC_order($order_id);
			$order_items = $order_obj->get_items();
			foreach($order_items as $item_key => $item_value) {
				$product_id = $item_value['product_id'];
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true);
				$order_item_to_display = array();
				if(isset($booking_settings['booking_tour_operator'])) {
					$tour_operator = $booking_settings['booking_tour_operator'];
					$tour_operator_data = get_userdata($tour_operator);
					$tour_operator_email = '';
					if (isset($tour_operator_data->user_email)) {
						$tour_operator_email = $tour_operator_data->user_email;
					}
				}
				else {
					$tour_operator = '';
					$tour_operator_email = '';
				}
				if($tour_operator != '' ) {
					$order_item_to_display[$item_key] = $item_value;
				}
				$from_email = get_option('woocommerce_email_from_address');
				$from_email_name = get_option('woocommerce_email_from_name');
				$email_heading = 'New customer order';//get_headers('email');
								
				if ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) {
				    $order_date = $order_obj->order_date;
				} else {
    				$order_created_obj = $order_obj->get_date_created();
    				$order_date      = $order_created_obj->format('Y-m-d H:i:s');
				}
				$subject = 'New customer order ('.$order_id.') - '.date('F j, Y',strtotime( $order_date ));
				// Load colours
				$bg 		= get_option( 'woocommerce_email_background_color' );
				$body		= get_option( 'woocommerce_email_body_background_color' );
				$base 		= get_option( 'woocommerce_email_base_color' );
				$base_text 	= wc_light_or_dark( $base, '#202020', '#ffffff' );
				$text 		= get_option( 'woocommerce_email_text_color' );
					
				$bg_darker_10 = wc_hex_darker( $bg, 10 );
				$base_lighter_20 = wc_hex_lighter( $base, 20 );
				$text_lighter_20 = wc_hex_lighter( $text, 20 );
					
				// For gmail compatibility, including CSS styles in head/body are stripped out therefore styles need to be inline. These variables contain rules which are added to the template inline. !important; is a gmail hack to prevent styles being stripped if it doesn't like something.
				$wrapper = "
				background-color: " . esc_attr( $bg ) . ";
				width:100%;
				-webkit-text-size-adjust:none !important;
				margin:0;
				padding: 70px 0 70px 0;
				";
				$template_container = "
				box-shadow:0 0 0 3px rgba(0,0,0,0.025) !important;
				border-radius:6px !important;
				background-color: " . esc_attr( $body ) . ";
				border: 1px solid $bg_darker_10;
				border-radius:6px !important;
				";
				$template_header = "
				background-color: " . esc_attr( $base ) .";
				color: $base_text;
				border-top-left-radius:6px !important;
				border-top-right-radius:6px !important;
				border-bottom: 0;
				font-family:Arial;
				font-weight:bold;
				line-height:100%;
				vertical-align:middle;
				";
				$body_content = "
				background-color: " . esc_attr( $body ) . ";
				border-radius:6px !important;
				";
				$body_content_inner = "
				color: $text_lighter_20;
				font-family:Arial;
				font-size:14px;
				line-height:150%;
				text-align:left;
				";
				$header_content_h1 = "
				color: " . esc_attr( $base_text ) . ";
				margin:0;
				padding: 28px 24px;
				text-shadow: 0 1px 0 $base_lighter_20;
				display:block;
				font-family:Arial;
				font-size:30px;
				font-weight:bold;
				text-align:left;
				line-height: 150%;
				";
	
				$template = '<!DOCTYPE html>
				<html>
				<head>
				<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
				<title><?php echo get_bloginfo( "name" ); ?></title>
				</head>
				<body leftmargin="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">
				<div style="<?php echo $wrapper; ?>">
				<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
				<tr>
				<td align="center" valign="top">
				<div id="template_header_image">';
				if ( $img = get_option( 'woocommerce_email_header_image' ) ) {
					$template .= '<p style="margin-top:0;"><img src="' . esc_url( $img ) . '" alt="' . get_bloginfo( 'name' ) . '" /></p>';
				}
				$template .= '</div>
				<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_container" style="'.$template_container.'">
				<tr>
				<td align="center" valign="top">
				<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_header" style="'.$template_header.'" bgcolor="'.$base.'">
				<tr>
				<td>
				<h1 style="'.$header_content_h1.'">'.$email_heading.'</h1>
	
				</td>
				</tr>
				</table>
				</td>
				</tr>
				<tr>
				<td align="center" valign="top">
	
				<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_body">
				<tr>
				<td valign="top" style="'.$body_content.'">
				<!-- Content -->
				<table border="0" cellpadding="20" cellspacing="0" width="100%">
				<tr>
				<td valign="top">
				<div style="'.$body_content_inner.'">';
				$billing_first_name = ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) ? $order_obj->billing_first_name : $order_obj->get_billing_first_name();
				$billing_last_name = ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) ? $order_obj->billing_last_name : $order_obj->get_billing_last_name();
				$template .= '<p>'.__( 'You have received an order from '. $billing_first_name . ' ' . $billing_last_name .'. The order is as follows:', 'woocommerce' ).'</p>';
				ob_start();
				do_action( 'woocommerce_email_before_order_table', $order_obj, true, false );
				$template .= ob_get_clean();
					
				$template .= '<h2><a href="'.admin_url( 'post.php?post=' . $order_id . '&action=edit' ).'">'.__( 'Order #'.$order_obj->get_order_number(), 'woocommerce').'</a> (<time datetime="'.date_i18n( 'c', strtotime( $order_date )).'">'. date_i18n( wc_date_format(), strtotime( $order_date ) ).'</time>)</h2>
				<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee;" border="1" bordercolor="#eee">
				<thead>
				<tr>
				<th scope="col" style="text-align:left; border: 1px solid #eee;">'. __( 'Product', 'woocommerce' ).'</th>
				<th scope="col" style="text-align:left; border: 1px solid #eee;">'.__('Quantity', 'woocommerce' ).'</th>
				<th scope="col" style="text-align:left; border: 1px solid #eee;">'. __( 'Price', 'woocommerce' ).'</th>
				</tr>
				</thead>
				<tbody>';
				ob_start();
				wc_get_template( 'emails/email-order-items.php', array(
						'order'                 => $order_obj,
						'items'                 => $order_item_to_display,
						'show_download_links'   => false,
						'show_sku'              => false,
						'show_purchase_note'    => false,
						'show_image'            => false,
						'image_size'            => array( 32, 32 ),
						'plain_text'            => false
				) );
				$template .= ob_get_clean();
				$template .='</tbody>
				<tfoot>';
				if ( $totals = $order_obj->get_order_item_totals() ) {
					$i = 0;
					$currency_symbol = get_woocommerce_currency_symbol();
					foreach ( $totals as $total ) {
						$i++;
						//print_r($total);
						if ( $i == 1 ) {
							$template .= '<tr>
							<th scope="row" colspan="2" style="text-align:left; border: 1px solid #eee; '; $template .= 'border-top-width: 4px;"';$template .= '>'.$total['label'].'</th>
							<td style="text-align:left; border: 1px solid #eee;'; $template .=  'border-top-width: 4px;"'; $template .='>'.$order_obj->get_formatted_line_subtotal( $item_value ).'</td>
							</tr>';
						}
					}
				}
				$billing_email = ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) ? $order_obj->billing_email :$order_obj->get_billing_email();
				$billing_phone = ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) ? $order_obj->billing_phone : $order_obj->get_billing_phone();
				$template .=  '</tfoot>
				</table>
				<h2>' . __( 'Customer details', 'woocommerce' ) . '</h2>
				<p><strong>' . __( 'Email:', 'woocommerce' ) . '</strong>' . $billing_email . '</p>
				<p><strong>' . __( 'Tel:', 'woocommerce' ) . '</strong>' . $billing_phone . '</p>';
				ob_start();
				wc_get_template( 'emails/email-addresses.php', array( 'order' => $order_obj ) );
				$template .= ob_get_clean();
				ob_start();
				wc_get_template( 'emails/email-footer.php' );
				$template .= ob_get_clean();
				$headers_email[] = "From: ".$from_email_name." <".$from_email.">"."\r\n";
				$headers_email[] = "Content-type: text/html";
				wp_mail( $tour_operator_email, $subject, $template, $headers_email );
				tour_operators_print_tickets::$email = 'NO';
			}
		}
	}
	
	public static function tour_operators_details( $order, $sent_to_admin = false, $plain_text = false ) {
		$order_items = $order->get_items();
		$booking_date_label = get_option('book_item-meta-date');
		
		$booking_date = '';
		foreach($order_items as $item_key => $item_value) {
			$product_id = $item_value['product_id'];
			$prod_name = get_post($product_id);
			$product_name = $prod_name->post_title;
			//print_r($item_value);
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true);
			if(isset($booking_settings['booking_tour_operator'])) {
				$tour_operator = $booking_settings['booking_tour_operator'];
				$tour_operator_data = get_userdata($tour_operator);
				$tour_operator_phone = get_user_meta($tour_operator);
				$tour_operator_details = "<h3>".__( 'Tour operators details', 'woocommerce' )."</h3>";
				$tour_operator_details .= "<p><strong>".__( 'Product: ', 'woocommerce' )."</strong>".$product_name."</p>";
				if(isset($tour_operator_data->display_name) && $tour_operator_data->display_name != '') {
					$tour_operator_details .= "<p><strong>".__( 'Name: ', 'woocommerce' )."</strong>".$tour_operator_data->display_name."</p>";
				}
				else {
					$tour_operator_details .= "<p><strong>".__( 'Name: ', 'woocommerce' )."</strong></p>";
				}
				
				if(isset($tour_operator_data->user_email) && $tour_operator_data->user_email != '') {
					$tour_operator_details .= "<p><strong>". __( 'Email: ', 'woocommerce' )."</strong>".$tour_operator_data->user_email."</p>";
				}
				else {
					$tour_operator_details .= "<p><strong>". __( 'Email: ', 'woocommerce' )."</strong></p>";
				}
				
				if(isset($tour_operator_data->user_url) && $tour_operator_data->user_url != '') {
					$tour_operator_details .= "<p><strong>". __( 'Website: ', 'woocommerce' )."</strong>".$tour_operator_data->user_url."</p>";
				}
				else {
					$tour_operator_details .= "<p><strong>". __( 'Website: ', 'woocommerce' )."</strong></p>";
				}
						
				if(isset($tour_operator_phone['phone'][0]) && $tour_operator_phone['phone'][0] != '') {
					$tour_operator_details .= "<p><strong>". __( 'Phone: ', 'woocommerce' )."</strong>".$tour_operator_phone['phone'][0]."</p>";
				}
				else {
					$tour_operator_details .= "<p><strong>". __( 'Phone: ', 'woocommerce' )."</strong></p>";
				}

				echo $tour_operator_details;
	
			}
		}
	}
}
$tour_operators_print_tickets = new tour_operators_print_tickets();
?>
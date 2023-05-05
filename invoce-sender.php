<?php 
/*
Plugin Name: invoce-sender
Description: This is a plugin that make a pdf invoice and send it to as a whatsapp message.
Version: 1.0.0
Author: Hamed Elahi
Author URI: https://egeekbin.com/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: invoce-sender
*/



add_action('woocommerce_thankyou', 'generate_sales_report', 10, 1);


require_once( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php');
use GreenApi\RestApi\GreenApiClient;

define( "ID_INSTANCE", "1101806573" );
define( "API_TOKEN_INSTANCE", "fe4132fdab0647f48532e1ca4dfdbb69ff64ddf987db49c98b" );



function generate_sales_report($order_id)
{
    
    // Get the order details
    $order = new WC_Order($order_id);
    $order_id = $order->get_id();
    $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
    $total = $order->get_total();
    $date = $order->get_date_created()->format('M j, Y');
    $address = $order->get_formatted_billing_address();

    if ( $order ) {
        $items = $order->get_items();
        $data=[];
        $counter = 1;
        $sum_of_all_quantity = 0;
        $sum_of_cart = 0;
        $sum_of_cart_by_discount = 0;
        foreach ( $items as $item ) {
            $product_id = $item->get_product_id();
            $product_name = $item->get_name();
            $product_qty = $item->get_quantity();
            $product = wc_get_product( $product_id );
            $regular_price = $product->get_regular_price();
            $price = $product->get_price();
            $product_total = $regular_price * $product_qty;
            $product_total_by_discount = $price * $product_qty;
            $sum_of_all_quantity +=  $product_qty;
            $sum_of_cart +=  intval($product_total);
            $sum_of_cart_by_discount += intval($product_total_by_discount);
            
            // Do something with the product information
            
            array_push($data, array(
                'counter' => $counter,
                'product_name' => $product_name,
                'product_qty' => $product_qty,
                'regular_price' => wc_price($regular_price),
                'product_total' => wc_price($product_total),
                'product_discount' => wc_price(($regular_price - $price) * $product_qty),
                'product_total_by_discount' => wc_price($product_total_by_discount)
            ));
            $counter++;
        }

    }
    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
    $mpdf->SetDirectionality('rtl');
    $mpdf->autoScriptToLang = true;
    $mpdf->baseScript = 1; 
    $mpdf->autoVietnamese = true;
    $mpdf->autoArabic = true;
    $mpdf->autoLangToFont = true;
    $mpdf->SetFont('dejavusans', '', 12);
    

    $html = '<table border="1" cellpadding="2" cellspacing="2">
    <thead>
        <tr style="background-color:#FFFF00;color:#0000FF;">
        <td width="30" align="center"><b>ردیف</b></td>
        <td width="165" align="center"><b>نام محصول</b></td>
        <td width="30" align="center"><b>تعداد</b></td>
        <td width="120" align="center"><b>قیمت واحد</b></td>
        <td width="120" align="center"> <b>قیمت کل</b></td>
        <td width="120" align="center"> <b>تخفیف</b></td>
        <td width="140" align="center"><b>قیمت کل با تخفیف</b></td>
        </tr>
        </thead>'    ;
    foreach($data as $row){
        $html .= '<tr>';
        foreach($row as $key => $cell){
            $html .= '<td>' . $cell . '</td>';
        }
        $html .= '</tr>';
    }

    
    $html .= '<tfoot>
        <tr style="background-color:red;color:white;">
        <td colspan="2" width="110" align="center"><b>مجموع تعداد: '. $sum_of_all_quantity .'</b></td>
        <td colspan="2" width="170" align="center"><b>جمع کل خرید: ' . wc_price( $sum_of_cart ) . '</b></td>
        <td colspan="2" align="center"><b>حمل و نقل: '. $order->get_shipping_to_display() .'</b></td>
        <td colspan="2" align="center"><b>قیمت نهایی: '. wc_price($order->get_total()) .'</b></td>
        </tr>
        </tfoot>';

    $html .= '</table><br>';

    $html .= 'خریداری شده در تاریخ ' . $date . '<br>';
    $html .= 'آدرس: ' . $address . '<br>';

    $mpdf->WriteHTML($html);


    $mpdf->OutputFile(plugin_dir_path( __FILE__ ) . 'invoices/' . $customer_name . '_' . $order_id . '.pdf'); 


    $greenApi = new GreenApiClient( ID_INSTANCE, API_TOKEN_INSTANCE );

    $file = plugin_dir_path( __FILE__ ) . 'invoices/' . $customer_name . '_' . $order_id . '.pdf';

    $result = $greenApi->sending->sendFileByUpload('989021012150@c.us', $file, $customer_name . '_' . $order_id . '.pdf', $customer_name);

    if($result->error == null && $result->code == 200){
        unlink($file);
    }else{
        var_dump('err =>  ' . $result->error);
    }
} 

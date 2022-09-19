<?php

/*
Plugin Name: TriPay Payment Gateway
Plugin URI: https://tripay.co.id
Description: Terima pembayaran online dengan banyak pilihan channel seperti Virtual Account, Convenience Store, E-Wallet, E-Banking, dll
Version: 3.2.6
Author: PT Trijaya Digital Grup
Author URI: https://tridi.net
WC requires at least: 3.1.0
WC tested up to: 6.6.1
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Text Domain: woocommerce-gateway-tripay
Domain Path: /languages
------------------------------------------------------------------------
*/

if (!defined('ABSPATH')) {
    exit;
}

add_filter('plugin_row_meta', 'woocommerce_tripay_plugin_row_meta', 10, 2);
function woocommerce_tripay_plugin_row_meta($links, $file)
{
    if (plugin_basename(__FILE__) == $file) {
        $row_meta = array(
          'docs'    => '<a href="' . esc_url('https://tripay.co.id/docs/1/cara-install-setting-plugin-untuk-woocommerce-wordpress') . '" target="_blank" aria-label="' . esc_attr__('Plugin Additional Links', 'domain') . '">' . esc_html__('Dokumentasi', 'domain') . '</a>',
          'docs_api' => '<a href="' . esc_url('https://tripay.co.id/developer') . '" target="_blank" aria-label="' . esc_attr__('Plugin Additional Links', 'domain') . '">' . esc_html__('Dokumentasi API', 'domain') . '</a>',
        );
        return array_merge($links, $row_meta);
    }
    return (array) $links;
}

add_action('plugins_loaded', 'woocommerce_tripay_init', 0);
function woocommerce_tripay_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    include_once dirname(__FILE__) . '/includes/admin/class-wc-tripay-payment.php';

    if (!class_exists('Tripay_Payment_Gateway')) {
        abstract class Tripay_Payment_Gateway extends WC_Payment_Gateway
        {
            public static $log_enabled = false;
            public static $log = false;
            public $enable_icon = false;

            public function __construct()
            {
                $this->id = $this->sub_id;
                $this->payment_method = '';
                $this->init_settings();

                $this->title = empty($this->settings['title']) ? "Pembayaran TriPay" : $this->settings['title'];
                $this->expired =  @intval($this->settings["expired"]) <= 0 ? 1 : @intval($this->settings["expired"]);
                $this->enabled = isset($this->settings['enabled']) == 'yes' ? true : false;
                $this->description = isset($this->settings['description']) ? $this->settings['description']: '';
                $this->apikey = get_option('tripay_api_key');
                $this->merchantCode = get_option('tripay_merchant_code');
                $this->privateKey = get_option("tripay_private_key");
                $this->status_success = get_option("tripay_success_status");
                $this->initial_status = get_option("tripay_initial_status");
                $this->redirect_page = get_option('tripay_redirect_page');
                $this->verify_callback = get_option('tripay_verify_callback') == 'yes' ? true : false;
                $this->customer_invoice_email = get_option('tripay_customer_invoice_email') == 'yes' ? true : false;

                self::$log_enabled = get_option("tripay_debug") == 'yes' ? true : false;

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
                add_action('woocommerce_api_wc_gateway_tripay', array($this, 'handle_callback'));
                add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
                add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
            }

            public function email_instructions($order, $sent_to_admin, $plain_text = false)
            {
                if (!$sent_to_admin && $this->id === $order->get_payment_method() && ($order->has_status('pending') || $order->has_status('on-hold'))) {
                    $checkoutMethod = get_post_meta($order->get_id(), '_tripay_payment_type', true);
                    $expiredTime = (int) get_post_meta($order->get_id(), '_tripay_payment_expired_time', true);

                    if ($checkoutMethod == 'DIRECT') {
                        $payCode = get_post_meta($order->get_id(), '_tripay_payment_pay_code', true);
                        $hasQR = get_post_meta($order->get_id(), '_tripay_payment_has_qr', true);

                        switch (wp_timezone_string()) {
                            case 'Asia/Jakarta':   $tz = 'WIB';  break;
                            case 'Asia/Makassar':
                            case 'Asia/Pontianak': $tz = 'WITA'; break;
                            case 'Asia/Jayapura':  $tz = 'WIT';  break;
                            default:               $tz = '';     break;
                        }

                        $datetime = new \DateTime();
                        $datetime->setTimestamp($expiredTime);
                        $datetime->setTimezone(new \DateTimeZone(wp_timezone_string()));

                        $html = '<p>Untuk menyelesaikan pesanan, silahkan lakukan pembayaran berikut:</p>';
                        $html .= '<p>';
                        $html .= '<b>Metode Pembayaran:</b> '.$this->title;

                        if (!empty($payCode) && $hasQR == '0') {
                            $html .= '<br/><b>Kode Bayar/Nomor VA:</b> '.$payCode;
                        }

                        $html .= '<br/><b>Batas Pembayaran:</b> '.$datetime->format('d F Y H:i').' '.$tz;
                        $html .= '</p>';

                        if ($hasQR == '1') {
                            $qrUrl = TripayPayment::get_qr_url($order);
                            $html .= '<p><a href="'.$qrUrl.'" target="_blank"><img src="'.$qrUrl.'" style="width:100%;max-width:150px" /></a></p>';
                        }

                        echo $html;
                    } else {
                        $checkout_url = TripayPayment::get_checkout_url($order);
                        $payUrl = get_post_meta($order->get_id(), '_tripay_payment_pay_url', true);

                        $url = empty($payUrl) ? $checkout_url : $payUrl;
                        echo '<p>Untuk menyelesaikan pembayaran, silahkan klik tautan berikut: <a href="'.$url.'">'.$url.'</a></p>';
                    }
                }
            }

            public function receipt_page($order)
            {
                echo TripayPayment::view_order_and_thankyou_page($order);
            }

            public function admin_options()
            {
                echo '<table class="form-table">';
                $this->generate_settings_html();
                echo '</table>';
            }

            public function process_payment($order_id)
            {
                if (!$this->enabled) {
                    wc_add_notice("payment method is not available", "error");
                    return;
                }

                $order = new WC_Order($order_id);
                $exchangeValue = get_option(TripayPayment::$option_prefix.'_exchange_rate', null);

                if ($order->has_status('pending') && $this->initial_status == 'on-hold') {
                    $order->update_status('on-hold', __('Menunggu Pembayaran dengan TriPay', 'woocommerce'));
                } elseif ($order->has_status('on-hold') && $this->initial_status == 'pending') {
                    $order->update_status('pending', __('Menunggu Pembayaran dengan TriPay', 'woocommerce'));
                }

                $totalAmount = TripayPayment::convertToIdr($order->order_total, $exchangeValue);

                $url = TripayPayment::ApiUrl("/transaction/create");
                $current_user = $order->billing_first_name . " " . $order->billing_last_name;

                $item_details = [];

                foreach ($order->get_items() as $item_key => $item) {
                    $item_name    = $item->get_name();
                    $quantity     = $item->get_quantity();
                    $product_price  = $item->get_subtotal();

                    $item_details[] = array(
                    'name' => $item_name,
                    'price' => TripayPayment::convertToIdr($product_price/intval($quantity), $exchangeValue),
                    'quantity' => $quantity
                  );
                }
    
                if ($order->get_total_shipping() > 0) {
                    $item_details[] = array(
                    'name' =>  __('Shipping Fee', 'woocommerce'),
                    'price' => TripayPayment::convertToIdr($order->get_total_shipping(), $exchangeValue),
                    'quantity' => 1
                  );
                }

                if ($order->get_total_tax() > 0) {
                    $item_details[] = array(
                    'name' => __('Tax', 'woocommerce'),
                    'price' => TripayPayment::convertToIdr($order->get_total_tax(), $exchangeValue),
                    'quantity' => 1
                  );
                }

                if ($order->get_total_discount() > 0) {
                    $item_details[] = array(
                    'name' => __('Total Discount', 'woocommerce'),
                    'price' => TripayPayment::convertToIdr($order->get_total_discount(), $exchangeValue) * -1,
                    'quantity' => 1
                  );
                }

                if (count($order->get_fees()) > 0) {
                    $fees = $order->get_fees();
                    $i = 0;
                    foreach ($fees as $item) {
                        if ($item['name'] == __('Surcharge', 'woocommerce')) {
                            $totalAmount = $totalAmount - TripayPayment::convertToIdr($item['line_total'], $exchangeValue);
                            continue;
                        }
                    
                        $item_details[] = array(
                      'name' => $item['name'],
                      'price' => TripayPayment::convertToIdr($item['line_total'], $exchangeValue),
                      'quantity' => 1
                    );
                        $i++;
                    }
                }

                $totalAmount = 0;
                foreach ($item_details as $id) {
                    $totalAmount += $id['price'] * $id['quantity'];
                }

                $signature = hash_hmac("sha256", $this->merchantCode.$order_id.$totalAmount, $this->privateKey);

                switch ($this->redirect_page) {
                    case "orderpay": $returnUrl = $order->get_checkout_payment_url(); break;
                    case "thankyou":
                    default:         $returnUrl = $this->get_return_url($order); break;
                }

                $expired_time = (time()+(24*60*60*$this->expired));

                if (get_option('woocommerce_manage_stock') == 'yes' && get_option('woocommerce_hold_stock_minutes') > 0) {
                    $held_duration = get_option('woocommerce_hold_stock_minutes');
                    $expired_time = (time()+(60*$held_duration));
                }

                $params = array(
                    "amount" 			=> $totalAmount,
                    "method" 			=> $this->payment_method,
                    "merchant_ref" 		=> $order_id,
                    "customer_name" 	=> $current_user,
                    "customer_email" 	=> $order->billing_email,
                    "customer_phone" 	=> $order->billing_phone,
                    "expired_time" 		=> $expired_time,
                    "return_url" 		=> $returnUrl,
                    "order_items" 		=> $item_details,
                    "signature" 		=> $signature,
                );

                $headers = array(
                    'Authorization' => 'Bearer '.$this->apikey,
                    'X-Plugin-Meta' => 'woocommerce|'.TripayPayment::$version
                );

                $this->log("Create a request for inquiry");
                $this->log('API URL: '.$url);
                $this->log('Payload: '.json_encode($params));

                $response = wp_remote_post($url, array(
                    'method' => 'POST',
                    'body' => $params,
                    'timeout' => 90,
                    'headers' => $headers,
                ));

                if (is_wp_error($response)) {
                    $this->log("WP Error: ".implode(", ", $response->get_error_messages()));
                    throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'tripay'));
                }

                $response_body = wp_remote_retrieve_body($response);
                $response_code = wp_remote_retrieve_response_code($response);

                $this->log('Response: '.$response_body);

                if (empty($response_body)) {
                    throw new Exception(__('TriPay Response was empty.', 'tripay'));
                }

                $resp = json_decode($response_body);

                if ($resp->success === true) {
                    WC()->cart->empty_cart();

                    $order->update_meta_data('_tripay_payment_amount', $resp->data->amount);
                    $order->update_meta_data('_tripay_payment_reference', $resp->data->reference);
                    $order->update_meta_data('_tripay_payment_pay_url', !empty($resp->data->pay_url) ? $resp->data->pay_url : '');
                    $order->update_meta_data('_tripay_payment_pay_code', !empty($resp->data->pay_code) ? $resp->data->pay_code : '');
                    $order->update_meta_data('_tripay_payment_expired_time', $resp->data->expired_time);
                    $order->update_meta_data('_tripay_payment_has_qr', (isset($resp->data->qr_string)&&!empty($resp->data->qr_string)?'1':'0'));

                    $datetime = new \DateTime();
                    $datetime->setTimestamp($resp->data->expired_time);
                    $datetime->setTimezone(new \DateTimeZone(wp_timezone_string()));
                    $order->update_meta_data('_tripay_payment_expired_date', $datetime->format('d F Y H:i'));

                    $systemWant = @TripayPayment::gateways(str_replace('tripay_', '', $this->id))['type'];
                    $userWant = isset($this->settings['checkout_method']) && !empty($this->settings['checkout_method']) ? $this->settings['checkout_method'] : 'REDIRECT';

                    if ($systemWant == 'REDIRECT' || $userWant == 'REDIRECT') { // Only support redirect :(
                        $order->update_meta_data('_tripay_payment_type', 'REDIRECT');
                        $redirectTo = !empty($resp->data->pay_url) ? $resp->data->pay_url : $resp->data->checkout_url;
                    } elseif ($userWant == 'DIRECT') {
                        $order->update_meta_data('_tripay_payment_type', 'DIRECT');
                        $redirectTo = $returnUrl;
                    }

                    $order->save();

                    if ($this->customer_invoice_email) {
                        WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger($order_id);
                    }
                      
                    return array(
                        'result' => 'success',
                        'redirect' => $redirectTo,
                    );
                } else {
                    if ($response_code == 400) {
                        wc_add_notice($resp->message, "error");
                        $order->add_order_note('Error: '.$resp->message);
                    } else {
                        wc_add_notice("Error processing payment", "error");
                        $order->add_order_note('Error: Error processing payment.');
                    }

                    return;
                }
            }

            public function handle_callback()
            {
                $json = file_get_contents("php://input");
                $callbackSignature = isset($_SERVER['HTTP_X_CALLBACK_SIGNATURE']) ? $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] : '';
                $signature = hash_hmac("sha256", $json, $this->privateKey);
                if (!hash_equals($signature, $callbackSignature)) {
                    $this->log("Invalid Signature: local(".$signature.") vs incoming(".$callbackSignature.")");
                    die("Invalid Signature");
                }

                $data = json_decode($json);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    die("Invalid JSON");
                }

                $event = isset($_SERVER['HTTP_X_CALLBACK_EVENT']) ? $_SERVER['HTTP_X_CALLBACK_EVENT'] : '';
                
                if ($event == 'payment_status') {
                    $order_id = wc_clean(stripslashes($data->merchant_ref));
                    $reference = wc_clean(stripslashes($data->reference));

                    $order = new WC_Order($order_id);

                    switch (strtoupper($data->status)) {
                        case "PAID":
                            if ($order->has_status($this->status_success)) { // jika order sudah berstatus terbayar, tolak callback
                                die('Current order status is '.$order->get_status());
                            }

                            if (! $this->verify_callback($order, $reference, ['PAID'])) {
                                die('Callback verification failed');
                            }

                            $order->update_status($this->status_success);
                            $order->add_order_note(__('Pembayaran telah dilakukan melalui TriPay dengan No. Referensi ' . $reference, 'woocommerce'));

                            echo json_encode(['success' => true, 'message' => 'Order '.$order_id.' has been processed']);
                            exit;
                        break;


                        case "EXPIRED":
                            if (! $order->has_status($this->initial_status)) { // jika order sudah mengalami perubahan status dari status awal, tolak callback
                                die('Current order status is '.$order->get_status());
                            }

                            if (! $this->verify_callback($order, $reference, ['EXPIRED'])) {
                                die('Callback verification failed');
                            }

                            $order->update_status('failed');
                            $order->add_order_note(__('Pembayaran melalui TriPay kadaluarsa. Ref: '. $reference, 'woocommerce'));

                            echo json_encode(['success' => true, 'message' => 'Order '.$order_id.' has been updated']);
                            exit;
                        break;


                        case "FAILED":
                            if (! $order->has_status($this->initial_status)) { // jika order sudah mengalami perubahan status dari status awal, tolak callback
                                die('Current order status is '.$order->get_status());
                            }

                            if (! $this->verify_callback($order, $reference, ['FAILED'])) {
                                die('Callback verification failed');
                            }

                            $order->update_status('failed');
                            $order->add_order_note(__('Pembayaran melalui TriPay gagal. Ref: '. $reference, 'woocommerce'));

                            echo json_encode(['success' => true, 'message' => 'Order '.$order_id.' has been updated']);
                            exit;
                        break;

                        default:
                        break;
                    }
                }

                die("No action was taken");
            }

            public function verify_callback(WC_Order $order, $reference, array $expectedStatuses = ['PAID'])
            {
                if ($this->verify_callback !== true) {
                    return true;
                }

                $url = TripayPayment::ApiUrl("/transaction/detail?reference=".$reference);

                $headers = array(
                    'Authorization' => 'Bearer '.$this->apikey,
                    'X-Plugin-Meta' => 'woocommerce|'.TripayPayment::$version,
                );

                $this->log("Verifying callback for ".$reference);

                $response = wp_remote_post($url, array(
                    'method' => 'GET',
                    'timeout' => 90,
                    'headers' => $headers,
                ));

                if (is_wp_error($response)) {
                    $this->log("WP Error: ".implode(", ", $response->get_error_messages()));
                    return false;
                }

                $response_body = wp_remote_retrieve_body($response);
                $response_code = wp_remote_retrieve_response_code($response);

                if (empty($response_body)) {
                    $this->log(__('TriPay Response was empty.', 'tripay'));
                    return false;
                }

                $this->log("Response: ".$response_body);

                $resp = json_decode($response_body);

                if ($resp->success === true && in_array(strtoupper($resp->data->status), $expectedStatuses)) {
                    return true;
                }

                return false;
            }

            public function log($message)
            {
                if (self::$log_enabled) {
                    if (empty(self::$log)) {
                        self::$log = new WC_Logger();
                    }
                    self::$log->add('tripay', $message);
                }
            }
        }
    }

    function add_tripay_gateway($methods)
    {
        foreach (TripayPayment::gateways() as $id => $property) {
            $methods[] = $property['class'];
        }

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_tripay_gateway');

    foreach (glob(dirname(__FILE__) . '/includes/gateways/*.php') as $filename) {
        include_once $filename;
    }
}

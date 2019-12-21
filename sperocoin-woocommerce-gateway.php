<?php
/**
 * Plugin Name: SPEROCOIN WooCommerce Gateway
 * Plugin URI:  https://github.com/Peppinux/dero-woocommerce-gateway
 * Description: SPEROCOIN Payment Gateway for WooCommerce
 * Version:     1.0.0
 * Author:      Peppinux
 * Author URI:  https://github.com/Peppinux
 * License:     MIT
 */

defined('ABSPATH') || exit;

define('SPEROCOIN_GATEWAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPEROCOIN_GATEWAY_PLUGIN_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', 'sperocoin_gateway_init');

require_once(SPEROCOIN_GATEWAY_PLUGIN_DIR . '/lib/sperocoin-wallet-rpc.php');
require_once(SPEROCOIN_GATEWAY_PLUGIN_DIR . '/lib/coingecko-api.php');

require_once(SPEROCOIN_GATEWAY_PLUGIN_DIR . '/lib/util/format-time.php');

function sperocoin_gateway_init() {
    if(!class_exists('WC_Payment_Gateway'))
        return;

    class SPEROCOIN_Gateway extends WC_Payment_Gateway {
        function __construct() {
            $this->id = 'sperocoin_gateway';
            $this->icon = apply_filters('woocommerce_gateway_icon', SPEROCOIN_GATEWAY_PLUGIN_URL . '/assets/img/sperocoin-icon.png');
            $this->has_fields = false;
            $this->method_title = __('SPEROCOIN Gateway', 'sperocoin_gateway');
            $this->method_description = __('SPEROCOIN Payment Gateway for WooCommerce', 'sperocoin_gateway');
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->enabled = $this->get_option('enabled');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->discount = $this->get_option('discount');
            $this->order_valid_time = $this->get_option('order_valid_time');
            $this->confirmations = $this->get_option('confirmations');
            $this->wallet_host = $this->get_option('wallet_host');
            $this->wallet_port = $this->get_option('wallet_port');
            $this->wallet_login_required = $this->get_option('wallet_login_required');
            $this->wallet_username = $this->get_option('wallet_username');
            $this->wallet_password = $this->get_option('wallet_password');

            SPEROCOIN_Wallet_RPC::setup($this->wallet_host, $this->wallet_port, $this->wallet_login_required, $this->wallet_username, $this->wallet_password);

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'sperocoin_gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable SPEROCOIN Gateway Payment', 'sperocoin_gateway'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title', 'sperocoin_gateway'),
                    'type' => 'text',
                    'description' => __('Payment title which the user sees during checkout.', 'sperocoin_gateway'),
                    'default' => __('SPEROCOIN Payment Gateway', 'sperocoin_gateway'),
                    'desc_tip' => true
                ),
                'description' => array(
                    'title' => __('Description', 'sperocoin_gateway'),
                    'type' => 'text',
                    'description' => __('Payment description which the user sees during checkout.', 'sperocoin_gateway'),
                    'default' => __('Pay securely and privately using SPEROCOIN. Payment details will be provided after checkout.', 'sperocoin_gateway'),
                    'desc_tip' => true
                ),
                'discount' => array(
                    'title' => __('Discount for using SPEROCOIN', 'sperocoin_gateway'),
                    'type' => __('number'),
                    'description' => __('Enter a percentage discount (e.g., 5 for 5%. Whole numbers only.) or leave this field empty if you do not wish to provide a discount.', 'sperocoin_gateway'),
                    'default' => '0',
                    'desc_tip' => __('Discount for making a payment with SPEROCOIN', 'sperocoin_gateway')
                ),
                'order_valid_time' => array(
                    'title' => __('Order valid time', 'sperocoin_gateway'),
                    'type' => __('number'),
                    'description' => __('Enter the number of seconds that the funds must be received in after the order is placed (e.g., 3600 = 1 hour).', 'sperocoin_gateway'),
                    'default' => '3600',
                    'desc_tip' => __('Amount of time the funds must be received in after the order is placed.', 'sperocoin_gateway')
                ),
                'confirmations' => array(
                    'title' => __('Number of confirmations', 'sperocoin_gateway'),
                    'type' => __('number'),
                    'description' => __('Enter the number of confirmations that the transaction must have to be valid. Each confirmation should take around 12 seconds. 10 confirmations = 2 minutes.', 'sperocoin_gateway'),
                    'default' => '10',
                    'desc_tip' => __('Number of confirmations that the transaction must have to be valid.', 'sperocoin_gateway')
                ),
                'wallet_host' => array(
                    'title' => __('SPEROCOIN Wallet RPC Hostname/IP Address', 'sperocoin_gateway'),
                    'type' => 'text',
                    'description' => __('Wallet RPC host used to connect to the wallet in order to verify transactions.', 'sperocoin_gateway'),
                    'default' => __('127.0.0.1', 'sperocoin_gateway'),
                    'desc_tip' => true
                ),
                'wallet_port' => array(
                    'title' => __('SPEROCOIN Wallet RPC Port', 'sperocoin_gateway'),
                    'type' => __('number'),
                    'description' => __('Wallet RPC port used to connect to the wallet in order to verify transactions.', 'sperocoin_gateway'),
                    'default' => 55681,
                    'desc_tip' => true
                ),
                'wallet_login_required' => array(
                    'title' => __('Wallet RPC requires Login', 'sperocoin_gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable Wallet RPC Login', 'sperocoin_gateway'),
                    'default' => 'no'
                ),
                'wallet_username' => array(
                    'title' => __('SPEROCOIN Wallet RPC Username', 'sperocoin_gateway'),
                    'type' => 'text',
                    'description' => __('Optional. Enter username only if wallet requires RPC Login and previous checkbox is checked.', 'sperocoin_gateway'),
                    'default' => __('', 'sperocoin_gateway'),
                    'desc_tip' => __('Wallet RPC username used to connect to the wallet in order to verify transactions.', 'sperocoin_gateway')
                ),
                'wallet_password' => array(
                    'title' => __('SPEROCOIN Wallet RPC Password', 'sperocoin_gateway'),
                    'type' => 'text',
                    'description' => __('Optional. Enter password only if wallet requires RPC Login and previous checkbox is checked.', 'sperocoin_gateway'),
                    'default' => __('', 'sperocoin_gateway'),
                    'desc_tip' => __('Wallet RPC password used to connect to the wallet in order to verify transactions.', 'sperocoin_gateway')
                )
            );
        }

        public function process_payment($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);

            $current_height = SPEROCOIN_Wallet_RPC::get_height();
            if(!is_int($current_height) || !($current_height >= 0)) {
                $error_message = 'Could not get chain height.';
                wc_add_notice(__('SPEROCOIN Payment Gateway error: ', 'sperocoin_gateway') . $error_message, 'error');
                return;
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'sperocoin_gateway_payments';

            $payment_id = '';
            do {
                $payment_id = bin2hex(openssl_random_pseudo_bytes(32));
                $query = $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE payment_id=%s", $payment_id);
                $payment_id_found = $wpdb->get_var($query);
            } while($payment_id_found);

            $integrated_address = SPEROCOIN_Wallet_RPC::make_integrated_address($payment_id);
            if(!is_string($integrated_address) || strlen($integrated_address) != 142) {
                $error_message = 'Could not make integrated address.';
                wc_add_notice(__('SPEROCOIN Payment Gateway error: ', 'sperocoin_gateway') . $error_message, 'error');
                return;
            }

            $currency = $order->get_currency();
            $supported_currencies = CoinGecko_API::get_supported_currencies();
            $exchange_rate = 0;
            if(in_array(strtolower($currency), $supported_currencies) || in_array(strtoupper($currency), $supported_currencies))
                $exchange_rate = CoinGecko_API::get_sperocoin_exchange_rate($currency);
            else {
                $error_message = 'Currency ' . $currency . ' not supported by CoinGecko API.';
                wc_add_notice(__('SPEROCOIN Payment Gateway error: ', 'sperocoin_gateway') . $error_message, 'error');
                return;
            }

            $fiat_total = $order->get_total();
            $sperocoin_total = $fiat_total / $exchange_rate;
            $discount = 0;
            if($this->discount > 0 && $this->discount <= 100) {
                $discount = $this->discount;
                $sperocoin_total = $sperocoin_total - ($sperocoin_total * $discount / 100);
            }

            $prepared_statement_params = array(
                $order_id,
                $currency,
                $exchange_rate,
                $fiat_total,
                $discount,
                $sperocoin_total
            );
            $query = $wpdb->prepare("INSERT INTO $table_name (order_id, currency, exchange_rate, fiat_total, discount_percentage, sperocoin_total) VALUES (%d, %s, %f, %f, %d, %f)", $prepared_statement_params);
            $wpdb->query($query);

            $prepared_statement_params = array(
                $payment_id,
                $integrated_address,
                $current_height,
                $order_id
            );
            $query = $wpdb->prepare("UPDATE $table_name SET payment_id=%s, integrated_address=%s, status='on-hold', creation_time=NOW(), height_at_creation=%d WHERE order_id=%d", $prepared_statement_params); // This separated query is needed for order re-paying. The previous INSERT INTO wouldn't update the values of an already existing order.
            $wpdb->query($query);

            $order->update_status('on-hold', __('Awaiting SPEROCOIN payment', 'sperocoin_gateway'));
            $order->reduce_order_stock();
            $woocommerce->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }

        public function thankyou_page($order_id) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'sperocoin_gateway_payments';
            $query = $wpdb->prepare("SELECT integrated_address, currency, exchange_rate, fiat_total, discount_percentage, sperocoin_total, status, received_payment_txid, TIMESTAMPDIFF(SECOND, creation_time, NOW()) as seconds_passed FROM $table_name WHERE order_id=%d", $order_id);
            $result = $wpdb->get_results($query)[0];

            $status = ucfirst($result->status);
            if($result->status == 'on-hold' || $result->status == 'pending') {
                $remaining_seconds = $this->order_valid_time - $result->seconds_passed;
                $time_format = format_time($remaining_seconds);
                if($remaining_seconds > 0)
                    $status = "Awaiting payment. Your order will expire in <i>$time_format</i> if payment is not received. Refresh this page to get updated status.";
                else
                    $status = "Your order is about to expire. Place another one to complete your purchase.";
            } else if($result->status == 'expired')
                $status = 'Expired. Place another order to complete your purchase.';

            $discount_section = "";
            if((int)$result->discount_percentage > 0)
                $discount_section = "<li class='woocommerce-order-overview__order order'>
                                        Discount for paying with SPEROCOIN: <strong>$result->discount_percentage%</strong>
                                    </li>";

            $integrated_address_section = "";
            if($result->status == 'on-hold' || $result->status == 'pending')
                $integrated_address_section = "<li class='woocommerce-order-overview__order order'>
                                                    <span class='detail-title'>Pay to (integrated address):</span> <span class='detail-content'><strong><span id='sperocoin-integrated-address'>$result->integrated_address</span></strong></span>
                                                    <button class='clipboard-button' title='Copy to clipboard' data-clipboard-target='#sperocoin-integrated-address'><svg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 512 512' version='1'><path d='M504 118c-6-6-12-8-20-8H365c-11 0-23 3-36 11V27c0-7-3-14-8-19s-12-8-20-8H183c-8 0-16 2-25 6-10 4-17 8-22 13L19 136c-5 5-9 12-13 22-4 9-6 17-6 25v192c0 7 3 14 8 19s12 8 19 8h156v82c0 8 2 14 8 20 5 5 12 8 19 8h274c8 0 14-3 20-8 5-6 8-12 8-20V137c0-8-3-14-8-19zm-175 52v86h-85l85-86zM146 61v85H61l85-85zm56 185c-5 5-10 12-14 21-3 9-5 18-5 25v73H37V183h118c8 0 14-3 20-8 5-6 8-12 8-20V37h109v118l-90 91zm273 229H219V292h119c8 0 14-2 19-8 6-5 8-11 8-19V146h110v329z'/></svg></button>
                                                    <button class='qrcode-button' title='Show QR Code' onclick='toggleQRCode()'><svg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 512 512' version='1'><path d='M0 512h233V279H0zm47-186h139v139H47z'/><path d='M93 372h47v47H93zm279 93h47v47h-47zm93 0h47v47h-47z'/><path d='M465 326h-46v-47H279v233h47V372h46v47h140V279h-47zM0 233h233V0H0zM47 47h139v139H47z'/><path d='M93 93h47v47H93zM279 0v233h233V0zm186 186H326V47h139z'/><path d='M372 93h47v47h-47z'/></svg></button>
                                                    <div id='address-qrcode'></div>
                                                </li>";

            $txid_section = "";
            if($result->received_payment_txid != null)
                $txid_section = "<li class='woocommerce-order-overview__order order'>
                                    Payment TXID: <strong>$result->received_payment_txid</strong>
                                </li>";

            $instructions = "<style>
                                .detail-title {
                                    display: block;
                                }
                                
                                .detail-content {
                                    display: inline-block;
                                    word-break: break-word;
                                }
                                
                                .clipboard-button, .qrcode-button {
                                    display: inline-block;
                                    margin-left: 10px;
                                    font-size: 0.5em;
                                    border-radius: 10%;
                                }

                                #address-qrcode {
                                    display: none;
                                    margin-top: 20px;
                                }
                            </style>
                            <section class='woocommerce-order-details'>
                                <h2 class='woocommerce-order-details__title'>SPEROCOIN Payment Details</h2>
                                <ul class='woocommerce-order-overview woocommerce-thankyou-order-details order_details'>
                                    <li class='woocommerce-order-overview__order order'>
                                        Status: <strong>$status</strong>
                                    </li>
                                    <li class='woocommerce-order-overview__order order'>
                                        Fiat total: <strong>$result->fiat_total $result->currency</strong>
                                    </li>
                                    <li class='woocommerce-order-overview__order order'>
                                        Exchange rate: <strong>1 SPEROCOIN = $result->exchange_rate $result->currency</strong>
                                    </li>
                                    $discount_section
                                    <li class='woocommerce-order-overview__order order'>
                                        <span class='detail-title'>Total:</span> <span class='detail-content'><strong><span id='sperocoin-total'>$result->sperocoin_total</span> SPEROCOIN</strong></span>
                                        <button class='clipboard-button' title='Copy to clipboard' data-clipboard-target='#sperocoin-total'><svg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 512 512' version='1'><path d='M504 118c-6-6-12-8-20-8H365c-11 0-23 3-36 11V27c0-7-3-14-8-19s-12-8-20-8H183c-8 0-16 2-25 6-10 4-17 8-22 13L19 136c-5 5-9 12-13 22-4 9-6 17-6 25v192c0 7 3 14 8 19s12 8 19 8h156v82c0 8 2 14 8 20 5 5 12 8 19 8h274c8 0 14-3 20-8 5-6 8-12 8-20V137c0-8-3-14-8-19zm-175 52v86h-85l85-86zM146 61v85H61l85-85zm56 185c-5 5-10 12-14 21-3 9-5 18-5 25v73H37V183h118c8 0 14-3 20-8 5-6 8-12 8-20V37h109v118l-90 91zm273 229H219V292h119c8 0 14-2 19-8 6-5 8-11 8-19V146h110v329z'/></svg></button>
                                    </li>
                                    $integrated_address_section
                                    $txid_section
                                </ul>
                            </section>
                            <script type='text/javascript'>
                                jQuery(document).ready(function() {
                                    // Clipboard
                                    var clipboardButtons = document.querySelectorAll('button.clipboard-button');
                                    var clipboard = new ClipboardJS(clipboardButtons);

                                    clipboard.on('success', function(e) {
                                        console.log('Text copied to clipboard.');
                                    });
                                    clipboard.on('error', function(e) {
                                        console.log('Error occured while trying to copy text to clipboard.');
                                    });

                                    // QR Code
                                    new QRCode(document.getElementById('address-qrcode'), '$result->integrated_address');
                                });
                                function toggleQRCode() {
                                    jQuery('#address-qrcode').toggle();
                                }
                            </script>";
            echo $instructions;
        }

        public function email_instructions($order, $sent_to_admin, $plain_text = false) {
            if(!$sent_to_admin && $order->get_payment_method() === $this->id && $order->has_status('on-hold')) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'sperocoin_gateway_payments';
                $query = $wpdb->prepare("SELECT integrated_address, sperocoin_total, TIMESTAMPDIFF(SECOND, creation_time, NOW()) as seconds_passed FROM $table_name WHERE order_id=%d", $order->get_id());
                $result = $wpdb->get_results($query)[0];

                $remaining_seconds = $this->order_valid_time - $result->seconds_passed;
                $time_format = format_time($remaining_seconds);

                echo wp_kses_post(wpautop(wptexturize('You need to send <strong>' . $result->sperocoin_total . ' SPEROCOIN</strong> to the following address within <strong>' . $time_format . '</strong> to complete your purchase.<br>SPEROCOIN Payment Integrated Address: <strong>' . $result->integrated_address . '</strong><br>More details can be found in the order page.' . PHP_EOL)));
            }
        }
    }

    function sperocoin_gateway($methods) {
        $methods[] = 'SPEROCOIN_Gateway'; 
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'sperocoin_gateway');
}

function sperocoin_cron_add_one_minute($schedules) {
    $schedules['one_minute'] = array(
        'interval' => 60,
        'display' => __('Once every minute')
    );
    return $schedules;
}
add_filter('cron_schedules', 'sperocoin_cron_add_one_minute');

function check_sperocoin_payments() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sperocoin_gateway_payments';
    $results = $wpdb->get_results("SELECT order_id, payment_id, sperocoin_total, height_at_creation, TIMESTAMPDIFF(SECOND, creation_time, NOW()) as seconds_passed FROM $table_name WHERE status='on-hold' OR status='pending'");

    global $woocommerce;
    $wc_gateways = new WC_Payment_Gateways();
    $sperocoin_gateway = $wc_gateways->get_available_payment_gateways()['sperocoin_gateway'];
    $order_valid_time = $sperocoin_gateway->get_option('order_valid_time');
    $required_confirmations = $sperocoin_gateway->get_option('confirmations');

    foreach($results as $result) {
        $order = new WC_Order($result->order_id);

        if($result->seconds_passed > $order_valid_time) {
            if($order->get_status() == "on-hold" || $order->get_status() == "pending")
                $order->update_status('failed', __('Payment not received in time', 'sperocoin_gateway'));

            $query = $wpdb->prepare("UPDATE $table_name SET status=%s WHERE order_id=%d", array('expired', $result->order_id));
            $wpdb->query($query);
        } else {
            $payments = SPEROCOIN_Wallet_RPC::get_bulk_payments($result->payment_id, $result->height_at_creation);
            if($payments != null) {
                $sperocoin_total = $result->sperocoin_total * pow(10, 12); // Convert SPEROCOIN got from the db from float to int in order to make it comparable to the value returned by wallet RPC.
                $payment = null;
                foreach($payments as $p) {
                    if(json_encode($p['amount']) >= $sperocoin_total) {
                        $payment = $p;
                        break;
                    }
                }

                $txid = null;

                if($payment != null) {
                    $payment_confirmations = SPEROCOIN_Wallet_RPC::get_height() - $result->height_at_creation;
                    $txid = $payment['tx_hash'];

                    if($payment_confirmations >= $required_confirmations)
                        $order->payment_complete();
                } else {
                    if($order->get_status() == "on-hold" || $order->get_status() == "pending")
                        $order->update_status('failed', __('Amount of SPEROCOIN sent not matching with order total.', 'sperocoin_gateway'));
                }
                $query = $wpdb->prepare("UPDATE $table_name SET status=%s, received_payment_txid=%s WHERE order_id=%d", array($order->get_status(), $txid, $result->order_id));
                $wpdb->query($query);
            }
        }
    }
}
add_action('check_sperocoin_payments_cron', 'check_sperocoin_payments');

function sperocoin_activate() {
    global $wpdb;
    require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'sperocoin_gateway_payments';
    if($wpdb->get_var("show tables like $table_name") != $table_name) {
        $sql = "CREATE TABLE $table_name (
                    order_id BIGINT UNSIGNED NOT NULL,
                    payment_id VARCHAR(128) NOT NULL,
                    integrated_address VARCHAR(256) NOT NULL,
                    currency VARCHAR(8) NOT NULL,
                    exchange_rate DOUBLE UNSIGNED NOT NULL,
                    fiat_total DOUBLE UNSIGNED NOT NULL,
                    discount_percentage TINYINT UNSIGNED NOT NULL,
                    sperocoin_total DOUBLE UNSIGNED NOT NULL,
                    status VARCHAR(16) NOT NULL,
                    height_at_creation BIGINT NOT NULL,
                    creation_time TIMESTAMP NOT NULL DEFAULT NOW(),
                    received_payment_txid VARCHAR(256) NULL DEFAULT NULL,
                    PRIMARY KEY (order_id)
               ) $charset_collate;";
        dbDelta($sql);
    }

    if(!wp_next_scheduled('check_sperocoin_payments_cron'))
        wp_schedule_event(time(), 'one_minute', 'check_sperocoin_payments_cron');
}
register_activation_hook(__FILE__, 'sperocoin_activate');

function sperocoin_deactivate() {
    wp_clear_scheduled_hook('check_sperocoin_payments_cron');
}
register_deactivation_hook(__FILE__, 'sperocoin_deactivate');

function sperocoin_enqueue_scripts() {
    wp_enqueue_script('sperocoin-qrcode-js', SPEROCOIN_GATEWAY_PLUGIN_URL . 'assets/js/qrcode.min.js');
    wp_enqueue_script('sperocoin-clipboard-js', SPEROCOIN_GATEWAY_PLUGIN_URL . 'assets/js/clipboard.min.js');
}
add_action('wp_enqueue_scripts', 'sperocoin_enqueue_scripts');

function sperocoin_accepted_here_shortcode() {
    return '<img src="' . SPEROCOIN_GATEWAY_PLUGIN_URL . 'assets/img/sperocoin-accepted-here.png" />';
}
add_shortcode('sperocoin-accepted-here', 'sperocoin_accepted_here_shortcode');
?>

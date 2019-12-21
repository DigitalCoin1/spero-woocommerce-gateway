<?php
defined('ABSPATH') || exit;

require_once(SPEROCOIN_GATEWAY_PLUGIN_DIR . '/lib/util/admin-error.php');

class SPEROCOIN_Wallet_RPC {
    // Default values that will be edited once the setup function is called inside the SPEROCOIN_Gateway constructor
    private static $host = '127.0.0.1';
    private static $port = 20209;
    private static $login_required = false;
    private static $username = '';
    private static $password = '';
    private static $url = '127.0.0.1:20209/json_rpc';

    public static function setup($host, $port, $login_required, $username, $password) {
        self::$host = $host;
        self::$port = $port;
        self::$login_required = $login_required == 'yes';
        self::$username = $username;
        self::$password = $password;
        self::$url = $host . ':' . $port . '/json_rpc';

        if(extension_loaded('curl') === false) {
            admin_error('cURL extension not loaded.');
        }

        if(extension_loaded('json') === false) {
            admin_error('JSON extension not loaded.');
        }
    }

    private static function request($method, $params = null) {
        static $request_id = 0;
        $request_id++;

        $payload = array(
            'jsonrpc' => '2.0',
            'id' => (string)$request_id,
            'method' => (string)$method
        );

        if($params != null) {
            $payload['params'] = $params;
        }

        $json_payload = json_encode($payload);

        $ch = curl_init();

        if(!$ch)
            throw new RuntimeException('Could\'t initialize a cURL session');

        curl_setopt($ch, CURLOPT_URL, self::$url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);

        if(self::$login_required) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, self::$username . ':' . self::$password);
        }
        
        $response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($http_code != 200)
            admin_error('SPEROCOIN Wallet RPC HTTP Response Code: ' . $http_code);

        if(curl_errno($ch) > 0)
            admin_error('Failed to connect to SPEROCOIN Wallet RPC at ' . self::$host . ':' .self::$port);

        curl_close($ch);

        return json_decode($response, true);
    }

    public static function get_height() {
        return (int)self::request('getheight')['result']['height'];
    }

    public static function make_integrated_address($payment_id) {
        return self::request('make_integrated_address', array('payment_id' => $payment_id))['result']['integrated_address'];
    }

    public static function get_bulk_payments($payment_id, $min_block_height = 0) {
        $result = self::request('get_bulk_payments', array('payment_ids' => array($payment_id), 'min_block_height' => (int)$min_block_height))['result'];
        if(empty($result))
            return null;
        return $result['payments'];
    }
}
?>

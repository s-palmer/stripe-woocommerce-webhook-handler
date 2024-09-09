<?php
// First, let's load the Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins');
}

// Mock Stripe library
class Stripe {
    public static function setApiKey($key) {}
}

class StripeClient {
    public $checkout;

    public function __construct($apiKey) {
        $this->checkout = new CheckoutSessions();
    }
}

class CheckoutSessions {
    public function retrieve($id, $params = null) {
        $session = new CheckoutSession();
        $session->id = $id;
        return $session;
    }
}

class CheckoutSession {
    public $id = 'test_session_id';
    public $amount_total = 9999;
    public $currency = 'usd';
    public $customer;
    public $customer_details;
    public $line_items;
    public $invoice;

    public function __construct() {
        $this->customer = new \stdClass();
        $this->customer->id = 'test_customer_id';
        $this->customer_details = new \stdClass();
        $this->customer_details->email = 'test@example.com';
        $this->customer_details->name = 'Test User';
        $this->line_items = new \stdClass();
        $this->line_items->data = [];
        $this->invoice = 'test_invoice_id';
    }
}

// Mock WordPress functions
function add_action() {}
function register_rest_route() {}
function get_option($option) {
    return 'test_value';
}
function update_post_meta() {}

// Mock WooCommerce functions
function wc_get_orders() {
    global $wc_orders;
    return $wc_orders ?? [];
}
function wc_get_order($order_id) {
    return new WC_Order($order_id);
}
function wc_create_order() {
    $order = new WC_Order();
    global $wc_orders;
    $wc_orders[] = $order;
    return $order;
}
function wc_get_order_notes() {
    return [];
}

// Mock WooCommerce classes
class WC_Order {
    private $data = [];
    private $id;

    public function __construct() {
        $this->id = uniqid();
    }

    public function get_id() { return $this->id; }
    public function set_total($total) { $this->data['total'] = $total; }
    public function set_currency($currency) { $this->data['currency'] = $currency; }
    public function set_payment_method($method) { $this->data['payment_method'] = $method; }
    public function set_payment_method_title($title) { $this->data['payment_method_title'] = $title; }
    public function set_customer_id($id) { $this->data['customer_id'] = $id; }
    public function set_billing_email($email) { $this->data['billing_email'] = $email; }
    public function set_billing_first_name($name) { $this->data['billing_first_name'] = $name; }
    public function set_billing_last_name($name) { $this->data['billing_last_name'] = $name; }
    public function add_order_note($note) { $this->data['notes'][] = $note; }
    public function update_status($status, $note = '') { $this->data['status'] = $status; }
    public function save() {}
    public function get_meta($key) { return isset($this->data[$key]) ? $this->data[$key] : ''; }
    public function add_meta_data($key, $value) { $this->data[$key] = $value; }
    public function add_product($product, $quantity = 1) {}
    public function add_item($item) {}
    public function get_total() { return isset($this->data['total']) ? $this->data['total'] : '99.99'; }
    public function get_currency() { return isset($this->data['currency']) ? $this->data['currency'] : 'USD'; }
    public function get_status() { return isset($this->data['status']) ? $this->data['status'] : 'processing'; }
    public function get_shipping_country() { return 'US'; }
    public function set_taxes($taxes) {}
}

class WC_Order_Item_Shipping {
    public function set_method_title($title) {}
    public function set_total($total) {}
}

class WP_REST_Response {
    private $data;
    private $status;

    public function __construct($data, $status) {
        $this->data = $data;
        $this->status = $status;
    }

    public function get_data() {
        return $this->data;
    }

    public function get_status() {
        return $this->status;
    }
}

class WP_Error {
    private $code;
    private $message;
    private $data;

    public function __construct($code, $message, $data) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
}

// Mock global $wpdb
global $wpdb;
$wpdb = new class {
    public function get_var($query) {
        return null;
    }
    public function prepare($query, ...$args) {
        return vsprintf(str_replace('%s', "'%s'", $query), $args);
    }
};

// Custom error logging function for tests
// function error_log($message) {
//     echo "Test Error Log: $message\n";
// }

// Mock do_action function
function do_action() {}

// Initialize global $wc_orders array
global $wc_orders;
$wc_orders = [];
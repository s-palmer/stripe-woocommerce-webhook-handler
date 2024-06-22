<?php
/**
 * Plugin Name: Minimal Stripe Webhook Handler for WooCommerce
 * Description: Handles Stripe webhooks for WooCommerce
 * Version: 0.2
 * Author: Sergei Palmer
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Include Stripe PHP library
require_once(WP_PLUGIN_DIR . '/stripe-php/init.php');

// Register the webhook endpoint
add_action('rest_api_init', function () {
    register_rest_route('custom-stripe-webhook/v1', '/handle', array(
        'methods' => 'POST',
        'callback' => 'handle_stripe_webhook',
        'permission_callback' => '__return_true'
    ));
});

function handle_stripe_webhook(WP_REST_Request $request) {
    $payload = $request->get_body();
    $event = json_decode($payload, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('json_error', 'Invalid JSON payload', array('status' => 400));
    }

    if (is_stripe_test_mode()) {
        error_log('Stripe webhook received in test mode: ' . $event['type']);
    }

    switch ($event['type']) {
        case 'checkout.session.completed':
            handle_checkout_session_completed($event['data']['object']);
            break;
        case 'invoice.paid':
            handle_invoice_paid($event['data']['object']);
            break;
        default:
            error_log('Unhandled event type: ' . $event['type']);
    }

    return new WP_REST_Response('Webhook handled', 200);
}

function handle_checkout_session_completed($session) {
    $stripe_secret_key = get_option('stripe_secret_key');
    $stripe = new \Stripe\StripeClient($stripe_secret_key);

    $full_session = $stripe->checkout->sessions->retrieve($session['id'], ['expand' => ['line_items', 'customer']]);

    $order = wc_create_order();

    // Add line items to the order
    foreach ($full_session->line_items->data as $item) {
        $product_id = get_product_id_from_stripe($item->price->product);
        if ($product_id) {
            $product = wc_get_product($product_id);
            $order->add_product($product, $item->quantity);
        } else {
            $order->add_item(array(
                'name' => $item->description,
                'qty' => $item->quantity,
                'total' => $item->amount_total / 100,
            ));
        }
    }

    // Set order details
    $order->set_total($full_session->amount_total / 100);
    $order->set_currency(strtoupper($full_session->currency));
    $order->set_payment_method('stripe');
    $order->set_payment_method_title('Stripe');
    $order->set_customer_id($full_session->customer->id);

    // Set billing details
    if ($full_session->customer_details) {
        $order->set_billing_first_name($full_session->customer_details->name);
        $order->set_billing_email($full_session->customer_details->email);

        if (isset($full_session->customer_details->address)) {
            $billing_address = $full_session->customer_details->address;
            $order->set_billing_address_1($billing_address->line1);
            $order->set_billing_address_2($billing_address->line2);
            $order->set_billing_city($billing_address->city);
            $order->set_billing_state($billing_address->state);
            $order->set_billing_postcode($billing_address->postal_code);
            $order->set_billing_country($billing_address->country);
        }
    }

    // Set shipping details
    if ($full_session->shipping) {
        $shipping_details = $full_session->shipping->address;
        $order->set_shipping_first_name($full_session->shipping->name);
        $order->set_shipping_address_1($shipping_details->line1);
        $order->set_shipping_address_2($shipping_details->line2);
        $order->set_shipping_city($shipping_details->city);
        $order->set_shipping_state($shipping_details->state);
        $order->set_shipping_postcode($shipping_details->postal_code);
        $order->set_shipping_country($shipping_details->country);
    } elseif (isset($full_session->customer_details->address)) {
        // If no shipping details but billing address exists, use billing as shipping
        $billing_address = $full_session->customer_details->address;
        $order->set_shipping_first_name($full_session->customer_details->name);
        $order->set_shipping_address_1($billing_address->line1);
        $order->set_shipping_address_2($billing_address->line2);
        $order->set_shipping_city($billing_address->city);
        $order->set_shipping_state($billing_address->state);
        $order->set_shipping_postcode($billing_address->postal_code);
        $order->set_shipping_country($billing_address->country);
    }

    // Add order notes
    $order->add_order_note('Order created from Stripe Checkout Session ' . $full_session->id);

    // Set order status to processing
    $order->update_status('processing', 'Order paid via Stripe');

    // Save the order
    $order->save();

    // Trigger the WooCommerce order created action
    do_action('woocommerce_new_order', $order->get_id());

    error_log('WooCommerce order created from Stripe session: ' . $full_session->id);
}

function handle_invoice_paid($invoice) {
    if ($invoice['billing_reason'] != 'subscription_cycle') {
        return;
    }

    // Create a new WooCommerce order
    $order = wc_create_order();

    // Add line items to the order
    foreach ($invoice['lines']['data'] as $line) {
        $product_id = get_product_id_from_stripe($line['price']['product']);
        if ($product_id) {
            $product = wc_get_product($product_id);
            $order->add_product($product, $line['quantity']);
        } else {
            // If product not found, add a line item with available info
            $order->add_item(array(
                'name' => $line['description'],
                'qty' => $line['quantity'],
                'total' => $line['amount'] / 100, // Convert cents to dollars/pounds
            ));
        }
    }

    // Set order details
    $order->set_total($invoice['amount_paid'] / 100);
    $order->set_currency(strtoupper($invoice['currency']));
    $order->set_payment_method('stripe');
    $order->set_payment_method_title('Stripe Subscription');

    // Add order notes
    $order->add_order_note('Order created from Stripe invoice ' . $invoice['id']);

    // Set order status to processing
    $order->update_status('processing', 'Subscription renewed via Stripe');

    // Save the order
    $order->save();

    // Trigger the WooCommerce order created action
    do_action('woocommerce_new_order', $order->get_id());

    error_log('WooCommerce order created from Stripe invoice: ' . $invoice['id']);
}

function get_product_id_from_stripe($stripe_product_id) {
    $products = wc_get_products(array(
        'meta_key' => '_stripe_product_id',
        'meta_value' => $stripe_product_id,
        'limit' => 1,
    ));

    return !empty($products) ? $products[0]->get_id() : null;
}

function is_stripe_test_mode() {
    return get_option('stripe_mode', 'test') === 'test';
}

// Add Stripe Product ID field to WooCommerce products
add_action('woocommerce_product_options_general_product_data', 'add_stripe_product_id_field');
function add_stripe_product_id_field() {
    woocommerce_wp_text_input(
        array(
            'id' => '_stripe_product_id',
            'label' => __('Stripe Product ID', 'woocommerce'),
            'description' => __('Enter the Stripe Product ID for this product.', 'woocommerce'),
            'desc_tip' => true,
        )
    );
}

// Save the Stripe Product ID
add_action('woocommerce_process_product_meta', 'save_stripe_product_id_field');
function save_stripe_product_id_field($post_id) {
    $stripe_product_id = isset($_POST['_stripe_product_id']) ? sanitize_text_field($_POST['_stripe_product_id']) : '';
    update_post_meta($post_id, '_stripe_product_id', $stripe_product_id);
}

// Add menu item
add_action('admin_menu', 'stripe_webhook_menu');

function stripe_webhook_menu() {
    add_options_page('Stripe Webhook Settings', 'Stripe Webhook', 'manage_options', 'stripe-webhook-settings', 'stripe_webhook_settings_page');
}

// Create the settings page
function stripe_webhook_settings_page() {
    ?>
    <div class="wrap">
        <h1>Stripe Webhook Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('stripe_webhook_options');
            do_settings_sections('stripe-webhook-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', 'stripe_webhook_settings_init');

function stripe_webhook_settings_init() {
    register_setting('stripe_webhook_options', 'stripe_secret_key');
    register_setting('stripe_webhook_options', 'stripe_mode');

    add_settings_section('stripe_webhook_section', 'Stripe API Settings', 'stripe_webhook_section_callback', 'stripe-webhook-settings');

    add_settings_field('stripe_secret_key', 'Stripe Secret Key', 'stripe_secret_key_callback', 'stripe-webhook-settings', 'stripe_webhook_section');
    add_settings_field('stripe_mode', 'Stripe Mode', 'stripe_mode_callback', 'stripe-webhook-settings', 'stripe_webhook_section');
}

function stripe_webhook_section_callback() {
    echo '<p>Enter your Stripe API settings below:</p>';
}

function stripe_secret_key_callback() {
    $secret_key = get_option('stripe_secret_key');
    echo '<input type="text" id="stripe_secret_key" name="stripe_secret_key" value="' . esc_attr($secret_key) . '" style="width: 300px;" />';
}

function stripe_mode_callback() {
    $mode = get_option('stripe_mode', 'test');
    echo '<select id="stripe_mode" name="stripe_mode">';
    echo '<option value="test" ' . selected($mode, 'test', false) . '>Test</option>';
    echo '<option value="live" ' . selected($mode, 'live', false) . '>Live</option>';
    echo '</select>';
}
?>

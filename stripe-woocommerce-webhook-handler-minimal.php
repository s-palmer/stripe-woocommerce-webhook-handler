<?php
/**
 * Plugin Name: Minimal Stripe Webhook Handler for WooCommerce
 * Description: Handles Stripe webhooks for WooCommerce
 * Version: 0.1
 * Author: Sergei Palmer
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

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
    $order = wc_create_order();

    // Get line items from the Stripe Checkout Session
    $line_items = \Stripe\Checkout\Session::retrieve([
        'id' => $session['id'],
        'expand' => ['line_items'],
    ])->line_items;

    foreach ($line_items->data as $item) {
        $product_id = get_product_id_from_stripe($item->price->product);
        if ($product_id) {
            $product = wc_get_product($product_id);
            $order->add_product($product, $item->quantity);

            // If it's a subscription product, add the corresponding number of single products
            $subscription_quantity = get_subscription_quantity($product_id);
            if ($subscription_quantity > 0) {
                $single_product_id = get_single_product_id();
                $single_product = wc_get_product($single_product_id);
                $order->add_product($single_product, $subscription_quantity * $item->quantity);
            }
        }
    }

    $order->set_total($session['amount_total'] / 100);
    $order->set_currency(strtoupper($session['currency']));
    $order->set_payment_method('stripe');
    $order->set_payment_method_title('Stripe');
    $order->set_customer_id($session['customer']);

    $order->save();

    do_action('woocommerce_new_order', $order->get_id());
}

function handle_invoice_paid($invoice) {
    if ($invoice['billing_reason'] != 'subscription_cycle') {
        return;
    }

    $order = wc_create_order();

    foreach ($invoice['lines']['data'] as $line) {
        $product_id = get_product_id_from_stripe($line['price']['product']);
        if ($product_id) {
            $product = wc_get_product($product_id);
            $order->add_product($product, $line['quantity']);

            // Add the corresponding number of single products
            $subscription_quantity = get_subscription_quantity($product_id);
            if ($subscription_quantity > 0) {
                $single_product_id = get_single_product_id();
                $single_product = wc_get_product($single_product_id);
                $order->add_product($single_product, $subscription_quantity * $line['quantity']);
            }
        }
    }

    $order->set_total($invoice['amount_paid'] / 100);
    $order->set_currency(strtoupper($invoice['currency']));
    $order->set_payment_method('stripe');
    $order->set_payment_method_title('Stripe Subscription');

    $order->save();

    do_action('woocommerce_new_order', $order->get_id());
}

function get_product_id_from_stripe($stripe_product_id) {
    $products = wc_get_products(array(
        'meta_key' => '_stripe_product_id',
        'meta_value' => $stripe_product_id,
        'limit' => 1,
    ));

    return !empty($products) ? $products[0]->get_id() : null;
}

function get_subscription_quantity($product_id) {
    return get_post_meta($product_id, '_subscription_quantity', true) ?: 0;
}

function get_single_product_id() {
    return get_option('single_product_id');
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

    woocommerce_wp_text_input(
        array(
            'id' => '_subscription_quantity',
            'label' => __('Subscription Quantity', 'woocommerce'),
            'description' => __('Enter the number of single products in this subscription.', 'woocommerce'),
            'desc_tip' => true,
            'type' => 'number',
        )
    );
}

// Save the Stripe Product ID and Subscription Quantity
add_action('woocommerce_process_product_meta', 'save_stripe_product_id_field');
function save_stripe_product_id_field($post_id) {
    $stripe_product_id = isset($_POST['_stripe_product_id']) ? sanitize_text_field($_POST['_stripe_product_id']) : '';
    update_post_meta($post_id, '_stripe_product_id', $stripe_product_id);

    $subscription_quantity = isset($_POST['_subscription_quantity']) ? intval($_POST['_subscription_quantity']) : 0;
    update_post_meta($post_id, '_subscription_quantity', $subscription_quantity);
}
?>

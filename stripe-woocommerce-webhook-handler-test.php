<?php
// This is a testable version of your plugin file

// Comment out or remove the following line:
// require_once(WP_PLUGIN_DIR . '/stripe-php/init.php');

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
            return handle_checkout_session_completed($event['data']['object']);
        case 'invoice.paid':
            return handle_invoice_paid($event['data']['object']);
        default:
            error_log('Unhandled event type: ' . $event['type']);
    }

    return new WP_REST_Response('Webhook handled', 200);
}

function handle_checkout_session_completed($session) {
    try {
        $stripe_secret_key = get_option('stripe_secret_key');
        $stripe = new StripeClient($stripe_secret_key);

        $full_session = $stripe->checkout->sessions->retrieve($session['id'], ['expand' => ['line_items', 'customer']]);

        // Create or update WooCommerce order
        $order_id = get_order_id_by_stripe_invoice($full_session->invoice);
        if (!$order_id) {
            $order = wc_create_order();
            $order_id = $order->get_id();
        } else {
            $order = wc_get_order($order_id);
        }

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
                    'tax_class' => '',
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
        if (isset($full_session->customer_details)) {
            $order->set_billing_email($full_session->customer_details->email);
            $name_parts = explode(' ', $full_session->customer_details->name, 2);
            $order->set_billing_first_name($name_parts[0]);
            $order->set_billing_last_name(isset($name_parts[1]) ? $name_parts[1] : '');

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
        if (isset($full_session->shipping)) {
            $shipping_details = $full_session->shipping->address;
            $name_parts = explode(' ', $full_session->shipping->name, 2);
            $order->set_shipping_first_name($name_parts[0]);
            $order->set_shipping_last_name(isset($name_parts[1]) ? $name_parts[1] : '');
            $order->set_shipping_address_1($shipping_details->line1);
            $order->set_shipping_address_2($shipping_details->line2);
            $order->set_shipping_city($shipping_details->city);
            $order->set_shipping_state($shipping_details->state);
            $order->set_shipping_postcode($shipping_details->postal_code);
            $order->set_shipping_country($shipping_details->country);
        }

        // Handle international shipping and tax
        $country_code = $order->get_shipping_country();
        if ($country_code && $country_code !== 'GB') {
            $international_shipping_cost = get_option('international_shipping_cost', 0);
            if ($international_shipping_cost > 0) {
                $item = new WC_Order_Item_Shipping();
                $item->set_method_title('International Shipping');
                $item->set_total($international_shipping_cost);
                $order->add_item($item);
            }
            $order->set_taxes(array());
        }

        // Add order notes
        $order->add_order_note('Order updated from Stripe Checkout Session ' . $full_session->id);

        // Set order status to processing
        $order->update_status('processing', 'Order paid via Stripe');

        // Store the Stripe invoice ID
        $order->add_meta_data('_stripe_invoice_id', $full_session->invoice);

        // Save the order
        $order->save();

        // Trigger the WooCommerce new order actions
        do_action('woocommerce_new_order', $order->get_id(), $order);

        error_log('WooCommerce order processed from Stripe session: ' . $full_session->id);

        return new WP_REST_Response('Order processed successfully', 200);
    } catch (Exception $e) {
        error_log('Error processing Stripe webhook: ' . $e->getMessage());
        return new WP_Error('order_processing_failed', 'Failed to process order: ' . $e->getMessage(), array('status' => 500));
    }
}

function handle_invoice_paid($invoice) {
    try {
        $invoice_id = $invoice['id'];
        $order_id = get_order_id_by_stripe_invoice($invoice_id);
        
        if (!$order_id) {
            // Create a new order if it doesn't exist
            $order = wc_create_order();
            $order_id = $order->get_id();
        } else {
            $order = wc_get_order($order_id);
        }

        // Add line items to the order
        foreach ($invoice['lines']['data'] as $item) {
            $product_id = get_product_id_from_stripe($item['price']['product']);
            if ($product_id) {
                $product = wc_get_product($product_id);
                $order->add_product($product, $item['quantity']);
            } else {
                $order->add_item(array(
                    'name' => $item['description'],
                    'qty' => $item['quantity'],
                    'total' => $item['amount'] / 100,
                    'tax_class' => '',
                ));
            }
        }

        // Set order details
        $order->set_total($invoice['amount_paid'] / 100);
        $order->set_currency(strtoupper($invoice['currency']));
        $order->set_payment_method('stripe');
        $order->set_payment_method_title('Stripe');
        $order->set_customer_id($invoice['customer']);

        // Set billing details
        if (isset($invoice['customer_name'])) {
            $name_parts = explode(' ', $invoice['customer_name'], 2);
            $order->set_billing_first_name($name_parts[0]);
            $order->set_billing_last_name(isset($name_parts[1]) ? $name_parts[1] : '');
        }
        $order->set_billing_email($invoice['customer_email']);
        if (isset($invoice['customer_address'])) {
            $order->set_billing_address_1($invoice['customer_address']['line1']);
            $order->set_billing_address_2($invoice['customer_address']['line2']);
            $order->set_billing_city($invoice['customer_address']['city']);
            $order->set_billing_state($invoice['customer_address']['state']);
            $order->set_billing_postcode($invoice['customer_address']['postal_code']);
            $order->set_billing_country($invoice['customer_address']['country']);
        }

        // Set shipping details if available
        if (isset($invoice['customer_shipping'])) {
            $shipping = $invoice['customer_shipping'];
            $name_parts = explode(' ', $shipping['name'], 2);
            $order->set_shipping_first_name($name_parts[0]);
            $order->set_shipping_last_name(isset($name_parts[1]) ? $name_parts[1] : '');
            $order->set_shipping_address_1($shipping['address']['line1']);
            $order->set_shipping_address_2($shipping['address']['line2']);
            $order->set_shipping_city($shipping['address']['city']);
            $order->set_shipping_state($shipping['address']['state']);
            $order->set_shipping_postcode($shipping['address']['postal_code']);
            $order->set_shipping_country($shipping['address']['country']);
        }

        // Handle international shipping and tax
        $country_code = $order->get_shipping_country();
        if ($country_code && $country_code !== 'GB') {
            $international_shipping_cost = get_option('international_shipping_cost', 0);
            if ($international_shipping_cost > 0) {
                $item = new WC_Order_Item_Shipping();
                $item->set_method_title('International Shipping');
                $item->set_total($international_shipping_cost);
                $order->add_item($item);
            }
            $order->set_taxes(array());
        }

        // Add order notes
        if ($invoice['billing_reason'] === 'subscription_cycle') {
            $order->add_order_note('Subscription renewal order updated from Stripe invoice ' . $invoice_id);
        } else {
            $order->add_order_note('Order updated from Stripe invoice ' . $invoice_id);
        }

        // Set order status to processing
        $order->update_status('processing', 'Order paid via Stripe');

        // Store the Stripe invoice ID
        $order->add_meta_data('_stripe_invoice_id', $invoice_id);

        // Save the order
        $order->save();

        // Trigger the WooCommerce new order actions
        do_action('woocommerce_new_order', $order->get_id(), $order);

        error_log('WooCommerce order processed from Stripe invoice: ' . $invoice_id);

        return new WP_REST_Response('Order processed successfully', 200);
    } catch (Exception $e) {
        error_log('Error processing Stripe invoice webhook: ' . $e->getMessage());
        return new WP_Error('order_processing_failed', 'Failed to process order: ' . $e->getMessage(), array('status' => 500));
    }
}

function get_order_id_by_stripe_invoice($invoice_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = '_stripe_invoice_id' AND meta_value = %s LIMIT 1",
        $invoice_id
    ));
}

function get_product_id_from_stripe($stripe_product_id) {
    $products = wc_get_products(array(
        'meta_key' => '_stripe_product_id',
        'meta_value' => $stripe_product_id,
        'limit' => 1,
    ));

    if (!empty($products)) {
        return $products[0]->get_id();
    }

    $product = wc_get_product_id_by_sku($stripe_product_id);
    if ($product) {
        return $product;
    }

    error_log("No WooCommerce product found for Stripe product ID: " . $stripe_product_id);
    return null;
}

function is_stripe_test_mode() {
    return get_option('stripe_mode', 'test') === 'test';
}
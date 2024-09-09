<?php
use PHPUnit\Framework\TestCase;

// Include the testable version of your plugin
require_once dirname(__DIR__) . '/stripe-woocommerce-webhook-handler-test.php';

class StripeWebhookHandlerTest extends TestCase
{
    private $checkout_session_data;
    private $invoice_paid_data;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset global state
        global $wc_orders;
        $wc_orders = [];

        // Mock the Stripe Checkout Session data
        $this->checkout_session_data = [
            'id' => 'cs_test_123456789',
            'invoice' => 'in_test_987654321',
            'amount_total' => 9999,
            'currency' => 'usd',
            'customer' => [
                'id' => 'cus_test_12345'
            ],
            'customer_details' => [
                'email' => 'test@example.com',
                'name' => 'Test User'
            ],
            'line_items' => [
                'data' => [
                    [
                        'description' => 'Test Product',
                        'quantity' => 1,
                        'amount_total' => 9999,
                        'price' => [
                            'product' => 'prod_test_12345'
                        ]
                    ]
                ]
            ]
        ];

        // Mock the Stripe Invoice Paid data
        $this->invoice_paid_data = [
            'id' => 'in_test_987654321',
            'amount_paid' => 9999,
            'currency' => 'usd',
            'customer' => 'cus_test_12345',
            'customer_email' => 'test@example.com',
            'customer_name' => 'Test User',
            'lines' => [
                'data' => [
                    [
                        'description' => 'Test Product',
                        'quantity' => 1,
                        'amount' => 9999,
                        'price' => [
                            'product' => 'prod_test_12345'
                        ]
                    ]
                ]
            ]
        ];
    }

    public function testHandleCheckoutSessionCompleted()
    {
        // Ensure we start with no orders
        $this->assertEquals(0, count(wc_get_orders()));

        $response = handle_checkout_session_completed($this->checkout_session_data);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        // Check that one order was created
        $orders = wc_get_orders();
        $this->assertCount(1, $orders);

        $order = reset($orders);
        $this->assertEquals('99.99', $order->get_total());
        $this->assertEquals('USD', $order->get_currency());
        $this->assertEquals('processing', $order->get_status());
        $this->assertEquals('in_test_987654321', $order->get_meta('_stripe_invoice_id'));
    }

    public function testHandleInvoicePaid()
    {
        // Ensure we start with no orders
        $this->assertEquals(0, count(wc_get_orders()));

        $response = handle_invoice_paid($this->invoice_paid_data);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        // Check that one order was created
        $orders = wc_get_orders();
        $this->assertCount(1, $orders);

        $order = reset($orders);
        $this->assertEquals('99.99', $order->get_total());
        $this->assertEquals('USD', $order->get_currency());
        $this->assertEquals('processing', $order->get_status());
        $this->assertEquals('in_test_987654321', $order->get_meta('_stripe_invoice_id'));
    }

    public function testHandleCheckoutSessionAndInvoicePaid()
    {
        // Ensure we start with no orders
        $this->assertEquals(0, count(wc_get_orders()));

        // Simulate the checkout session completed webhook
        $response = handle_checkout_session_completed($this->checkout_session_data);
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        // Check that one order was created
        $orders = wc_get_orders();
        $this->assertCount(1, $orders);
        $order = reset($orders);

        // Verify order details
        $this->assertEquals('99.99', $order->get_total());
        $this->assertEquals('USD', $order->get_currency());
        $this->assertEquals('processing', $order->get_status());
        $this->assertEquals('in_test_987654321', $order->get_meta('_stripe_invoice_id'));

        // Simulate the invoice paid webhook
        $response = handle_invoice_paid($this->invoice_paid_data);
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());

        // Check that we still have only one order
        $orders = wc_get_orders();
        $this->assertCount(1, $orders);
        $order = reset($orders);

        // Verify order details again
        $this->assertEquals('99.99', $order->get_total());
        $this->assertEquals('USD', $order->get_currency());
        $this->assertEquals('processing', $order->get_status());
        $this->assertEquals('in_test_987654321', $order->get_meta('_stripe_invoice_id'));

        // Check order notes
        $order_notes = wc_get_order_notes(['order_id' => $order->get_id()]);
        $this->assertCount(2, $order_notes);
        $this->assertStringContainsString('Order updated from Stripe Checkout Session', $order_notes[1]->content);
        $this->assertStringContainsString('Order updated from Stripe invoice', $order_notes[0]->content);
    }
}
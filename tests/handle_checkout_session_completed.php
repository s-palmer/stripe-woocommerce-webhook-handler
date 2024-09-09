<?php
class Test_Stripe_Webhook_Handler extends WP_UnitTestCase {
    public function setUp() {
        parent::setUp();
        // Setup test dependencies, e.g., mock Stripe API, create test products
    }

    public function test_handle_checkout_session_completed() {
        // Mock Stripe session data
        $mock_session = [
            'id' => 'cs_test_123',
            'amount_total' => 3999,
            'currency' => 'gbp',
            'customer_details' => [
                'email' => 'test@example.com',
                'name' => 'John Doe',
                'address' => [
                    'line1' => '123 Billing St',
                    'city' => 'London',
                    'postal_code' => 'E1 1AA',
                    'country' => 'GB'
                ]
            ],
            'shipping_details' => [
                'name' => 'Jane Doe',
                'address' => [
                    'line1' => '456 Shipping St',
                    'city' => 'Manchester',
                    'postal_code' => 'M1 1AA',
                    'country' => 'GB'
                ]
            ],
            'line_items' => [
                'data' => [
                    [
                        'description' => 'Test Product',
                        'amount_total' => 3999,
                        'quantity' => 1,
                        'price' => [
                            'product' => 'prod_test123'
                        ]
                    ]
                ]
            ]
        ];

        // Call the function
        handle_checkout_session_completed($mock_session);

        // Get the last created order
        $orders = wc_get_orders(['limit' => 1, 'orderby' => 'date', 'order' => 'DESC']);
        $last_order = $orders[0];

        // Assert order details
        $this->assertEquals(39.99, $last_order->get_total());
        $this->assertEquals('GBP', $last_order->get_currency());

        // Assert billing details
        $this->assertEquals('John', $last_order->get_billing_first_name());
        $this->assertEquals('Doe', $last_order->get_billing_last_name());
        $this->assertEquals('test@example.com', $last_order->get_billing_email());
        $this->assertEquals('123 Billing St', $last_order->get_billing_address_1());
        $this->assertEquals('London', $last_order->get_billing_city());
        $this->assertEquals('E1 1AA', $last_order->get_billing_postcode());
        $this->assertEquals('GB', $last_order->get_billing_country());

        // Assert shipping details
        $this->assertEquals('Jane', $last_order->get_shipping_first_name());
        $this->assertEquals('Doe', $last_order->get_shipping_last_name());
        $this->assertEquals('456 Shipping St', $last_order->get_shipping_address_1());
        $this->assertEquals('Manchester', $last_order->get_shipping_city());
        $this->assertEquals('M1 1AA', $last_order->get_shipping_postcode());
        $this->assertEquals('GB', $last_order->get_shipping_country());
    }
}
?>

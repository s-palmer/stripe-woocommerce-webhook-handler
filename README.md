# Stripe WooCommerce Webhook Handler

## Description

The Stripe WooCommerce Webhook Handler is a custom WordPress plugin designed to integrate Stripe payments with WooCommerce. It handles Stripe webhooks to create and manage orders in WooCommerce for both one-time purchases and subscription renewals.

## Features

- Processes Stripe webhook events
- Creates WooCommerce orders from Stripe Checkout sessions
- Handles subscription renewals and creates corresponding WooCommerce orders
- Supports both test and live modes of Stripe
- Detailed logging for easy troubleshooting

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.2 or higher
- Stripe account and API keys

## Installation

1. Download the plugin zip file.
2. Log in to your WordPress admin panel and navigate to Plugins > Add New.
3. Click the "Upload Plugin" button at the top of the page.
4. Choose the downloaded zip file and click "Install Now".
5. After installation, click "Activate Plugin".

## Configuration

1. Go to WooCommerce > Settings > Payments and ensure Stripe is enabled and configured.
2. Navigate to Settings > Stripe Webhook in your WordPress admin panel.
3. Enter your Stripe Webhook Secret Key.
4. Select the mode (Test or Live) that matches your Stripe account settings.
5. Save the settings.

## Stripe Webhook Setup

1. Log in to your Stripe Dashboard.
2. Go to Developers > Webhooks.
3. Click "Add endpoint".
4. Enter your webhook URL: `https://your-site.com/wp-json/custom-stripe-webhook/v1/handle`
5. Select the events to send: At minimum, choose `checkout.session.completed` and `invoice.paid`.
6. Copy the Webhook Signing Secret and paste it into your plugin settings in WordPress.

## Usage

Once configured, the plugin will automatically:

1. Create WooCommerce orders when customers complete a Stripe Checkout session.
2. Create renewal orders in WooCommerce when Stripe processes a subscription renewal.

No additional action is required for day-to-day operations.

## Troubleshooting

- Check your server's error logs for any issues during webhook processing.
- Ensure your Stripe webhook is correctly configured and pointing to the right URL.
- Verify that your WooCommerce products have the correct Stripe Product IDs set.

## Changelog

### 0.1
- Initial release
- Support for processing Stripe Checkout sessions
- Support for handling subscription renewals

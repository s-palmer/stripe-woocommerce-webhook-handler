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

## Switching Between Test and Production Modes

Switching between test and production modes requires changes in both your Stripe account and the plugin settings. Follow these steps carefully to ensure a smooth transition:

1. Stripe Dashboard Changes
Switching to Test Mode:

Log in to your Stripe Dashboard.
In the upper-right corner, toggle the switch to "View test data".
Go to Developers > API keys.
Copy your test mode API keys (Publishable key and Secret key).

Switching to Production Mode:

In your Stripe Dashboard, toggle the switch to "View live data".
Go to Developers > API keys.
If you haven't already, you may need to create live mode API keys.
Copy your live mode API keys (Publishable key and Secret key).

2. WordPress Plugin Settings

Navigate to Settings > Stripe Webhook in your WordPress admin panel.
Update the "Stripe Mode" setting to either "Test" or "Live".
Update the "Stripe Secret Key" with the corresponding key from your Stripe Dashboard.
Save the changes.

3. Update Webhook Endpoints
For Test Mode:

In your Stripe Dashboard (in test mode), go to Developers > Webhooks.
Add a new webhook endpoint or update the existing one with your test mode URL:
https://your-site.com/wp-json/custom-stripe-webhook/v1/handle
Ensure you've selected the correct events to send (checkout.session.completed and invoice.paid at minimum).
Copy the new Webhook Signing Secret.
Update the Webhook Secret Key in your WordPress plugin settings.

For Production Mode:

Repeat the same process in your Stripe Dashboard, but while in live mode.
Ensure you're using your production domain for the webhook URL.
Update the live mode Webhook Signing Secret in your WordPress plugin settings.

4. Test the Integration

After switching modes, perform a test transaction to ensure everything is working correctly.
Check that orders are being created in WooCommerce as expected.
Verify that webhook events are being received and processed correctly.

Important Notes:

Never use live API keys in a test environment or test keys in a production environment.
Always double-check that your webhook URLs are correct for each mode to prevent missed events.
Remember that test mode transactions will not process real payments.
It's recommended to test thoroughly in test mode before switching to production.

## Troubleshooting

- Check your server's error logs for any issues during webhook processing.
- Ensure your Stripe webhook is correctly configured and pointing to the right URL.
- Verify that your WooCommerce products have the correct Stripe Product IDs set.

## Changelog

### 0.1
- Initial release
- Support for processing Stripe Checkout sessions
- Support for handling subscription renewals

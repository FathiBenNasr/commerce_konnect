# Commerce Konnect Tunisia

Integrates the **Konnect.network** payment gateway with **Drupal Commerce 2.x**. This module provides a secure, off-site payment flow for Tunisian merchants.

## Features
- **Secure Transactions**: Built-in protection against ID-switching attacks.
- **Robust Logic**: Duplicate payment prevention to ensure data integrity.
- **Environment Support**: Easy switching between Sandbox (Preprod) and Live environments.
- **Modern Architecture**: Fully compliant with Drupal 10/11 dependency injection standards.

## Requirements
- Drupal Commerce 2.x
- A Konnect Merchant account ([konnect.network](https://konnect.network))

## Installation
1. Install and enable the module as you would any Drupal module.
2. Navigate to `Commerce > Configuration > Payment > Payment gateways`.
3. Click **Add payment gateway** and select **Konnect**.

## Configuration
1. **API Credentials**: Enter your `API Key` from your Konnect dashboard.
2. **Receiver Wallet ID**: Provide the Wallet ID where you wish to receive payments.
3. **Environment**: Select **Sandbox** for testing or **Live** for production.
4. **Webhook URL (Recommended)**: Copy the Webhook URL provided in the configuration form and add it to your Konnect Developer dashboard to ensure reliable payment notifications.

## Security
This module implements the following security measures:
- Verification of the remote `orderId` against the local Drupal Order ID on return.
- Server-to-server API validation of payment status using `x-api-key` headers.
- Use of the modern `parent::create()` pattern for secure and stable service injection.

## License
This project is licensed under the **GPL-2.0-or-later**.

## Troubleshooting

### TypeError / ArgumentCountError
If you see an error regarding constructor arguments, ensure you are using version 1.1.2 or higher. This version refactors the plugin to use the `ContainerInterface` properly, avoiding common dependency injection mismatches in Drupal 10.

### Webhook Not Working (404 Error)
If your Webhook URL returns a 404 error, you must **clear the Drupal cache** to register the route defined in `commerce_konnect.routing.yml`. Use `drush cr` or the "Clear all caches" button in the Performance settings.

### Payments Not Captured
- Ensure your **API Key** matches the environment (Sandbox vs. Live) selected in the gateway settings.
- Check the **Drupal Watchdog logs** (/admin/reports/dblog) for entries labeled `commerce_konnect` to see detailed API error responses.
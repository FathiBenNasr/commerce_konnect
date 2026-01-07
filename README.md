# Commerce Konnect Tunisia

Integrates the **Konnect.network** payment gateway with **Drupal Commerce 2.x**. This module provides a secure, off-site payment flow for Tunisian merchants.

## Features
- **Secure Transactions**: Built-in protection against ID-switching attacks.
- **Robust Logic**: Duplicate payment prevention to ensure data integrity.
- **Environment Support**: Easy switching between Sandbox (Preprod) and Live environments.
- **Developer Friendly**: Fully compliant with Drupal PSR-4 autoloading and coding standards.

## Requirements
- Drupal Commerce 2.x
- A Konnect Merchant account ([konnect.network](https://konnect.network))

## Installation
1. Install and enable the module as you would any Drupal module.
2. Navigate to `Commerce > Configuration > Payment > Payment gateways`.
3. Click **Add payment gateway** and select **Konnect**.

## Configuration
1. **API Credentials**: Enter your `API Key` and `API Secret` from your Konnect dashboard.
2. **Receiver Wallet ID**: Provide the Wallet ID where you wish to receive payments.
3. **Environment**: Select **Sandbox** for testing or **Live** for production.
4. **Webhook URL (Recommended)**: Copy the Webhook URL provided in the configuration form and add it to your Konnect Developer dashboard to ensure reliable payment notifications even if the customer closes their browser.

## Security
This module implements the following security measures:
- Verification of the remote `orderId` against the local Drupal Order ID on return.
- Server-to-server API validation of payment status.
- Use of Basic Auth for all API communications.

## License
This project is licensed under the **GPL-2.0-or-later**.
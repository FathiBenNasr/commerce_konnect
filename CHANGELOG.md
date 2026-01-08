## [1.1.8] - 2026-01-08

### Fixed
- **Critical Stability:** Removed the dynamic call to `Url::fromRoute` in the admin form to prevent `RouteNotFoundException` during installation or cache rebuilds.
- **Webhook Logic:** Split the webhook configuration into a storage field and a static instruction block for improved reliability.
- **Metadata:** Updated `commerce_konnect.info.yml` to version 1.1.8.

## [1.1.7] - 2026-01-08

### Fixed
- **Controller Mapping:** Corrected `commerce_konnect.routing.yml` to use `PaymentGatewayController::notifyPage`. This resolves the `MethodDoesNotExist` exception caused by using the incorrect checkout controller.
- **Routing Stability:** Verified that the `commerce_konnect.webhook` route is correctly registered in the Drupal RouteProvider after cache rebuild.

## [1.1.6] - 2026-01-08

### Fixed
- **Webhook Routing:** Rebuilt `commerce_konnect.routing.yml` to utilize the standard `PaymentCheckoutController::notifyPage`. This resolves the `RouteNotFoundException` that occurred in the administration interface.
- **Admin Form Stability:** Fixed dynamic Webhook URL generation in the plugin configuration form to ensure the FQDN is correctly resolved.
- **Access Control:** Updated Webhook requirements to `_access: 'TRUE'` to allow asynchronous server-to-server notifications from Konnect.
- Standardized the notification path to `/payment/notify/konnect`.

## [1.1.5] - 2026-01-08
### Fixed
- **API Endpoints:** Corrected the sandbox URL from `preprod` to `sandbox.konnect.network` to resolve 404 errors during payment initiation.
- **Environment Alignment:** Ensured test mode keys are correctly validated against the official Konnect Sandbox environment.

## [1.1.4] - 2026-01-08
### Added
- **Dynamic Currency Logic:** Implemented automatic fraction digit detection (e.g., 3 decimals for TND, 2 for EUR) for cross-border payment accuracy.

### Fixed
- **Critical Engine Fix:** Resolved `Call to undefined method Price::getMinorUnits()` by implementing minor unit calculation.

## [1.1.3] - 2026-01-08
### Fixed
- **Authentication:** Refactored the API authentication to use the `x-api-key` header instead of Basic Auth with an "API Secret", aligning the module with the official Konnect API documentation.
- **Architecture:** Resolved a critical `TypeError` by refactoring the plugin to use `parent::create()` for dependency injection, ensuring compatibility with Drupal 10/11.
- **Cleanup:** Removed the unused and confusing `api_secret` field from the gateway configuration form and optimized `KonnectPaymentForm` data handling.

### Added
- Improved internal logging for API calls to facilitate debugging of payment failures.

## [1.1.1] - 2026-01-07
### Fixed
- **Critical:** Fixed `ArgumentCountError` in `Konnect.php` by updating the constructor to match Drupal Commerce's requirements for 8 arguments.
- Fixed a syntax error in `KonnectPaymentForm.php` regarding duplicate array keys for `firstName` and `lastName`.
- Corrected type hinting for `PaymentTypeManagerInterface` and `PaymentMethodTypeManagerInterface` in the gateway plugin.

### Added
- Added asynchronous payment notification support (Webhooks).
- Created `commerce_konnect.routing.yml` to handle the incoming Konnect API notifications.
- Added an API double-check in the `onNotify` method to verify payment status directly with Konnect servers.

### Security
- Implemented an ID switching protection check in `onReturn()` to ensure the returned Konnect `orderId` matches the Drupal Order ID.
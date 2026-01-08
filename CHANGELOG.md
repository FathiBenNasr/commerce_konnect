# Changelog

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
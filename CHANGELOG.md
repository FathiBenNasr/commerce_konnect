# Changelog

## [1.1.1] - 2026-01-07
### Fixed
- **Critical:** Fixed `ArgumentCountError` in `Konnect.php` by updating the constructor to match Drupal Commerce's requirements for 8 arguments.
- Fixed a syntax error in `KonnectPaymentForm.php` regarding duplicate array keys for `firstName` and `lastName`.
- Corrected type hinting for `PaymentTypeManagerInterface` and `PaymentMethodTypeManagerInterface` in the gateway plugin.

### Added
- Added asynchronous payment notification support (Webhooks).
- Created `commerce_konnect.routing.yml` to handle the incoming Konnect API notifications.
- Added an API double-check in the `onNotify` method to verify payment status directly with Konnect servers for enhanced security.

### Security
- Implemented an ID switching protection check in `onReturn()` to ensure the returned Konnect `orderId` matches the Drupal Order ID.
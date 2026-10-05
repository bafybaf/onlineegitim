# Changelog

All notable changes to this project are documented in this file.

## [1.1.0] - 2026-10-01

### Added

- Card holder IP address for card payments: `setIp()` / `getIp()` on `NonSecurePaymentInvoice`, `SecurePaymentInvoice` and `SavedCardPaymentInvoice`, sent to the API as `ip` ([PS-3458](https://sipay.atlassian.net/browse/PS-3458)). `setIp()` accepts a single valid IPv4 or IPv6 address, converts IPv4-mapped IPv6 (`::ffff:…`) to IPv4, and throws `Sipay\Exceptions\InvalidArgumentException` for anything else.
- Payment samples now send the card holder IP address.

### Upgrade Notes

- No breaking changes. `ip` is only sent when `setIp()` is called, so existing integrations keep working.
- If your merchant account has the "Cardholder IP" billing field set to mandatory, payments without a valid IP are rejected. Call `setIp()` with the end user's IP address (not your server's) on every card payment. See the "Card Holder IP Address" section in the README.

## [1.0.0] - 2025-05-23

- Initial release.

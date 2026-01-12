# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-02-10

### Added
- Max verification attempts with auto-invalidation (default: 5 attempts)
- `TooManyVerificationAttemptsException` for exceeded attempts
- Optional dual verification mode requiring confirmation from both old and new email addresses
- `EmailChangeDualSignature` value object with `getOldEmailSignedUrl()` for dual mode
- `validateOldEmailToken()` method on `EmailChangeHelper`
- `EmailChangeNewEmailConfirmedEvent` and `EmailChangeOldEmailConfirmedEvent` events
- `CsrfTokenHelper` service for protecting the cancel endpoint
- `max_attempts` configuration option
- `require_old_email_confirmation` configuration option
- Repository method `findByOldEmailSelector()` for dual mode lookups
- Entity fields: `attempts`, `confirmedByNewEmail`, `confirmedByOldEmail`, `oldEmailHashedToken`, `oldEmailSelector`

### Security
- Verification links are automatically invalidated after configurable failed attempts
- Dual verification mode prevents account takeover by requiring old email confirmation
- CSRF token helper prevents cross-site request forgery on cancel endpoint

## [1.1.0] - 2026-02-10

### Added
- `EmailChangeableInterface` — a simplified interface requiring only `getId()`, `getEmail()`, and `setEmail()`
- `verify:email-change:purge-expired` console command to clean up expired requests
  - `--dry-run` option to preview without deleting
  - `--older-than=SECONDS` option to target only old expired requests
- Symfony Flex auto-discovery configuration
- `.gitattributes` for correct GitHub language detection
- Repository methods: `countExpiredEmailChangeRequests()`, `removeExpiredOlderThan()`, `countExpiredOlderThan()`

### Changed
- All internal type hints updated from `EmailChangeInterface` to `EmailChangeableInterface`
- `EmailChangeInterface` now extends `EmailChangeableInterface` (fully backward compatible)

### Deprecated
- `EmailChangeInterface` — use `EmailChangeableInterface` instead (will be removed in 2.0)

## [1.0.0] - 2025-12-04

### Added
- Initial release of the bundle
- `EmailChangeHelper` service for handling email change requests
- `EmailChangeInterface` for User entities
- `EmailChangeRequest` entity for storing verification tokens
- Cryptographically secure token generation (selector + hashed token pattern)
- Configurable expiration times for verification links
- Built-in request throttling to prevent abuse
- Event system (EmailChangeInitiatedEvent, EmailChangeConfirmedEvent, EmailChangeCancelledEvent)
- Comprehensive exception hierarchy
- Doctrine repository implementation
- Twig extension for pending email change status
- Symfony bundle configuration
- Complete documentation and examples

### Security
- SHA-256 token hashing
- Timing-attack prevention with `hash_equals()`
- No plain text token storage
- Configurable throttling limits

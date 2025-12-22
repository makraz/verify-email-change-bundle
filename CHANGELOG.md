# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

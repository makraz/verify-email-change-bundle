# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
- Symfony bundle configuration
- Complete documentation and examples

### Security
- SHA-256 token hashing
- Timing-attack prevention with `hash_equals()`
- No plain text token storage
- Configurable throttling limits

## [1.0.0] - 2025-12-04

### Added
- Initial development version

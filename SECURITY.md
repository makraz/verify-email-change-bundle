# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 2.0.x   | :white_check_mark: |
| 1.4.x   | :white_check_mark: |
| < 1.4   | :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability in this bundle, please report it responsibly.

**Do not open a public GitHub issue.**

Instead, send an email to **hamza@makraz.com** with:

- A description of the vulnerability
- Steps to reproduce (or a proof of concept)
- The affected version(s)
- Any potential impact assessment

You will receive an acknowledgment within **48 hours**, and a detailed response within **5 business days** indicating the next steps.

## Security Measures

This bundle handles security-sensitive operations (email address changes) and implements the following protections:

### Token Security
- Verification tokens use the **selector/hashed-token** pattern (similar to password reset best practices)
- Tokens are hashed with **SHA-256** before storage — plain text tokens are never persisted
- Token comparison uses `hash_equals()` to prevent **timing attacks**

### Brute-Force Protection
- Configurable **max verification attempts** (default: 5) with automatic request invalidation
- Built-in **request throttling** to prevent abuse

### Verification Integrity
- Optional **dual verification mode** requiring confirmation from both old and new email addresses to prevent account takeover
- **CSRF protection** helper for the cancel endpoint
- Configurable **token expiration** times

### General
- No sensitive data is exposed in events or logs
- All cryptographic operations use PHP's `random_bytes()` via Symfony's secure token generator
- The bundle follows the principle of least privilege in its service definitions

## Disclosure Policy

- Confirmed vulnerabilities will be patched and released as soon as possible
- A security advisory will be published on GitHub once a fix is available
- Credit will be given to reporters unless they prefer to remain anonymous

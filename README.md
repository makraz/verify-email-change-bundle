# Makraz Verify Email Change Bundle

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![Tests](https://github.com/makraz/verify-email-change-bundle/workflows/Tests/badge.svg)](https://github.com/makraz/verify-email-change-bundle/actions)

A Symfony bundle that provides secure email address change functionality with verification.

## Features

- **Cryptographically Secure**: Uses selector + hashed token pattern to prevent timing attacks
- **Configurable Expiration**: Set custom lifetimes for verification links
- **Built-in Throttling**: Prevents abuse with configurable rate limiting
- **Flexible**: You control email sending, UI, and password verification
- **Twig Integration**: Built-in Twig functions for checking pending email changes
- **Max Verification Attempts**: Auto-invalidation after configurable failed attempts
- **Dual Verification Mode**: Optional confirmation from both old and new email addresses
- **CSRF Protection**: Built-in helper for cancel endpoint security
- **Email Notifications**: Built-in `EmailChangeNotifier` service with Twig templates
- **Translations**: Built-in translations for English, French, and Arabic
- **API/Headless Support**: JSON response factory for SPA and mobile app integration
- **OTP Verification**: Numeric code verification alternative to signed URLs
- **Audit Events**: Security-relevant events for logging and compliance
- **Pluggable Persistence**: Doctrine ORM, PSR-6 Cache, or in-memory adapters
- **Well Tested**: Comprehensive test suite with 480+ tests
- **Event-Driven**: Dispatches events for extensibility
- **Symfony Flex**: Auto-discovery support for seamless installation

## Installation

```bash
composer require makraz/verify-email-change-bundle
```

The bundle supports **Symfony Flex auto-discovery** and will be registered automatically. If you're not using Flex, enable it manually:

```php
// config/bundles.php
return [
    // ...
    Makraz\Bundle\VerifyEmailChange\MakrazVerifyEmailChangeBundle::class => ['all' => true],
];
```

## Quick Start

### Step 1: Update Your User Entity

Your User entity must implement `EmailChangeableInterface`:

```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;

#[ORM\Entity]
class User implements EmailChangeableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    // ... rest of your entity

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }
}
```

> **Note:** `EmailChangeableInterface` only requires `getId()`, `getEmail()`, and `setEmail()`.

### Step 2: Create the Database Table

Run the following command to create the migration:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Or create the table manually:

```sql
CREATE TABLE email_change_request (
    id INT AUTO_INCREMENT PRIMARY KEY,
    selector VARCHAR(20) UNIQUE NOT NULL,
    hashed_token VARCHAR(100) NOT NULL,
    requested_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    new_email VARCHAR(180) NOT NULL,
    user_identifier VARCHAR(255) NOT NULL,
    INDEX email_change_selector_idx (selector),
    INDEX email_change_user_idx (user_identifier)
);
```

### Step 3: Configure the Bundle (Optional)

```yaml
# config/packages/verify_email_change.yaml
verify_email_change:
    lifetime: 3600              # Link expires after 1 hour (default)
    enable_throttling: true     # Prevent abuse (default: true)
    throttle_limit: 3600        # Wait time between requests (default: 1 hour)
    max_attempts: 5             # Max verification attempts before invalidation (default: 5)
    require_old_email_confirmation: false  # Require old email confirmation too (default: false)
```

### Step 4: Create Your Controller

```php
<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeHelper;
use Makraz\Bundle\VerifyEmailChange\Exception\VerifyEmailChangeExceptionInterface;
use Doctrine\ORM\EntityManagerInterface;

class EmailChangeController extends AbstractController
{
    public function __construct(
        private readonly EmailChangeHelper $emailChangeHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
    ) {}

    #[Route('/account/email/change', name: 'app_email_change_request')]
    public function request(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Your form handling here...
        $newEmail = $request->request->get('new_email');

        try {
            // Generate the verification signature
            $signature = $this->emailChangeHelper->generateSignature(
                'app_email_change_verify',
                $user,
                $newEmail
            );

            $this->entityManager->flush();

            // Send verification email to the NEW address
            $email = (new Email())
                ->to($newEmail)
                ->subject('Verify your new email address')
                ->html(sprintf(
                    'Click here to verify: <a href="%s">%s</a><br>This link will expire in 1 hour.',
                    $signature->getSignedUrl(),
                    $signature->getSignedUrl()
                ));

            $this->mailer->send($email);

            $this->addFlash('success', 'Verification email sent! Check your new inbox.');
        } catch (VerifyEmailChangeExceptionInterface $e) {
            $this->addFlash('error', $e->getReason());
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/account/email/verify', name: 'app_email_change_verify')]
    public function verify(Request $request): Response
    {
        try {
            $user = $this->emailChangeHelper->validateTokenAndFetchUser($request);
            $oldEmail = $this->emailChangeHelper->confirmEmailChange($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'Email changed successfully!');
        } catch (VerifyEmailChangeExceptionInterface $e) {
            $this->addFlash('error', $e->getReason());
            return $this->redirectToRoute('app_email_change_request');
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/account/email/cancel', name: 'app_email_change_cancel', methods: ['POST'])]
    public function cancel(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->emailChangeHelper->cancelEmailChange($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'Email change cancelled.');

        return $this->redirectToRoute('app_profile');
    }
}
```

## Displaying Pending Email Changes

The bundle provides Twig functions to easily display pending email change status in your templates.

### Using Twig Functions (Recommended)

```twig
{# templates/account/profile.html.twig #}

<h2>Email Address</h2>

<div>
    <strong>Current Email:</strong> {{ app.user.email }}
</div>

{% if has_pending_email_change(app.user) %}
    <div class="alert alert-info">
        <p>Pending email change to: <strong>{{ get_pending_email(app.user) }}</strong></p>
        <p>Please check your new email inbox for the verification link.</p>

        <form method="post" action="{{ path('app_email_change_cancel') }}">
            <button type="submit" class="btn btn-secondary">Cancel Email Change</button>
        </form>
    </div>
{% else %}
    <a href="{{ path('app_email_change_request') }}" class="btn btn-primary">
        Change Email Address
    </a>
{% endif %}
```

### Available Twig Functions

- **`has_pending_email_change(user)`**: Returns `true` if the user has a pending, non-expired email change request
- **`get_pending_email(user)`**: Returns the pending new email address, or `null` if none exists

## How It Works

### Flow Diagram

```
User requests email change
    |
EmailChangeHelper::generateSignature()
    |
Token created & stored (hashed)
    |
Verification email sent to NEW address
    |
User clicks link
    |
EmailChangeHelper::validateTokenAndFetchUser()
    |
Token validated (timing-safe comparison)
    |
EmailChangeHelper::confirmEmailChange()
    |
Email updated, request deleted
    |
Notification sent to OLD address
```

## API Reference

### EmailChangeHelper

#### `generateSignature()`

```php
public function generateSignature(
    string $routeName,
    EmailChangeableInterface $user,
    string $newEmail,
    array $extraParams = []
): EmailChangeSignature
```

#### `validateTokenAndFetchUser()`

```php
public function validateTokenAndFetchUser(Request $request): EmailChangeableInterface
```

#### `confirmEmailChange()`

```php
public function confirmEmailChange(EmailChangeableInterface $user): string
```

Returns the user's old email address.

#### `cancelEmailChange()`

```php
public function cancelEmailChange(EmailChangeableInterface $user): void
```

#### `hasPendingEmailChange()`

```php
public function hasPendingEmailChange(EmailChangeableInterface $user): bool
```

#### `getPendingEmail()`

```php
public function getPendingEmail(EmailChangeableInterface $user): ?string
```

## Maintenance

### Purging Expired Requests

Expired email change requests remain in the database until purged. Use the built-in console command to clean them up:

```bash
# Purge all expired requests
php bin/console verify:email-change:purge-expired

# Preview what would be purged (no changes made)
php bin/console verify:email-change:purge-expired --dry-run

# Purge only requests that expired more than 24 hours ago
php bin/console verify:email-change:purge-expired --older-than=86400

# Combine options
php bin/console verify:email-change:purge-expired --dry-run --older-than=3600
```

**Recommended:** Add a cron job or Symfony Scheduler task to purge regularly:

```bash
# Run daily at midnight
0 0 * * * cd /path/to/project && php bin/console verify:email-change:purge-expired --older-than=86400
```

Or with Symfony Scheduler:

```php
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask('1 day')]
class PurgeExpiredEmailChangeRequestsMessage
{
    // This message triggers the purge command
}
```

## Events

The bundle dispatches events for extensibility:

### `EmailChangeInitiatedEvent`

Dispatched when an email change request is initiated.

```php
use Makraz\Bundle\VerifyEmailChange\Event\EmailChangeInitiatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class EmailChangeListener
{
    public function __invoke(EmailChangeInitiatedEvent $event): void
    {
        $user = $event->getUser();
        $newEmail = $event->getNewEmail();
        $oldEmail = $event->getOldEmail();
        $verificationUrl = $event->getVerificationUrl();
    }
}
```

### `EmailChangeConfirmedEvent`

Dispatched when an email change is confirmed.

### `EmailChangeCancelledEvent`

Dispatched when an email change is cancelled.

## Security

### Dual Verification Mode

For high-security applications, enable dual verification to require confirmation from **both** the old and new email addresses:

```yaml
# config/packages/verify_email_change.yaml
verify_email_change:
    require_old_email_confirmation: true
```

When enabled, `generateSignature()` returns an `EmailChangeDualSignature` with two URLs:

```php
$signature = $this->emailChangeHelper->generateSignature(
    'app_email_change_verify',
    $user,
    $newEmail
);

// Send verification to the NEW email address
$this->sendEmail($newEmail, $signature->getSignedUrl());

// Also send confirmation to the OLD email address
if ($signature instanceof EmailChangeDualSignature) {
    $this->sendEmail($user->getEmail(), $signature->getOldEmailSignedUrl());
}
```

Handle old email verification in your controller:

```php
#[Route('/account/email/verify-old', name: 'app_email_change_verify_old')]
public function verifyOldEmail(Request $request): Response
{
    try {
        $user = $this->emailChangeHelper->validateOldEmailToken($request);
        // Check if both confirmations are done
        $oldEmail = $this->emailChangeHelper->confirmEmailChange($user);
        $this->entityManager->flush();
        $this->addFlash('success', 'Email changed successfully!');
    } catch (VerifyEmailChangeExceptionInterface $e) {
        $this->addFlash('info', $e->getReason());
    }

    return $this->redirectToRoute('app_profile');
}
```

### Max Verification Attempts

The bundle automatically invalidates verification links after a configurable number of failed attempts (default: 5):

```yaml
verify_email_change:
    max_attempts: 5
```

After exceeding the limit, a `TooManyVerificationAttemptsException` is thrown and the request is removed.

### CSRF Protection for Cancel Endpoint

The bundle provides a `CsrfTokenHelper` to protect the cancel endpoint:

```php
use Makraz\Bundle\VerifyEmailChange\Security\CsrfTokenHelper;

class EmailChangeController extends AbstractController
{
    public function __construct(
        private readonly EmailChangeHelper $emailChangeHelper,
        private readonly CsrfTokenHelper $csrfHelper,
    ) {}

    #[Route('/account/email/cancel', name: 'app_email_change_cancel', methods: ['POST'])]
    public function cancel(Request $request): Response
    {
        if (!$this->csrfHelper->isTokenValid($request)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->emailChangeHelper->cancelEmailChange($this->getUser());
        $this->addFlash('success', 'Email change cancelled.');
        return $this->redirectToRoute('app_profile');
    }
}
```

In your Twig template:

```twig
<form method="post" action="{{ path('app_email_change_cancel') }}">
    <input type="hidden" name="_csrf_token" value="{{ csrf_token('email_change_cancel') }}">
    <button type="submit">Cancel Email Change</button>
</form>
```

### Security Recommendations

1. **Always require password confirmation** before initiating an email change
2. **Enable dual verification** (`require_old_email_confirmation: true`) for sensitive applications
3. **Use CSRF protection** on the cancel endpoint (see above)
4. **Send a notification** to the old email address when an email change is initiated
5. **Keep verification link lifetimes short** (1 hour is recommended)
6. **Set up the purge command** as a cron job to clean expired requests
7. **Use HTTPS** for all verification URLs (the bundle uses `UrlGeneratorInterface::ABSOLUTE_URL`)
8. **Monitor events** — listen to `EmailChangeInitiatedEvent` for audit logging

## Email Notifications

The bundle includes an optional `EmailChangeNotifier` service that handles sending verification and notification emails using the built-in Twig templates.

### Enabling the Notifier

```yaml
# config/packages/verify_email_change.yaml
verify_email_change:
    notifier:
        enabled: true
        sender_email: 'noreply@example.com'
        sender_name: 'My Application'  # optional
```

### Using the Notifier

```php
use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeHelper;
use Makraz\Bundle\VerifyEmailChange\Notifier\EmailChangeNotifier;

class EmailChangeController extends AbstractController
{
    public function __construct(
        private readonly EmailChangeHelper $emailChangeHelper,
        private readonly EmailChangeNotifier $notifier,
    ) {}

    public function request(Request $request): Response
    {
        $user = $this->getUser();
        $newEmail = $request->request->get('new_email');

        $signature = $this->emailChangeHelper->generateSignature(
            'app_email_change_verify',
            $user,
            $newEmail
        );

        // Sends verification to new email (and old email in dual mode)
        $this->notifier->sendVerificationEmail($user, $newEmail, $signature);

        return $this->redirectToRoute('app_profile');
    }

    public function verify(Request $request): Response
    {
        $user = $this->emailChangeHelper->validateTokenAndFetchUser($request);
        $oldEmail = $this->emailChangeHelper->confirmEmailChange($user);

        // Notify old email address about the change
        $this->notifier->sendEmailChangeConfirmation($user, $oldEmail, $user->getEmail());

        return $this->redirectToRoute('app_profile');
    }
}
```

### Customizing Email Templates

Override the default templates by creating files in your project:

```
templates/bundles/MakrazVerifyEmailChange/email/
    verify_new_email.html.twig       # Verification email to new address
    confirm_old_email.html.twig      # Confirmation email to old address (dual mode)
    email_change_confirmed.html.twig # Change complete notification
    email_change_cancelled.html.twig # Cancellation notification
```

## Translations

The bundle includes translations for exception messages and email templates in:
- **English** (`en`)
- **French** (`fr`)
- **Arabic** (`ar`)

Translations are loaded automatically. To override them, create your own translation files:

```yaml
# translations/verify_email_change.en.yaml
verify_email_change:
    exception:
        same_email: "Your custom message here"
    notification:
        verify_subject: "Custom subject"
```

## API / Headless Mode

For SPA, mobile apps, and API-first applications, use the `EmailChangeResponseFactory` to generate consistent JSON responses:

```php
use Makraz\Bundle\VerifyEmailChange\Api\EmailChangeResponseFactory;
use Makraz\Bundle\VerifyEmailChange\Exception\VerifyEmailChangeExceptionInterface;

class ApiEmailChangeController
{
    public function __construct(
        private readonly EmailChangeHelper $emailChangeHelper,
        private readonly EmailChangeResponseFactory $responseFactory,
    ) {}

    #[Route('/api/email/change', methods: ['POST'])]
    public function request(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            $newEmail = $request->toArray()['new_email'];

            $signature = $this->emailChangeHelper->generateSignature(
                'api_email_change_verify', $user, $newEmail
            );

            return $this->responseFactory->initiated($newEmail, $signature->getExpiresAt());
        } catch (VerifyEmailChangeExceptionInterface $e) {
            return $this->responseFactory->error($e);
        }
    }

    #[Route('/api/email/status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $user = $this->getUser();
        return $this->responseFactory->pendingStatus(
            $this->emailChangeHelper->getPendingEmail($user)
        );
    }
}
```

## OTP Verification

For mobile apps and API flows, use numeric OTP codes instead of signed URL links:

```yaml
# config/packages/verify_email_change.yaml
verify_email_change:
    otp:
        enabled: true
        length: 6  # 4-10 digits
```

```php
use Makraz\Bundle\VerifyEmailChange\Otp\OtpEmailChangeHelper;

class OtpEmailChangeController
{
    public function __construct(
        private readonly OtpEmailChangeHelper $otpHelper,
    ) {}

    #[Route('/api/email/otp/request', methods: ['POST'])]
    public function request(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $newEmail = $request->toArray()['new_email'];

        $result = $this->otpHelper->generateOtp($user, $newEmail);

        // Send the OTP to the new email address
        // $this->mailer->send(...$result->getOtp()...)

        return new JsonResponse([
            'message' => 'OTP sent to new email address.',
            'expires_at' => $result->getExpiresAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/api/email/otp/verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $otp = $request->toArray()['otp'];

        $oldEmail = $this->otpHelper->verifyOtp($user, $otp);

        return new JsonResponse([
            'message' => 'Email changed successfully.',
            'old_email' => $oldEmail,
            'new_email' => $user->getEmail(),
        ]);
    }
}
```

## Audit Events

The `EmailChangeAuditEvent` provides security-relevant information for logging:

```php
use Makraz\Bundle\VerifyEmailChange\Event\EmailChangeAuditEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class EmailChangeAuditListener
{
    public function __invoke(EmailChangeAuditEvent $event): void
    {
        $action = $event->getAction(); // 'initiated', 'confirmed', 'failed_verification', etc.
        $user = $event->getUser();
        $ip = $event->getIpAddress();
        $metadata = $event->getMetadata();

        // Log to your audit system
    }
}
```

Available actions: `initiated`, `verified`, `confirmed`, `cancelled`, `failed_verification`, `max_attempts_exceeded`, `expired_access`, `old_email_confirmed`.

## Persistence Adapters

The bundle supports multiple persistence backends. The default is Doctrine ORM.

### Doctrine ORM (default)

```yaml
verify_email_change:
    persistence: doctrine
```

Requires `doctrine/orm` and `doctrine/doctrine-bundle`.

### PSR-6 Cache

```yaml
verify_email_change:
    persistence: cache
```

Stores requests in a PSR-6 cache pool (Redis, Memcached, filesystem, etc.). Requires a `CacheItemPoolInterface` service and a user provider callback.

### In-Memory

The `InMemoryEmailChangeRequestRepository` is intended for testing. Register it as a service manually:

```yaml
# config/packages/test/verify_email_change.yaml
verify_email_change:
    persistence_service: 'app.in_memory_email_change_repository'
```

### Custom Adapter

Implement `EmailChangeRequestRepositoryInterface` and point the configuration to your service:

```yaml
verify_email_change:
    persistence_service: 'App\Repository\MyEmailChangeRequestRepository'
```

When `persistence_service` is set, it takes precedence over the `persistence` option.

## Configuration Reference

```yaml
verify_email_change:
    # Time in seconds that an email change request is valid
    # Min: 60 (1 minute), Max: 86400 (24 hours)
    lifetime: 3600  # default: 1 hour

    # Enable request throttling to prevent abuse
    enable_throttling: true  # default: true

    # Time in seconds before a new request can be made
    # Only used if enable_throttling is true
    throttle_limit: 3600  # default: 1 hour

    # Maximum number of failed verification attempts before invalidation
    max_attempts: 5  # default: 5

    # Require confirmation from both old and new email addresses
    require_old_email_confirmation: false  # default: false

    # Persistence adapter: "doctrine" or "cache"
    persistence: doctrine  # default: doctrine

    # Custom service ID for the repository (overrides persistence option)
    persistence_service: ~  # default: null

    # OTP verification mode (alternative to signed URLs)
    otp:
        enabled: false  # default: false
        length: 6       # default: 6 (4-10 digits)

    # Optional email notifier service
    notifier:
        enabled: false  # default: false
        sender_email: ~  # required when enabled
        sender_name: ~   # optional
```

## Exception Reference

All exceptions implement `VerifyEmailChangeExceptionInterface`.

| Exception | When | Message |
|---|---|---|
| `SameEmailException` | New email equals current email | "The new email address is identical to the current one." |
| `EmailAlreadyInUseException` | Email taken by another user | "This email address is already in use." |
| `TooManyEmailChangeRequestsException` | Request too soon after previous | "You have already requested an email change..." |
| `TooManyVerificationAttemptsException` | Max attempts exceeded | "Too many verification attempts (max: N)." |
| `ExpiredEmailChangeRequestException` | Verification link expired | "The email change link has expired." |
| `InvalidEmailChangeRequestException` | Invalid or tampered link | Varies |

```php
use Makraz\Bundle\VerifyEmailChange\Exception\VerifyEmailChangeExceptionInterface;

try {
    // Your email change logic
} catch (VerifyEmailChangeExceptionInterface $e) {
    $this->addFlash('error', $e->getReason());
}
```

## Upgrading

### From v1.4 to v2.0

**Breaking changes:**
- `EmailChangeInterface` has been **removed**. Use `EmailChangeableInterface` instead.
- `Persistence\EmailChangeRequestRepository` has been **removed**. Use `Persistence\Doctrine\DoctrineEmailChangeRequestRepository` instead.

**New features:**
- `EmailChangeResponseFactory` for JSON/API responses
- OTP-based email verification (`OtpEmailChangeHelper`)
- `EmailChangeAuditEvent` for security logging

**Migration:**

```diff
-use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeInterface;
+use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;

-class User implements EmailChangeInterface
+class User implements EmailChangeableInterface
```

### From v1.3 to v1.4

**New features (non-breaking):**
- Pluggable persistence adapters: Doctrine ORM, PSR-6 Cache, In-Memory
- `DoctrineEmailChangeRequestRepository` moved to `Persistence\Doctrine` namespace
- `persistence` and `persistence_service` configuration options
- `EmailChangeRequestRepository` is now deprecated (use `DoctrineEmailChangeRequestRepository`)

No database migration required.

### From v1.2 to v1.3

**New features (non-breaking):**
- Translation support for English, French, and Arabic
- Default Twig email templates (`@MakrazVerifyEmailChange/email/...`)
- Optional `EmailChangeNotifier` service for sending emails

No database migration required. Enable the notifier in configuration if desired.

### From v1.1 to v1.2

**New features (non-breaking):**
- Max verification attempts protection — enabled by default (5 attempts). Configure with `max_attempts`.
- Dual verification mode — opt-in with `require_old_email_confirmation: true`.
- CSRF token helper — optional service for cancel endpoint protection.

**Database migration required:** The `email_change_request` table has new columns:

```sql
ALTER TABLE email_change_request
    ADD attempts INT DEFAULT 0 NOT NULL,
    ADD confirmed_by_new_email TINYINT(1) DEFAULT 0 NOT NULL,
    ADD confirmed_by_old_email TINYINT(1) DEFAULT 0 NOT NULL,
    ADD old_email_hashed_token VARCHAR(100) DEFAULT NULL,
    ADD old_email_selector VARCHAR(20) DEFAULT NULL;
CREATE UNIQUE INDEX email_change_old_selector_idx ON email_change_request (old_email_selector);
```

Or use Doctrine migrations:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### From v1.0 to v1.1

**Interface change (non-breaking):**
- `EmailChangeInterface` is now deprecated. Migrate to `EmailChangeableInterface` which only requires `getId()`, `getEmail()`, and `setEmail()`.
- Classes implementing `EmailChangeInterface` continue to work without changes.

```diff
-use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeInterface;
+use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;

-class User implements EmailChangeInterface
+class User implements EmailChangeableInterface
 {
-    // Can remove hasPendingEmailChange() and getPendingEmail()
 }
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

This bundle is released under the MIT License. See the [LICENSE](LICENSE) file for details.

## Support

For issues, questions, or contributions, please visit:
https://github.com/makraz/verify-email-change-bundle

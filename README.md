# Makraz Verify Email Change Bundle

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![Tests](https://github.com/makraz/verify-email-change-bundle/workflows/Tests/badge.svg)](https://github.com/makraz/verify-email-change-bundle/actions)

A Symfony bundle that provides secure email address change functionality with verification.

## Features

- **Cryptographically Secure**: Uses selector + hashed token pattern to prevent timing attacks
- **Configurable Expiration**: Set custom lifetimes for verification links
- **Built-in Throttling**: Prevents abuse with configurable rate limiting
- **Flexible**: You control email sending, UI, and password verification
- **Twig Integration**: Built-in Twig functions for checking pending email changes
- **Well Tested**: Comprehensive test suite with 225+ tests
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

Your User entity must implement `EmailChangeableInterface` (recommended) or the deprecated `EmailChangeInterface`:

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

> **Note:** `EmailChangeableInterface` only requires `getId()`, `getEmail()`, and `setEmail()`. The older `EmailChangeInterface` is deprecated since v1.1 and will be removed in v2.0.

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

All methods accept `EmailChangeableInterface` (the new recommended interface). Objects implementing the deprecated `EmailChangeInterface` continue to work without changes.

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
```

## Exception Reference

All exceptions implement `VerifyEmailChangeExceptionInterface`.

| Exception | When | Message |
|---|---|---|
| `SameEmailException` | New email equals current email | "The new email address is identical to the current one." |
| `EmailAlreadyInUseException` | Email taken by another user | "This email address is already in use." |
| `TooManyEmailChangeRequestsException` | Request too soon after previous | "You have already requested an email change..." |
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

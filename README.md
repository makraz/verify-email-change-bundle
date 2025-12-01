# Makraz Verify Email Change Bundle

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![Tests](https://github.com/makraz/verify-email-change-bundle/workflows/Tests/badge.svg)](https://github.com/makraz/verify-email-change-bundle/actions)

A Symfony bundle that provides secure email address change functionality with verification.

## Features

- 🔐 **Cryptographically Secure**: Uses selector + hashed token pattern to prevent timing attacks
- ⏱️ **Configurable Expiration**: Set custom lifetimes for verification links
- 🚫 **Built-in Throttling**: Prevents abuse with configurable rate limiting
- 📧 **Flexible**: You control email sending, UI, and password verification
- 🎨 **Twig Integration**: Built-in Twig functions for checking pending email changes
- 🧪 **Well Tested**: Comprehensive test suite with 203 tests
- 📝 **Event-Driven**: Dispatches events for extensibility

## Installation

```bash
composer require makraz/verify-email-change-bundle
```

If you're not using Symfony Flex, enable the bundle manually:

```php
// config/bundles.php
return [
    // ...
    Makraz\Bundle\VerifyEmailChange\MakrazVerifyEmailChangeBundle::class => ['all' => true],
];
```

## Quick Start

### Step 1: Update Your User Entity

Your User entity must implement `EmailChangeInterface`:

```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeInterface;

#[ORM\Entity]
class User implements EmailChangeInterface
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

    // Required by EmailChangeInterface
    public function hasPendingEmailChange(): bool
    {
        // Implement this or use Twig functions (recommended)
        return false;
    }

    public function getPendingEmail(): ?string
    {
        // Implement this or use Twig functions (recommended)
        return null;
    }
}
```

> **Note:** The `hasPendingEmailChange()` and `getPendingEmail()` methods are required by the interface but can return default values if you use the built-in Twig functions (recommended approach - see below).

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
            // Optional: Validate the new email
            if ($newEmail === $user->getEmail()) {
                throw new \Makraz\Bundle\VerifyEmailChange\Exception\SameEmailException($newEmail);
            }

            // Optional: Check if email is already in use
            $existingUser = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $newEmail]);
            if ($existingUser) {
                throw new \Makraz\Bundle\VerifyEmailChange\Exception\EmailAlreadyInUseException($newEmail);
            }

            // Generate the verification signature
            $signature = $this->emailChangeHelper->generateSignature(
                'app_email_change_verify', // Your verify route name
                $user,
                $newEmail
            );

            // Persist the pending email change
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
            // Validate the link and get the user
            $user = $this->emailChangeHelper->validateTokenAndFetchUser($request);

            // Complete the email change
            $oldEmail = $this->emailChangeHelper->confirmEmailChange($user);

            // Persist changes
            $this->entityManager->flush();

            // Send notification to OLD email (optional but recommended)
            $email = (new Email())
                ->to($oldEmail)
                ->subject('Your email address was changed')
                ->html('Your email address has been successfully changed. If you did not make this change, contact support immediately.');

            $this->mailer->send($email);

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

### Alternative: Using the Helper Service

You can also check for pending changes directly in your controller:

```php
public function profile(EmailChangeHelper $helper): Response
{
    $user = $this->getUser();

    if ($helper->hasPendingEmailChange($user)) {
        $pendingEmail = $helper->getPendingEmail($user);
        // ... use in your response
    }

    return $this->render('profile.html.twig');
}
```

## How It Works

### Flow Diagram

```
User requests email change
    ↓
EmailChangeHelper::generateSignature()
    ↓
Token created & stored (hashed)
    ↓
Verification email sent to NEW address
    ↓
User clicks link
    ↓
EmailChangeHelper::validateTokenAndFetchUser()
    ↓
Token validated (timing-safe comparison)
    ↓
EmailChangeHelper::confirmEmailChange()
    ↓
Email updated, request deleted
    ↓
Notification sent to OLD address
```

## API Reference

### EmailChangeHelper

#### `generateSignature()`

Generate a signed verification URL for an email change request.

```php
public function generateSignature(
    string $routeName,
    EmailChangeInterface $user,
    string $newEmail,
    array $extraParams = []
): EmailChangeSignature
```

**Parameters:**
- `$routeName`: The route name for your verification endpoint
- `$user`: The user requesting the change
- `$newEmail`: The new email address
- `$extraParams`: Additional route parameters (optional)

**Returns:** `EmailChangeSignature` with the signed URL and expiration info

**Throws:**
- `TooManyEmailChangeRequestsException`: If user has a recent pending request

#### `validateTokenAndFetchUser()`

Validate an email change request from URL parameters.

```php
public function validateTokenAndFetchUser(Request $request): EmailChangeInterface
```

**Returns:** The user who initiated the email change

**Throws:**
- `ExpiredEmailChangeRequestException`: If the link has expired
- `InvalidEmailChangeRequestException`: If the link is invalid

#### `confirmEmailChange()`

Complete the email change after validation.

```php
public function confirmEmailChange(EmailChangeInterface $user): string
```

**Returns:** The user's old email address (for notifications)

**Throws:**
- `InvalidEmailChangeRequestException`: If no pending request exists

#### `cancelEmailChange()`

Cancel a pending email change.

```php
public function cancelEmailChange(EmailChangeInterface $user): void
```

#### `hasPendingEmailChange()`

Check if a user has a pending email change request.

```php
public function hasPendingEmailChange(EmailChangeInterface $user): bool
```

**Returns:** `true` if the user has a non-expired pending request, `false` otherwise

#### `getPendingEmail()`

Get the pending new email address for a user.

```php
public function getPendingEmail(EmailChangeInterface $user): ?string
```

**Returns:** The pending new email address, or `null` if no pending request exists

#### `hasPendingRequest()`

Check if a user has a pending email change request (returns full request object).

```php
public function hasPendingRequest(EmailChangeInterface $user): bool
```

**Returns:** `true` if the user has a non-expired pending request, `false` otherwise

#### `getPendingRequest()`

Get the full pending email change request for a user.

```php
public function getPendingRequest(EmailChangeInterface $user): ?EmailChangeRequest
```

**Returns:** The `EmailChangeRequest` object, or `null` if no pending request exists

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

        // Send custom email, log, etc.
    }
}
```

### `EmailChangeConfirmedEvent`

Dispatched when an email change is confirmed.

```php
use Makraz\Bundle\VerifyEmailChange\Event\EmailChangeConfirmedEvent;

#[AsEventListener]
class EmailChangeConfirmedListener
{
    public function __invoke(EmailChangeConfirmedEvent $event): void
    {
        $user = $event->getUser();
        $oldEmail = $event->getOldEmail();
        $newEmail = $event->getNewEmail();

        // Send notification to old email, reset email verification, etc.
    }
}
```

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

All exceptions implement `VerifyEmailChangeExceptionInterface` and can be caught using this interface or individually.

### `EmailAlreadyInUseException`

Thrown when attempting to change to an email address that is already in use by another user.

**Usage:**
```php
use Makraz\Bundle\VerifyEmailChange\Exception\EmailAlreadyInUseException;

// Check if email is already in use
$existingUser = $entityManager->getRepository(User::class)
    ->findOneBy(['email' => $newEmail]);

if ($existingUser) {
    throw new EmailAlreadyInUseException($newEmail);
}
```

**Error Message:** "This email address is already in use."

### `SameEmailException`

Thrown when attempting to change to the same email address as the current one.

**Usage:**
```php
use Makraz\Bundle\VerifyEmailChange\Exception\SameEmailException;

if ($newEmail === $user->getEmail()) {
    throw new SameEmailException($newEmail);
}
```

**Error Message:** "The new email address is identical to the current one."

### `TooManyEmailChangeRequestsException`

Thrown when a user tries to create a new email change request too soon after a previous one.

**Error Message:** "Please wait before requesting another email change."

### `ExpiredEmailChangeRequestException`

Thrown when attempting to verify an email change link that has expired.

**Error Message:** "The email change link has expired."

### `InvalidEmailChangeRequestException`

Thrown when the email change verification link is invalid or the request doesn't exist.

**Error Message:** Varies depending on the specific validation failure.

### Catching All Exceptions

```php
use Makraz\Bundle\VerifyEmailChange\Exception\VerifyEmailChangeExceptionInterface;

try {
    // Your email change logic
} catch (VerifyEmailChangeExceptionInterface $e) {
    $this->addFlash('error', $e->getReason());
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

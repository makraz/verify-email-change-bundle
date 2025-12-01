# Make:change-email-form Command

The `make:change-email-form` command is a code generator that creates email templates and Twig forms for implementing email change functionality in your Symfony application.

## Table of Contents

- [Installation](#installation)
- [Usage](#usage)
- [Generated Files](#generated-files)
- [Options](#options)
- [Examples](#examples)
- [Customization](#customization)

## Installation

The command is automatically available once you install the bundle:

```bash
composer require makraz/verify-email-change-bundle
```

## Usage

### Basic Usage

```bash
php bin/console make:change-email-form
```

This generates three template files with default paths:
- `templates/email/email_change_verification.html.twig`
- `templates/email/email_change_notification.html.twig`
- `templates/profile/change_email.html.twig`

### Help

```bash
php bin/console make:change-email-form --help
```

## Generated Files

### 1. Verification Email Template

**File**: `templates/email/email_change_verification.html.twig`

**Purpose**: Sent to the NEW email address to verify ownership

**Variables**:
- `signature` - EmailChangeSignature object containing:
  - `signedUrl` - The verification URL
  - `expiresAt` - Expiration timestamp
- `user` - User object
- `app_name` (optional) - Your application name

**Features**:
- Responsive HTML email design
- Professional gradient header
- Clear call-to-action button
- Expiration warning
- Security notices
- Accessible link fallback

**Preview**:
```twig
{# Verification email sent to: new@example.com #}
Subject: Verify Your Email Change
Button: "Verify New Email Address"
Link expires in: 1 hour
```

### 2. Notification Email Template

**File**: `templates/email/email_change_notification.html.twig`

**Purpose**: Sent to the OLD email address after successful change

**Variables**:
- `old_email` - Previous email address
- `new_email` - New email address
- `changed_at` - DateTime when change occurred
- `support_url` (optional) - Support contact URL
- `app_name` (optional) - Your application name

**Features**:
- Success confirmation message
- Change details table (old/new email, timestamp)
- Security warning for unauthorized changes
- Contact support button
- Professional design

**Preview**:
```twig
{# Notification sent to: old@example.com #}
Subject: Email Address Changed
Message: Your email has been changed to new@example.com
Security: "Didn't make this change? Contact support"
```

### 3. Email Change Request Form

**File**: `templates/profile/change_email.html.twig`

**Purpose**: User-facing form to request email change

**Features**:
- Tailwind CSS styling (via CDN or extends base template)
- Current email display (disabled field)
- New email input with validation
- Password confirmation for security
- Pending change indicator
- Flash message support (success/error)
- Cancel pending request option
- Security tips section
- Process explanation

**Form Fields**:
- `new_email` - Required email input
- `password` - Required password confirmation

**Routes Used**:
- `app_email_change_request` - Form submission
- `app_email_change_cancel` - Cancel pending change
- `app_profile` - Back to profile

## Options

### `--templates-dir` / `-t`

Specify custom directory for email templates.

```bash
php bin/console make:change-email-form --templates-dir=templates/emails
```

Default: `templates/email`

### `--form-dir` / `-f`

Specify custom directory for the form template.

```bash
php bin/console make:change-email-form --form-dir=templates/user
```

Default: `templates/profile`

### `--overwrite` / `-o`

Overwrite existing files without prompting.

```bash
php bin/console make:change-email-form --overwrite
```

Without this flag, existing files are skipped.

## Examples

### Example 1: Basic Usage

```bash
php bin/console make:change-email-form
```

**Output**:
```
Email Change Template Generator
================================

 [OK] Templates generated successfully!

Generated Files
---------------
  ✓ templates/email/email_change_verification.html.twig
  ✓ templates/email/email_change_notification.html.twig
  ✓ templates/profile/change_email.html.twig

Next Steps
----------
 * Update the email templates with your branding and styling
 * Customize the form template to match your application design
 * Use EmailChangeHelper in your controller to handle email changes
 * Implement email sending using Symfony Mailer
```

### Example 2: Custom Directories

```bash
php bin/console make:change-email-form \
    --templates-dir=templates/emails/account \
    --form-dir=templates/account/settings
```

### Example 3: Regenerate and Overwrite

```bash
php bin/console make:change-email-form --overwrite
```

### Example 4: Integration with Existing Base Template

If you have `templates/base.html.twig`, the form automatically extends it:

```twig
{% extends 'base.html.twig' %}

{% block title %}Change Email Address{% endblock %}

{% block body %}
    {# Form content here #}
{% endblock %}
```

Otherwise, it generates a standalone HTML template with Tailwind CSS.

## Customization

### Customizing Email Templates

After generation, you can customize the templates:

#### Add Your Branding

```twig
{# templates/email/email_change_verification.html.twig #}

<div class="header">
    <img src="{{ asset('images/logo.png') }}" alt="Logo">
    <h1>Verify Your Email Change</h1>
</div>
```

#### Change Colors

```css
.header {
    background: linear-gradient(135deg, #YOUR_COLOR_1 0%, #YOUR_COLOR_2 100%);
}

.button {
    background: #YOUR_BRAND_COLOR;
}
```

#### Add Custom Variables

```twig
<p>
    Hi {{ user.firstName|default(user.email) }},
</p>

<p>
    This email was sent from {{ app.request.clientIp|default('your account') }}.
</p>
```

### Customizing the Form Template

#### Add Custom Validation

```twig
<div>
    <label for="new_email">New Email Address *</label>
    <input
        type="email"
        id="new_email"
        name="new_email"
        required
        pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
        data-validate="email"
    >
    <span class="error" id="email-error"></span>
</div>
```

#### Add JavaScript Confirmation

```javascript
document.querySelector('form').addEventListener('submit', function(e) {
    if (!confirm('Are you sure you want to change your email address?')) {
        e.preventDefault();
    }
});
```

#### Integrate with Symfony Form Component

Replace the plain HTML form with Symfony Form:

```twig
{{ form_start(form) }}
    {{ form_row(form.newEmail) }}
    {{ form_row(form.password) }}

    <button type="submit">Send Verification Email</button>
{{ form_end(form) }}
```

## Using Generated Templates in Your Controller

### Controller Example

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

        if ($request->isMethod('POST')) {
            $newEmail = $request->request->get('new_email');
            $password = $request->request->get('password');

            // Verify password (implement your own logic)
            if (!$this->passwordVerifier->verify($user, $password)) {
                $this->addFlash('error', 'Invalid password');
                return $this->redirectToRoute('app_email_change_request');
            }

            try {
                // Generate signature
                $signature = $this->emailChangeHelper->generateSignature(
                    'app_email_change_verify',
                    $user,
                    $newEmail
                );

                $this->entityManager->flush();

                // Send verification email using generated template
                $email = (new Email())
                    ->to($newEmail)
                    ->subject('Verify Your New Email Address')
                    ->htmlTemplate('email/email_change_verification.html.twig')
                    ->context([
                        'signature' => $signature,
                        'user' => $user,
                        'app_name' => 'My Application',
                    ]);

                $this->mailer->send($email);

                $this->addFlash('success', 'Verification email sent! Check your inbox.');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_profile');
        }

        // Render the generated form template
        return $this->render('profile/change_email.html.twig');
    }

    #[Route('/account/email/verify', name: 'app_email_change_verify')]
    public function verify(Request $request): Response
    {
        try {
            $user = $this->emailChangeHelper->validateTokenAndFetchUser($request);
            $oldEmail = $this->emailChangeHelper->confirmEmailChange($user);

            $this->entityManager->flush();

            // Send notification to old email using generated template
            $email = (new Email())
                ->to($oldEmail)
                ->subject('Your Email Address Was Changed')
                ->htmlTemplate('email/email_change_notification.html.twig')
                ->context([
                    'old_email' => $oldEmail,
                    'new_email' => $user->getEmail(),
                    'changed_at' => new \DateTime(),
                    'support_url' => $this->generateUrl('app_support', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    'app_name' => 'My Application',
                ]);

            $this->mailer->send($email);

            $this->addFlash('success', 'Email changed successfully!');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
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

## Template Variables Reference

### Verification Email

| Variable | Type | Description |
|----------|------|-------------|
| `signature.signedUrl` | string | Full verification URL |
| `signature.expiresAt` | DateTimeImmutable | Expiration timestamp |
| `user.email` | string | User's current email |
| `app_name` | string | Application name (optional) |

### Notification Email

| Variable | Type | Description |
|----------|------|-------------|
| `old_email` | string | Previous email address |
| `new_email` | string | New email address |
| `changed_at` | DateTime | When change occurred |
| `support_url` | string | Support contact URL (optional) |
| `app_name` | string | Application name (optional) |

### Form Template

| Variable | Type | Description |
|----------|------|-------------|
| `app.user.email` | string | Current email address |
| `app.user.pendingEmail` | string\|null | Pending email if exists |
| `app.flashes('success')` | array | Success messages |
| `app.flashes('error')` | array | Error messages |

## Troubleshooting

### Templates Not Found

If Symfony can't find the templates:

1. Clear the cache: `php bin/console cache:clear`
2. Verify file paths match your configuration
3. Check `config/packages/twig.yaml` for custom paths

### Styling Issues

If the form doesn't look right:

1. **With Tailwind CSS**: Ensure CDN is loaded or Tailwind is installed
2. **Custom styles**: Add your own CSS or integrate with your design system
3. **Base template**: Extend your existing base template for consistency

### Route Not Found

Ensure you've created the required routes:
- `app_email_change_request` - Show form and handle submission
- `app_email_change_verify` - Verify email change
- `app_email_change_cancel` - Cancel pending change
- `app_profile` - User profile page

## Best Practices

1. **Branding**: Customize colors, logos, and text to match your brand
2. **Security**: Always verify the user's password before allowing email changes
3. **Testing**: Test email delivery with [MailHog](https://github.com/mailhog/MailHog) or [Mailpit](https://github.com/axllent/mailpit)
4. **Localization**: Use translation filters for multilingual support
5. **Rate Limiting**: Implement rate limiting on the form to prevent abuse
6. **Logging**: Log all email change attempts for security auditing

## Related Documentation

- [Bundle README](../README.md)
- [Getting Started Guide](../GETTING_STARTED.md)
- [API Reference](../README.md#api-reference)
- [Contributing Guide](../CONTRIBUTING.md)

## Support

For issues or questions:
- [GitHub Issues](https://github.com/Makraz/verify-email-change-bundle/issues)
- [Documentation](https://github.com/Makraz/verify-email-change-bundle)

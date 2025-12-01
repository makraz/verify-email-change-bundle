# Make:change-email-form Command

A Symfony console command that generates production-ready email templates and Twig forms for email change functionality.

## Quick Start

```bash
php bin/console make:change-email-form
```

This generates:
- **Verification email template** (sent to new email)
- **Notification email template** (sent to old email)
- **Email change form** (Twig template)

## What Gets Generated

### 1. Verification Email (`templates/email/email_change_verification.html.twig`)

Professional HTML email sent to the **new** email address:
- Responsive design with gradient header
- Clear verification button
- Expiration warning (1 hour)
- Security notices
- Link fallback for email clients

**Variables:**
- `signature.signedUrl` - Verification URL
- `signature.expiresAt` - Expiration timestamp
- `user.email` - Current email
- `app_name` (optional) - Application name

### 2. Notification Email (`templates/email/email_change_notification.html.twig`)

Confirmation email sent to the **old** email address:
- Success confirmation
- Old/new email details
- Timestamp
- Security warning
- Support contact button

**Variables:**
- `old_email` - Previous email
- `new_email` - New email
- `changed_at` - Change timestamp
- `support_url` (optional) - Support URL
- `app_name` (optional) - Application name

### 3. Email Change Form (`templates/profile/change_email.html.twig`)

User-facing form with:
- Current email display (disabled)
- New email input
- Password confirmation
- Pending change indicator
- Flash messages
- Security tips
- Tailwind CSS styling (or extends base template)

## Command Options

```bash
--templates-dir, -t    Custom email templates directory (default: templates/email)
--form-dir, -f         Custom form directory (default: templates/profile)
--overwrite, -o        Overwrite existing files
```

## Examples

### Basic Usage
```bash
php bin/console make:change-email-form
```

### Custom Directories
```bash
php bin/console make:change-email-form \
    --templates-dir=templates/emails/account \
    --form-dir=templates/user/settings
```

### Overwrite Existing
```bash
php bin/console make:change-email-form --overwrite
```

## Integration Example

After generating templates, create a controller:

```php
use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeHelper;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/account/email/change', name: 'app_email_change_request')]
public function request(Request $request): Response
{
    $user = $this->getUser();

    if ($request->isMethod('POST')) {
        $newEmail = $request->request->get('new_email');

        // Generate signature
        $signature = $this->emailChangeHelper->generateSignature(
            'app_email_change_verify',
            $user,
            $newEmail
        );

        $this->entityManager->flush();

        // Send verification email
        $email = (new Email())
            ->to($newEmail)
            ->subject('Verify Your New Email Address')
            ->htmlTemplate('email/email_change_verification.html.twig')
            ->context([
                'signature' => $signature,
                'user' => $user,
                'app_name' => 'My App',
            ]);

        $this->mailer->send($email);

        $this->addFlash('success', 'Verification email sent!');
        return $this->redirectToRoute('app_profile');
    }

    return $this->render('profile/change_email.html.twig');
}
```

## Features

✓ **Smart Detection**: Automatically extends `base.html.twig` if available
✓ **Directory Creation**: Creates directories if they don't exist
✓ **Overwrite Protection**: Skips existing files unless `--overwrite` is used
✓ **Professional Design**: Production-ready templates with modern styling
✓ **Security Built-in**: Password confirmation, expiration, security notices
✓ **Accessibility**: Semantic HTML, proper labels, keyboard navigation
✓ **Responsive**: Works on all devices
✓ **Customizable**: Easy to modify colors, branding, and content

## Customization

### Change Colors

Edit the generated templates:

```css
.header {
    background: linear-gradient(135deg, #YOUR_PRIMARY 0%, #YOUR_SECONDARY 100%);
}

.button {
    background: #YOUR_BRAND_COLOR;
}
```

### Add Logo

```twig
<div class="header">
    <img src="{{ asset('images/logo.png') }}" alt="Logo">
    <h1>Verify Your Email Change</h1>
</div>
```

### Localization

```twig
<p>{{ 'email_change.greeting'|trans({'%name%': user.email}) }}</p>
```

## Testing

The command includes comprehensive tests:

```bash
vendor/bin/phpunit tests/Command/MakeChangeEmailFormCommandTest.php
```

**Test Coverage:**
- ✓ Command execution
- ✓ File generation
- ✓ Custom directories
- ✓ Overwrite functionality
- ✓ Valid Twig syntax
- ✓ Required variables
- ✓ Security features

## Troubleshooting

**Templates not found?**
```bash
php bin/console cache:clear
php bin/console debug:twig
```

**Emails not sending?**
```yaml
# config/packages/mailer.yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
```

**Need different styling?**
- Edit generated templates
- Replace Tailwind with Bootstrap
- Use your existing CSS framework

## Documentation

- [Full Command Reference](../../docs/MAKE_COMMAND.md)
- [Quick Example Guide](../../docs/COMMAND_EXAMPLE.md)
- [Bundle Documentation](../../README.md)

## Architecture

The command:
1. Accepts options for custom directories
2. Creates directories if needed
3. Generates three template files
4. Detects `base.html.twig` for form template
5. Displays helpful output with next steps
6. Provides example controller code

**Design Principles:**
- Single responsibility
- Fail-safe operations
- Clear error messages
- Helpful output
- Extensible architecture

## Credits

Inspired by Symfony Maker Bundle and modern email design best practices.

## License

MIT License - Part of makraz/verify-email-change-bundle

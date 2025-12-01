# Make:change-email-form Command - Quick Example

This guide shows you how to quickly set up email change functionality using the `make:change-email-form` command.

## Step 1: Run the Command

```bash
php bin/console make:change-email-form
```

**Output:**
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

Example Controller Usage
-------------------------
  // In your controller:
  $signature = $this->emailChangeHelper->generateSignature(
      'app_email_change_verify',
      $user,
      $newEmail
  );

  $email = (new Email())
      ->to($newEmail)
      ->subject('Verify your new email address')
      ->htmlTemplate('templates/email/email_change_verification.html.twig')
      ->context([
          'signature' => $signature,
          'user' => $user,
      ]);
```

## Step 2: Review Generated Files

### Verification Email Template
**Path:** `templates/email/email_change_verification.html.twig`

```twig
{# Preview #}
Subject: Verify Your Email Change

Hello user@example.com,

You recently requested to change your email address.
To complete this process, please verify your new email
address by clicking the button below:

[Verify New Email Address]

⏱️ This link will expire in 1 hour
```

### Notification Email Template
**Path:** `templates/email/email_change_notification.html.twig`

```twig
{# Preview #}
Subject: Email Address Changed

✅ Your email address has been successfully changed.

Previous Email: old@example.com
New Email:      new@example.com
Changed At:     December 4, 2024 at 2:30 PM

🚨 Didn't make this change?
If you did NOT authorize this email change, your
account may have been compromised.
[Contact Support Immediately]
```

### Email Change Form
**Path:** `templates/profile/change_email.html.twig`

Preview:
```
┌─────────────────────────────────────────┐
│     Change Email Address                │
├─────────────────────────────────────────┤
│                                         │
│ Current Email Address                   │
│ ┌─────────────────────────────────┐   │
│ │ user@example.com (disabled)     │   │
│ └─────────────────────────────────┘   │
│                                         │
│ New Email Address *                     │
│ ┌─────────────────────────────────┐   │
│ │ your.new.email@example.com      │   │
│ └─────────────────────────────────┘   │
│ A verification email will be sent       │
│                                         │
│ Confirm Your Password *                 │
│ ┌─────────────────────────────────┐   │
│ │ ••••••••••••                    │   │
│ └─────────────────────────────────┘   │
│ For security, confirm your password     │
│                                         │
│ What happens next?                      │
│ 1. We'll send a verification email     │
│ 2. Click the link to confirm           │
│ 3. Your email will be updated          │
│ 4. We'll notify your old email         │
│                                         │
│ [Send Verification Email]  [Cancel]    │
│                                         │
└─────────────────────────────────────────┘
```

## Step 3: Create Controller

Create `src/Controller/EmailChangeController.php`:

```php
<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailChangeController extends AbstractController
{
    public function __construct(
        private readonly EmailChangeHelper $emailChangeHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Route('/account/email/change', name: 'app_email_change_request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $newEmail = $request->request->get('new_email');
            $password = $request->request->get('password');

            // Verify password
            if (!$this->passwordHasher->isPasswordValid($user, $password)) {
                $this->addFlash('error', 'Invalid password. Please try again.');
                return $this->redirectToRoute('app_email_change_request');
            }

            try {
                // Generate verification signature
                $signature = $this->emailChangeHelper->generateSignature(
                    'app_email_change_verify',
                    $user,
                    $newEmail
                );

                $this->entityManager->flush();

                // Send verification email to NEW address
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

                $this->addFlash('success', 'Verification email sent! Check your inbox at '.$newEmail);
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_email_change_request');
        }

        return $this->render('profile/change_email.html.twig');
    }

    #[Route('/account/email/verify', name: 'app_email_change_verify')]
    public function verify(Request $request): Response
    {
        try {
            // Validate token and get user
            $user = $this->emailChangeHelper->validateTokenAndFetchUser($request);

            // Confirm the email change
            $oldEmail = $this->emailChangeHelper->confirmEmailChange($user);

            $this->entityManager->flush();

            // Send notification to OLD email
            $email = (new Email())
                ->to($oldEmail)
                ->subject('Your Email Address Was Changed')
                ->htmlTemplate('email/email_change_notification.html.twig')
                ->context([
                    'old_email' => $oldEmail,
                    'new_email' => $user->getEmail(),
                    'changed_at' => new \DateTime(),
                    'support_url' => $this->generateUrl('app_support', [], UrlGeneratorInterface::ABSOLUTE_URL),
                    'app_name' => 'My App',
                ]);

            $this->mailer->send($email);

            $this->addFlash('success', 'Email changed successfully to '.$user->getEmail().'!');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
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

        $this->addFlash('success', 'Email change request cancelled.');

        return $this->redirectToRoute('app_email_change_request');
    }
}
```

## Step 4: Test Your Implementation

### 1. Access the Form

Visit: `http://localhost:8000/account/email/change`

### 2. Request Email Change

- Enter new email: `newemail@example.com`
- Enter password: `yourpassword`
- Click "Send Verification Email"

### 3. Check Email

**Subject:** Verify Your New Email Address
**To:** newemail@example.com

```
Hello user@example.com,

You recently requested to change your email address...

[Verify New Email Address]
```

### 4. Click Verification Link

The link looks like:
```
https://yourapp.com/account/email/verify?selector=abc123...&token=xyz789...
```

### 5. Confirmation

After clicking, you'll see:
- Success message: "Email changed successfully!"
- Your email is now: `newemail@example.com`

### 6. Old Email Notification

**Subject:** Your Email Address Was Changed
**To:** user@example.com

```
✅ Your email address has been successfully changed.

Previous Email: user@example.com
New Email:      newemail@example.com
Changed At:     December 4, 2024 at 2:30 PM
```

## Customization Examples

### Add Your Logo

Edit `templates/email/email_change_verification.html.twig`:

```twig
<div class="header">
    <img src="{{ asset('images/logo.png') }}" alt="{{ app_name }}" style="height: 40px; margin-bottom: 10px;">
    <h1>Verify Your Email Change</h1>
</div>
```

### Change Brand Colors

```css
.header {
    background: linear-gradient(135deg, #YOUR_PRIMARY 0%, #YOUR_SECONDARY 100%);
}

.button {
    background: #YOUR_PRIMARY;
}
```

### Add Custom Fields

Add a "reason" field to the form:

```twig
<div>
    <label for="reason">Reason for Change (Optional)</label>
    <textarea
        id="reason"
        name="reason"
        rows="3"
        placeholder="Why are you changing your email?"
    ></textarea>
</div>
```

## Common Workflows

### Workflow 1: Simple Email Change
1. User fills form → 2. Verification email sent → 3. User clicks link → 4. Email changed → 5. Notification sent

### Workflow 2: Cancel Pending Change
1. User has pending change → 2. Clicks "Cancel pending change" → 3. Request removed

### Workflow 3: Expired Link
1. User waits > 1 hour → 2. Clicks expired link → 3. Error: "Link expired" → 4. Must request new change

## Troubleshooting

**Problem:** Templates not found

**Solution:**
```bash
php bin/console cache:clear
php bin/console debug:twig  # Verify template paths
```

---

**Problem:** Emails not sending

**Solution:** Check Mailer configuration:
```yaml
# config/packages/mailer.yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'  # e.g., smtp://localhost:1025 for Mailpit
```

---

**Problem:** Password validation fails

**Solution:** Ensure you're using the correct password hasher:
```php
$this->passwordHasher->isPasswordValid($user, $password)
```

## Next Steps

- 📚 [Read full command documentation](MAKE_COMMAND.md)
- 🎨 [Customize email templates](#customization-examples)
- 🔒 [Add two-factor authentication](../README.md)
- 📧 [Configure production email provider](https://symfony.com/doc/current/mailer.html)

## Complete Example Repository

For a complete working example, check out:
[https://github.com/Makraz/verify-email-change-example](https://github.com/Makraz/verify-email-change-example)

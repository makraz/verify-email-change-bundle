# Getting Started with Makraz Verify Email Change Bundle

This guide will walk you through integrating the bundle into your Symfony application.

## Prerequisites

- Symfony 6.4 or 7.0+
- PHP 8.1+
- Doctrine ORM

## Installation Steps

### 1. Install the Bundle

```bash
composer require makraz/verify-email-change-bundle
```

### 2. Update Your User Entity

Add the interface and trait to your User entity:

```php
<?php

namespace App\Entity;

use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeInterface;

class User implements EmailChangeInterface
{
    // ... rest of your entity
}
```

### 3. Create Database Table

Generate and run the migration:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

The migration will create the `email_change_request` table.

### 4. Create Email Change Controller

Create `src/Controller/EmailChangeController.php`:

```php
<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangeEmailType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeHelper;
use Makraz\Bundle\VerifyEmailChange\Exception\VerifyEmailChangeExceptionInterface;

#[IsGranted('ROLE_USER')]
class EmailChangeController extends AbstractController
{
    public function __construct(
        private readonly EmailChangeHelper $emailChangeHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
    ) {}

    #[Route('/account/email/change', name: 'app_email_change_request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangeEmailType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newEmail = $form->get('newEmail')->getData();

            try {
                // Generate verification signature
                $signature = $this->emailChangeHelper->generateSignature(
                    'app_email_change_verify',
                    $user,
                    $newEmail
                );

                // Save pending email
                $this->entityManager->flush();

                // Send verification email
                $email = (new Email())
                    ->to($newEmail)
                    ->subject('Verify your new email address')
                    ->html($this->renderView('email/email_change_verification.html.twig', [
                        'user' => $user,
                        'signedUrl' => $signature->getSignedUrl(),
                        'expiresAt' => $signature->getExpiresAt(),
                    ]));

                $this->mailer->send($email);

                $this->addFlash('success', sprintf(
                    'A verification email has been sent to %s. Please check your inbox.',
                    $newEmail
                ));

                return $this->redirectToRoute('app_profile');
            } catch (VerifyEmailChangeExceptionInterface $e) {
                $this->addFlash('error', $e->getReason());
            }
        }

        return $this->render('account/email_change_request.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/account/email/verify', name: 'app_email_change_verify', methods: ['GET'])]
    public function verify(Request $request): Response
    {
        try {
            // Validate token and get user
            $user = $this->emailChangeHelper->validateTokenAndFetchUser($request);

            // Complete email change
            $oldEmail = $this->emailChangeHelper->confirmEmailChange($user);

            // Persist changes
            $this->entityManager->flush();

            // Send notification to old email
            $email = (new Email())
                ->to($oldEmail)
                ->subject('Your email address was changed')
                ->html($this->renderView('email/email_changed_notification.html.twig', [
                    'user' => $user,
                    'oldEmail' => $oldEmail,
                    'newEmail' => $user->getEmail(),
                ]));

            $this->mailer->send($email);

            $this->addFlash('success', 'Your email address has been successfully changed!');

            return $this->redirectToRoute('app_profile');
        } catch (VerifyEmailChangeExceptionInterface $e) {
            $this->addFlash('error', $e->getReason());
            return $this->redirectToRoute('app_email_change_request');
        }
    }

    #[Route('/account/email/cancel', name: 'app_email_change_cancel', methods: ['POST'])]
    public function cancel(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->emailChangeHelper->cancelEmailChange($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'Email change request cancelled.');

        return $this->redirectToRoute('app_profile');
    }
}
```

### 5. Create the Form

Create `src/Form/ChangeEmailType.php`:

```php
<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;

class ChangeEmailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('newEmail', EmailType::class, [
                'label' => 'New Email Address',
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                ],
            ])
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Current Password',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(),
                    new UserPassword(),
                ],
            ]);
    }
}
```

### 6. Create Email Templates

Create `templates/email/email_change_verification.html.twig`:

```twig
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verify Your New Email Address</title>
</head>
<body>
    <h1>Verify Your New Email Address</h1>

    <p>Hello {{ user.firstName ?? 'there' }},</p>

    <p>You requested to change your email address. Please click the link below to verify your new email:</p>

    <p>
        <a href="{{ signedUrl }}">Verify New Email Address</a>
    </p>

    <p><small>This link will expire at {{ expiresAt|date('Y-m-d H:i:s') }}</small></p>

    <p>If you didn't request this change, please ignore this email.</p>
</body>
</html>
```

Create `templates/email/email_changed_notification.html.twig`:

```twig
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Your Email Was Changed</title>
</head>
<body>
    <h1>Your Email Address Was Changed</h1>

    <p>Hello {{ user.firstName ?? 'there' }},</p>

    <p>This is a notification that your email address has been successfully changed.</p>

    <ul>
        <li><strong>Old Email:</strong> {{ oldEmail }}</li>
        <li><strong>New Email:</strong> {{ newEmail }}</li>
    </ul>

    <p style="color: red;">
        <strong>Important:</strong> If you did not make this change, please contact support immediately!
    </p>
</body>
</html>
```

### 7. Update Your Profile Template

Add email change UI to your profile page. You have two options:

#### Option A: Using Twig Functions (Recommended)

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
            <button type="submit">Cancel Email Change</button>
        </form>
    </div>
{% else %}
    <a href="{{ path('app_email_change_request') }}">Change Email Address</a>
{% endif %}
```

#### Option B: Implementing Methods on User Entity

Add these methods to your User entity:

```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeInterface;
use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;

#[ORM\Entity]
class User implements EmailChangeInterface
{
    // ... your existing properties and methods

    public function hasPendingEmailChange(): bool
    {
        // This requires a custom query or repository method
        // It's recommended to use Option A (Twig functions) instead
        return false;
    }

    public function getPendingEmail(): ?string
    {
        // This requires a custom query or repository method
        // It's recommended to use Option A (Twig functions) instead
        return null;
    }
}
```

Then use in your template:

```twig
{% if app.user.hasPendingEmailChange() %}
    <p>Pending: {{ app.user.pendingEmail }}</p>
{% endif %}
```

## Configuration (Optional)

Create `config/packages/verify_email_change.yaml`:

```yaml
verify_email_change:
    lifetime: 3600              # 1 hour
    enable_throttling: true
    throttle_limit: 3600        # 1 hour
```

## Testing

1. Log in to your application
2. Navigate to your profile page
3. Click "Change Email Address"
4. Enter a new email and your current password
5. Check the new email inbox for verification link
6. Click the verification link
7. Check the old email inbox for notification

## Next Steps

- Add password verification before allowing email changes
- Customize email templates with your branding
- Add event listeners for custom logic (logging, notifications, etc.)
- Implement rate limiting per IP address
- Add CAPTCHA for additional security

## Troubleshooting

### "The EntityManager is closed"

Make sure you call `$entityManager->flush()` after calling bundle methods that modify entities.

### Tokens are not being validated

Ensure your route parameters are named `selector` and `token`, or customize them using `extraParams`.

### Emails are not being sent

This bundle does NOT send emails. You must implement email sending in your controller using Symfony Mailer.

## Support

For issues or questions, please visit:
https://github.com/Makraz/verify-email-change-bundle/issues

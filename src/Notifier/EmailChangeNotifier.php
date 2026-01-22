<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Notifier;

use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeDualSignature;
use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeSignature;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Optional service that sends email change notification emails using the
 * built-in Twig templates.
 *
 * Requires `symfony/mailer` and `twig/twig` to be installed.
 */
class EmailChangeNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $senderEmail,
        private readonly ?string $senderName = null,
    ) {
    }

    /**
     * Send a verification email to the new email address.
     *
     * In dual verification mode, also sends a confirmation email to the old address.
     */
    public function sendVerificationEmail(
        EmailChangeableInterface $user,
        string $newEmail,
        EmailChangeSignature $signature,
    ): void {
        // Send verification email to new address
        $html = $this->twig->render('@MakrazVerifyEmailChange/email/verify_new_email.html.twig', [
            'signedUrl' => $signature->getSignedUrl(),
            'expiresAt' => $signature->getExpiresAt(),
            'newEmail' => $newEmail,
            'user' => $user,
        ]);

        $email = $this->createEmail()
            ->to($newEmail)
            ->subject('Verify your new email address')
            ->html($html);

        $this->mailer->send($email);

        // In dual mode, also send confirmation to old address
        if ($signature instanceof EmailChangeDualSignature) {
            $this->sendOldEmailConfirmation($user, $newEmail, $signature);
        }
    }

    /**
     * Send a notification to the old email address that the change is complete.
     */
    public function sendEmailChangeConfirmation(
        EmailChangeableInterface $user,
        string $oldEmail,
        string $newEmail,
    ): void {
        $html = $this->twig->render('@MakrazVerifyEmailChange/email/email_change_confirmed.html.twig', [
            'oldEmail' => $oldEmail,
            'newEmail' => $newEmail,
            'user' => $user,
        ]);

        $email = $this->createEmail()
            ->to($oldEmail)
            ->subject('Your email address has been changed')
            ->html($html);

        $this->mailer->send($email);
    }

    /**
     * Send a notification that the email change was cancelled.
     */
    public function sendCancellationNotice(
        EmailChangeableInterface $user,
        string $cancelledEmail,
    ): void {
        $html = $this->twig->render('@MakrazVerifyEmailChange/email/email_change_cancelled.html.twig', [
            'cancelledEmail' => $cancelledEmail,
            'user' => $user,
        ]);

        $email = $this->createEmail()
            ->to($user->getEmail())
            ->subject('Email change cancelled')
            ->html($html);

        $this->mailer->send($email);
    }

    private function sendOldEmailConfirmation(
        EmailChangeableInterface $user,
        string $newEmail,
        EmailChangeDualSignature $signature,
    ): void {
        $html = $this->twig->render('@MakrazVerifyEmailChange/email/confirm_old_email.html.twig', [
            'signedUrl' => $signature->getOldEmailSignedUrl(),
            'expiresAt' => $signature->getExpiresAt(),
            'newEmail' => $newEmail,
            'user' => $user,
        ]);

        $email = $this->createEmail()
            ->to($user->getEmail())
            ->subject('Confirm email address change')
            ->html($html);

        $this->mailer->send($email);
    }

    private function createEmail(): Email
    {
        $email = new Email();

        if ($this->senderName) {
            $email->from(sprintf('%s <%s>', $this->senderName, $this->senderEmail));
        } else {
            $email->from($this->senderEmail);
        }

        return $email;
    }
}

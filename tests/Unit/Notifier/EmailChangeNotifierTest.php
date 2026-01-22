<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\Notifier;

use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeDualSignature;
use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeSignature;
use Makraz\Bundle\VerifyEmailChange\Notifier\EmailChangeNotifier;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EmailChangeNotifierTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private Environment&MockObject $twig;
    private EmailChangeNotifier $notifier;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->notifier = new EmailChangeNotifier(
            $this->mailer,
            $this->twig,
            'noreply@example.com',
            'Example App',
        );
    }

    public function testSendVerificationEmailToNewAddress(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $signature = new EmailChangeSignature(
            'https://example.com/verify?token=abc',
            new \DateTimeImmutable('+1 hour'),
        );

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                '@MakrazVerifyEmailChange/email/verify_new_email.html.twig',
                $this->callback(function (array $params) use ($user, $signature) {
                    return $params['signedUrl'] === $signature->getSignedUrl()
                        && $params['newEmail'] === 'new@example.com'
                        && $params['user'] === $user;
                })
            )
            ->willReturn('<html>Verify</html>');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $to = $email->getTo();
                return count($to) === 1 && $to[0]->getAddress() === 'new@example.com';
            }));

        $this->notifier->sendVerificationEmail($user, 'new@example.com', $signature);
    }

    public function testSendVerificationEmailInDualModeSendsTwoEmails(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $signature = new EmailChangeDualSignature(
            'https://example.com/verify?token=abc',
            'https://example.com/verify?token=def&confirm_old=1',
            new \DateTimeImmutable('+1 hour'),
        );

        $this->twig->expects($this->exactly(2))
            ->method('render')
            ->willReturn('<html>Email</html>');

        $sentEmails = [];
        $this->mailer->expects($this->exactly(2))
            ->method('send')
            ->with($this->callback(function (Email $email) use (&$sentEmails) {
                $sentEmails[] = $email->getTo()[0]->getAddress();
                return true;
            }));

        $this->notifier->sendVerificationEmail($user, 'new@example.com', $signature);

        $this->assertContains('new@example.com', $sentEmails);
        $this->assertContains('old@example.com', $sentEmails);
    }

    public function testSendEmailChangeConfirmation(): void
    {
        $user = new TestUser(1, 'new@example.com');

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                '@MakrazVerifyEmailChange/email/email_change_confirmed.html.twig',
                $this->callback(function (array $params) {
                    return $params['oldEmail'] === 'old@example.com'
                        && $params['newEmail'] === 'new@example.com';
                })
            )
            ->willReturn('<html>Confirmed</html>');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getTo()[0]->getAddress() === 'old@example.com';
            }));

        $this->notifier->sendEmailChangeConfirmation($user, 'old@example.com', 'new@example.com');
    }

    public function testSendCancellationNotice(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                '@MakrazVerifyEmailChange/email/email_change_cancelled.html.twig',
                $this->callback(function (array $params) {
                    return $params['cancelledEmail'] === 'new@example.com';
                })
            )
            ->willReturn('<html>Cancelled</html>');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getTo()[0]->getAddress() === 'old@example.com';
            }));

        $this->notifier->sendCancellationNotice($user, 'new@example.com');
    }

    public function testSenderEmailIsSetCorrectly(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $signature = new EmailChangeSignature(
            'https://example.com/verify?token=abc',
            new \DateTimeImmutable('+1 hour'),
        );

        $this->twig->method('render')->willReturn('<html>Test</html>');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $from = $email->getFrom();
                return count($from) === 1
                    && $from[0]->getAddress() === 'noreply@example.com'
                    && $from[0]->getName() === 'Example App';
            }));

        $this->notifier->sendVerificationEmail($user, 'new@example.com', $signature);
    }

    public function testSenderWithoutNameIsJustEmail(): void
    {
        $notifier = new EmailChangeNotifier(
            $this->mailer,
            $this->twig,
            'noreply@example.com',
        );

        $user = new TestUser(1, 'old@example.com');
        $signature = new EmailChangeSignature(
            'https://example.com/verify?token=abc',
            new \DateTimeImmutable('+1 hour'),
        );

        $this->twig->method('render')->willReturn('<html>Test</html>');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $from = $email->getFrom();
                return count($from) === 1 && $from[0]->getAddress() === 'noreply@example.com';
            }));

        $notifier->sendVerificationEmail($user, 'new@example.com', $signature);
    }

    public function testDualModeRendersCorrectTemplateForOldEmail(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $signature = new EmailChangeDualSignature(
            'https://example.com/verify?token=abc',
            'https://example.com/verify?token=def&confirm_old=1',
            new \DateTimeImmutable('+1 hour'),
        );

        $renderedTemplates = [];
        $this->twig->expects($this->exactly(2))
            ->method('render')
            ->with(
                $this->callback(function (string $template) use (&$renderedTemplates) {
                    $renderedTemplates[] = $template;
                    return true;
                }),
                $this->anything()
            )
            ->willReturn('<html>Email</html>');

        $this->mailer->method('send');

        $this->notifier->sendVerificationEmail($user, 'new@example.com', $signature);

        $this->assertContains('@MakrazVerifyEmailChange/email/verify_new_email.html.twig', $renderedTemplates);
        $this->assertContains('@MakrazVerifyEmailChange/email/confirm_old_email.html.twig', $renderedTemplates);
    }
}

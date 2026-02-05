<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\Event;

use Makraz\Bundle\VerifyEmailChange\Event\EmailChangeAuditEvent;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;

class EmailChangeAuditEventTest extends TestCase
{
    public function testAction(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $event = new EmailChangeAuditEvent($user, EmailChangeAuditEvent::ACTION_INITIATED);

        $this->assertSame(EmailChangeAuditEvent::ACTION_INITIATED, $event->getAction());
        $this->assertSame($user, $event->getUser());
    }

    public function testMetadata(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $event = new EmailChangeAuditEvent($user, EmailChangeAuditEvent::ACTION_CONFIRMED, [
            'old_email' => 'old@example.com',
            'new_email' => 'new@example.com',
            'ip_address' => '192.168.1.1',
        ]);

        $this->assertSame('old@example.com', $event->getMetadataValue('old_email'));
        $this->assertSame('192.168.1.1', $event->getIpAddress());
        $this->assertNull($event->getUserAgent());
    }

    public function testMetadataDefaultValue(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $event = new EmailChangeAuditEvent($user, EmailChangeAuditEvent::ACTION_CANCELLED);

        $this->assertNull($event->getMetadataValue('nonexistent'));
        $this->assertSame('default', $event->getMetadataValue('nonexistent', 'default'));
    }

    public function testAllActionConstants(): void
    {
        $this->assertSame('initiated', EmailChangeAuditEvent::ACTION_INITIATED);
        $this->assertSame('verified', EmailChangeAuditEvent::ACTION_VERIFIED);
        $this->assertSame('confirmed', EmailChangeAuditEvent::ACTION_CONFIRMED);
        $this->assertSame('cancelled', EmailChangeAuditEvent::ACTION_CANCELLED);
        $this->assertSame('failed_verification', EmailChangeAuditEvent::ACTION_FAILED_VERIFICATION);
        $this->assertSame('max_attempts_exceeded', EmailChangeAuditEvent::ACTION_MAX_ATTEMPTS_EXCEEDED);
        $this->assertSame('expired_access', EmailChangeAuditEvent::ACTION_EXPIRED_ACCESS);
        $this->assertSame('old_email_confirmed', EmailChangeAuditEvent::ACTION_OLD_EMAIL_CONFIRMED);
    }

    public function testEmptyMetadata(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $event = new EmailChangeAuditEvent($user, EmailChangeAuditEvent::ACTION_INITIATED);

        $this->assertSame([], $event->getMetadata());
        $this->assertNull($event->getIpAddress());
        $this->assertNull($event->getUserAgent());
    }

    public function testUserAgentMetadata(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $event = new EmailChangeAuditEvent($user, EmailChangeAuditEvent::ACTION_INITIATED, [
            'user_agent' => 'Mozilla/5.0',
        ]);

        $this->assertSame('Mozilla/5.0', $event->getUserAgent());
    }

    public function testEventExtendsEmailChangeEvent(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $event = new EmailChangeAuditEvent($user, EmailChangeAuditEvent::ACTION_INITIATED);

        $this->assertInstanceOf(\Makraz\Bundle\VerifyEmailChange\Event\EmailChangeEvent::class, $event);
    }
}

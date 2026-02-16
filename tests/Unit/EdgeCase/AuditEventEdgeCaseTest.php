<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\EdgeCase;

use Makraz\Bundle\VerifyEmailChange\Event\EmailChangeAuditEvent;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;

class AuditEventEdgeCaseTest extends TestCase
{
    public function testEmptyMetadata(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $event = new EmailChangeAuditEvent($user, EmailChangeAuditEvent::ACTION_INITIATED);

        $this->assertSame([], $event->getMetadata());
        $this->assertNull($event->getIpAddress());
        $this->assertNull($event->getUserAgent());
    }

    public function testGetMetadataValueWithDefault(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $event = new EmailChangeAuditEvent($user, EmailChangeAuditEvent::ACTION_INITIATED);

        $this->assertNull($event->getMetadataValue('nonexistent'));
        $this->assertSame('fallback', $event->getMetadataValue('nonexistent', 'fallback'));
    }

    public function testGetMetadataValueReturnsActualValue(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $event = new EmailChangeAuditEvent($user, EmailChangeAuditEvent::ACTION_INITIATED, [
            'custom_key' => 'custom_value',
        ]);

        $this->assertSame('custom_value', $event->getMetadataValue('custom_key'));
        $this->assertSame('custom_value', $event->getMetadataValue('custom_key', 'fallback'));
    }

    public function testIpAddressFromMetadata(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $event = new EmailChangeAuditEvent($user, EmailChangeAuditEvent::ACTION_VERIFIED, [
            'ip_address' => '192.168.1.100',
        ]);

        $this->assertSame('192.168.1.100', $event->getIpAddress());
    }

    public function testUserAgentFromMetadata(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $event = new EmailChangeAuditEvent($user, EmailChangeAuditEvent::ACTION_VERIFIED, [
            'user_agent' => 'Mozilla/5.0',
        ]);

        $this->assertSame('Mozilla/5.0', $event->getUserAgent());
    }

    public function testAllActionConstants(): void
    {
        $actions = [
            EmailChangeAuditEvent::ACTION_INITIATED,
            EmailChangeAuditEvent::ACTION_VERIFIED,
            EmailChangeAuditEvent::ACTION_CONFIRMED,
            EmailChangeAuditEvent::ACTION_CANCELLED,
            EmailChangeAuditEvent::ACTION_FAILED_VERIFICATION,
            EmailChangeAuditEvent::ACTION_MAX_ATTEMPTS_EXCEEDED,
            EmailChangeAuditEvent::ACTION_EXPIRED_ACCESS,
            EmailChangeAuditEvent::ACTION_OLD_EMAIL_CONFIRMED,
        ];

        $user = new TestUser(1, 'test@example.com');

        foreach ($actions as $action) {
            $event = new EmailChangeAuditEvent($user, $action);
            $this->assertSame($action, $event->getAction());
        }

        // Ensure all constants are unique
        $this->assertCount(count($actions), array_unique($actions));
    }

    public function testEventPreservesUserReference(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $event = new EmailChangeAuditEvent($user, EmailChangeAuditEvent::ACTION_INITIATED);

        $this->assertSame($user, $event->getUser());
    }

    public function testMetadataWithMultipleEntries(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $metadata = [
            'ip_address' => '10.0.0.1',
            'user_agent' => 'TestBrowser/1.0',
            'session_id' => 'abc123',
            'timestamp' => '2026-01-15T10:00:00Z',
        ];

        $event = new EmailChangeAuditEvent($user, EmailChangeAuditEvent::ACTION_CONFIRMED, $metadata);

        $this->assertSame($metadata, $event->getMetadata());
        $this->assertSame('10.0.0.1', $event->getIpAddress());
        $this->assertSame('TestBrowser/1.0', $event->getUserAgent());
        $this->assertSame('abc123', $event->getMetadataValue('session_id'));
    }

    public function testMetadataWithNullValues(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $event = new EmailChangeAuditEvent($user, EmailChangeAuditEvent::ACTION_INITIATED, [
            'ip_address' => null,
            'some_key' => null,
        ]);

        $this->assertNull($event->getIpAddress());
        $this->assertNull($event->getMetadataValue('some_key'));
        // The ?? operator returns the default when value is null
        $this->assertSame('default', $event->getMetadataValue('some_key', 'default'));
    }

    public function testMetadataWithIntegerAndBooleanValues(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $event = new EmailChangeAuditEvent($user, EmailChangeAuditEvent::ACTION_INITIATED, [
            'attempt_count' => 3,
            'is_dual_mode' => true,
        ]);

        $this->assertSame(3, $event->getMetadataValue('attempt_count'));
        $this->assertTrue($event->getMetadataValue('is_dual_mode'));
    }
}

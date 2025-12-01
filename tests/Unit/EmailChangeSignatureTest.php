<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit;

use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeSignature;
use PHPUnit\Framework\TestCase;

class EmailChangeSignatureTest extends TestCase
{
    public function testConstructorInitializesProperties(): void
    {
        $url = 'https://example.com/verify?token=abc123';
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $signature = new EmailChangeSignature($url, $expiresAt);

        $this->assertSame($url, $signature->getSignedUrl());
        $this->assertSame($expiresAt, $signature->getExpiresAt());
    }

    public function testGetExpirationMessageKeyReturnsTranslatableString(): void
    {
        $signature = new EmailChangeSignature('https://example.com', new \DateTimeImmutable('+1 hour'));

        $key = $signature->getExpirationMessageKey();

        $this->assertStringContainsString('This link will expire', $key);
        $this->assertStringContainsString('%count%', $key);
        $this->assertStringContainsString('|', $key); // Symfony translation plural separator
    }

    public function testGetExpirationMessageDataForOneHour(): void
    {
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $signature = new EmailChangeSignature('https://example.com', $expiresAt);

        $data = $signature->getExpirationMessageData();

        $this->assertArrayHasKey('%count%', $data);
        $this->assertSame(1, $data['%count%']);
    }

    public function testGetExpirationMessageDataForTwoHours(): void
    {
        $expiresAt = new \DateTimeImmutable('+2 hours');
        $signature = new EmailChangeSignature('https://example.com', $expiresAt);

        $data = $signature->getExpirationMessageData();

        $this->assertArrayHasKey('%count%', $data);
        $this->assertEquals(2, $data['%count%']);
    }

    public function testGetExpirationMessageDataRoundsUp(): void
    {
        // 90 minutes = 1.5 hours, should round up to 2
        $expiresAt = new \DateTimeImmutable('+90 minutes');
        $signature = new EmailChangeSignature('https://example.com', $expiresAt);

        $data = $signature->getExpirationMessageData();

        $this->assertEquals(2, $data['%count%']);
    }

    public function testGetExpirationMessageDataMinimumIsOne(): void
    {
        // Very short expiration (30 seconds) should still show as 1 hour
        $expiresAt = new \DateTimeImmutable('+30 seconds');
        $signature = new EmailChangeSignature('https://example.com', $expiresAt);

        $data = $signature->getExpirationMessageData();

        $this->assertSame(1, $data['%count%']);
    }

    public function testSignedUrlCanContainQueryParameters(): void
    {
        $url = 'https://example.com/verify?selector=abc&token=def&locale=en';
        $signature = new EmailChangeSignature($url, new \DateTimeImmutable('+1 hour'));

        $this->assertSame($url, $signature->getSignedUrl());
        $this->assertStringContainsString('selector=abc', $signature->getSignedUrl());
        $this->assertStringContainsString('token=def', $signature->getSignedUrl());
        $this->assertStringContainsString('locale=en', $signature->getSignedUrl());
    }

    public function testExpiresAtIsImmutable(): void
    {
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $signature = new EmailChangeSignature('https://example.com', $expiresAt);

        $retrievedExpiresAt = $signature->getExpiresAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $retrievedExpiresAt);
        $this->assertEquals($expiresAt, $retrievedExpiresAt);
    }
}

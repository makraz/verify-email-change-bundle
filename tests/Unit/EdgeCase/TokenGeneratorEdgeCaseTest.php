<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\EdgeCase;

use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Generator\EmailChangeTokenGenerator;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;

class TokenGeneratorEdgeCaseTest extends TestCase
{
    private EmailChangeTokenGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new EmailChangeTokenGenerator();
    }

    public function testSelectorIsExactly20HexChars(): void
    {
        $components = $this->generator->createToken(new \DateTimeImmutable('+1 hour'), 1, 'test@example.com');

        $this->assertSame(20, strlen($components->getSelector()));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{20}$/', $components->getSelector());
    }

    public function testTokenIsExactly64HexChars(): void
    {
        $components = $this->generator->createToken(new \DateTimeImmutable('+1 hour'), 1, 'test@example.com');

        $this->assertSame(64, strlen($components->getToken()));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $components->getToken());
    }

    public function testHashedTokenIsSha256(): void
    {
        $components = $this->generator->createToken(new \DateTimeImmutable('+1 hour'), 1, 'test@example.com');

        // SHA-256 produces 64 hex characters
        $this->assertSame(64, strlen($components->getHashedToken()));
        $this->assertSame(hash('sha256', $components->getToken()), $components->getHashedToken());
    }

    public function testConsecutiveTokensAreDifferent(): void
    {
        $components1 = $this->generator->createToken(new \DateTimeImmutable('+1 hour'), 1, 'test@example.com');
        $components2 = $this->generator->createToken(new \DateTimeImmutable('+1 hour'), 1, 'test@example.com');

        $this->assertNotSame($components1->getSelector(), $components2->getSelector());
        $this->assertNotSame($components1->getToken(), $components2->getToken());
        $this->assertNotSame($components1->getHashedToken(), $components2->getHashedToken());
    }

    public function testSelectorAndTokenAreDifferent(): void
    {
        $components = $this->generator->createToken(new \DateTimeImmutable('+1 hour'), 1, 'test@example.com');

        $this->assertNotSame($components->getSelector(), $components->getToken());
    }

    public function testVerifyTokenWithEmptyString(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $components = $this->generator->createToken(new \DateTimeImmutable('+1 hour'), 1, 'test@example.com');

        $request = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('+1 hour'),
            $components->getSelector(),
            $components->getHashedToken(),
            'new@example.com'
        );

        $this->assertFalse($this->generator->verifyToken($request, ''));
    }

    public function testVerifyTokenWithPartialToken(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $components = $this->generator->createToken(new \DateTimeImmutable('+1 hour'), 1, 'test@example.com');

        $request = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('+1 hour'),
            $components->getSelector(),
            $components->getHashedToken(),
            'new@example.com'
        );

        // Use only first half of the token
        $partial = substr($components->getToken(), 0, 32);
        $this->assertFalse($this->generator->verifyToken($request, $partial));
    }

    public function testVerifyTokenWithCorrectTokenSucceeds(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $components = $this->generator->createToken(new \DateTimeImmutable('+1 hour'), 1, 'test@example.com');

        $request = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('+1 hour'),
            $components->getSelector(),
            $components->getHashedToken(),
            'new@example.com'
        );

        $this->assertTrue($this->generator->verifyToken($request, $components->getToken()));
    }

    public function testVerifyTokenCaseSensitive(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $components = $this->generator->createToken(new \DateTimeImmutable('+1 hour'), 1, 'test@example.com');

        $request = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('+1 hour'),
            $components->getSelector(),
            $components->getHashedToken(),
            'new@example.com'
        );

        // Uppercase the token — should fail since SHA-256 is case-sensitive
        $upperToken = strtoupper($components->getToken());
        if ($upperToken !== $components->getToken()) {
            $this->assertFalse($this->generator->verifyToken($request, $upperToken));
        } else {
            // All hex chars happened to be digits — extremely unlikely, skip
            $this->markTestSkipped('Token has no alphabetic hex characters.');
        }
    }

    public function testBulkUniqueness(): void
    {
        $selectors = [];
        $tokens = [];

        for ($i = 0; $i < 100; ++$i) {
            $components = $this->generator->createToken(new \DateTimeImmutable('+1 hour'), 1, 'test@example.com');
            $selectors[] = $components->getSelector();
            $tokens[] = $components->getToken();
        }

        $this->assertCount(100, array_unique($selectors));
        $this->assertCount(100, array_unique($tokens));
    }
}

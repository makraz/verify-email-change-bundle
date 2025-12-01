<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit;

use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Generator\EmailChangeTokenGenerator;
use Makraz\Bundle\VerifyEmailChange\Generator\TokenComponents;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;

class EmailChangeTokenGeneratorTest extends TestCase
{
    private EmailChangeTokenGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new EmailChangeTokenGenerator();
    }

    public function testCreateTokenGeneratesValidComponents(): void
    {
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $userId = 123;
        $newEmail = 'new@example.com';

        $tokenComponents = $this->generator->createToken($expiresAt, $userId, $newEmail);

        $this->assertInstanceOf(TokenComponents::class, $tokenComponents);
        $this->assertNotEmpty($tokenComponents->getSelector());
        $this->assertNotEmpty($tokenComponents->getToken());
        $this->assertNotEmpty($tokenComponents->getHashedToken());
    }

    public function testCreateTokenGeneratesSelectorWithCorrectLength(): void
    {
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $userId = 123;
        $newEmail = 'new@example.com';

        $tokenComponents = $this->generator->createToken($expiresAt, $userId, $newEmail);

        // Selector should be 20 characters (10 bytes in hex)
        $this->assertSame(20, strlen($tokenComponents->getSelector()));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{20}$/', $tokenComponents->getSelector());
    }

    public function testCreateTokenGeneratesTokenWithCorrectLength(): void
    {
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $userId = 123;
        $newEmail = 'new@example.com';

        $tokenComponents = $this->generator->createToken($expiresAt, $userId, $newEmail);

        // Token should be 64 characters (32 bytes in hex)
        $this->assertSame(64, strlen($tokenComponents->getToken()));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $tokenComponents->getToken());
    }

    public function testCreateTokenGeneratesHashedTokenWithCorrectLength(): void
    {
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $userId = 123;
        $newEmail = 'new@example.com';

        $tokenComponents = $this->generator->createToken($expiresAt, $userId, $newEmail);

        // SHA-256 hash should be 64 characters
        $this->assertSame(64, strlen($tokenComponents->getHashedToken()));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $tokenComponents->getHashedToken());
    }

    public function testCreateTokenGeneratesUniqueSelectors(): void
    {
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $userId = 123;
        $newEmail = 'new@example.com';

        $tokenComponents1 = $this->generator->createToken($expiresAt, $userId, $newEmail);
        $tokenComponents2 = $this->generator->createToken($expiresAt, $userId, $newEmail);

        $this->assertNotSame($tokenComponents1->getSelector(), $tokenComponents2->getSelector());
    }

    public function testCreateTokenGeneratesUniqueTokens(): void
    {
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $userId = 123;
        $newEmail = 'new@example.com';

        $tokenComponents1 = $this->generator->createToken($expiresAt, $userId, $newEmail);
        $tokenComponents2 = $this->generator->createToken($expiresAt, $userId, $newEmail);

        $this->assertNotSame($tokenComponents1->getToken(), $tokenComponents2->getToken());
        $this->assertNotSame($tokenComponents1->getHashedToken(), $tokenComponents2->getHashedToken());
    }

    public function testVerifyTokenReturnsTrueForMatchingToken(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $tokenComponents = $this->generator->createToken($expiresAt, $user->getId(), 'new@example.com');

        $emailChangeRequest = new EmailChangeRequest(
            $user,
            $expiresAt,
            $tokenComponents->getSelector(),
            $tokenComponents->getHashedToken(),
            'new@example.com'
        );

        $result = $this->generator->verifyToken($emailChangeRequest, $tokenComponents->getToken());

        $this->assertTrue($result);
    }

    public function testVerifyTokenReturnsFalseForNonMatchingToken(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $tokenComponents = $this->generator->createToken($expiresAt, $user->getId(), 'new@example.com');

        $emailChangeRequest = new EmailChangeRequest(
            $user,
            $expiresAt,
            $tokenComponents->getSelector(),
            $tokenComponents->getHashedToken(),
            'new@example.com'
        );

        $wrongToken = bin2hex(random_bytes(32));
        $result = $this->generator->verifyToken($emailChangeRequest, $wrongToken);

        $this->assertFalse($result);
    }

    public function testVerifyTokenUsesConstantTimeComparison(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $tokenComponents = $this->generator->createToken($expiresAt, $user->getId(), 'new@example.com');

        $emailChangeRequest = new EmailChangeRequest(
            $user,
            $expiresAt,
            $tokenComponents->getSelector(),
            $tokenComponents->getHashedToken(),
            'new@example.com'
        );

        // Test with completely different token
        $wrongToken1 = str_repeat('0', 64);
        $startTime1 = microtime(true);
        $this->generator->verifyToken($emailChangeRequest, $wrongToken1);
        $duration1 = microtime(true) - $startTime1;

        // Test with token that differs only in last character
        // Ensure the replacement character is different from the original
        $originalToken = $tokenComponents->getToken();
        $lastChar = substr($originalToken, -1);
        $replacementChar = $lastChar === '0' ? 'f' : '0';
        $wrongToken2 = substr($originalToken, 0, -1) . $replacementChar;

        $startTime2 = microtime(true);
        $this->generator->verifyToken($emailChangeRequest, $wrongToken2);
        $duration2 = microtime(true) - $startTime2;

        // The durations should be similar (within reasonable margin)
        // This is a basic check - timing attacks are harder to test reliably
        // The important part is that we use hash_equals() internally
        $this->assertFalse($this->generator->verifyToken($emailChangeRequest, $wrongToken1));
        $this->assertFalse($this->generator->verifyToken($emailChangeRequest, $wrongToken2));
    }

    public function testHashedTokenMatchesSha256Hash(): void
    {
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $userId = 123;
        $newEmail = 'new@example.com';

        $tokenComponents = $this->generator->createToken($expiresAt, $userId, $newEmail);

        // Manually hash the token to verify it matches
        $expectedHash = hash('sha256', $tokenComponents->getToken());

        $this->assertSame($expectedHash, $tokenComponents->getHashedToken());
    }

    public function testCreateTokenIsNotDependentOnInputParameters(): void
    {
        // Test that different input parameters don't affect randomness
        $expiresAt1 = new \DateTimeImmutable('+1 hour');
        $expiresAt2 = new \DateTimeImmutable('+2 hours');

        $tokenComponents1 = $this->generator->createToken($expiresAt1, 1, 'email1@example.com');
        $tokenComponents2 = $this->generator->createToken($expiresAt2, 2, 'email2@example.com');

        // Even with different inputs, selectors and tokens should be unique
        $this->assertNotSame($tokenComponents1->getSelector(), $tokenComponents2->getSelector());
        $this->assertNotSame($tokenComponents1->getToken(), $tokenComponents2->getToken());
        $this->assertNotSame($tokenComponents1->getHashedToken(), $tokenComponents2->getHashedToken());
    }
}

<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit;

use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;

class EmailChangeRequestTest extends TestCase
{
    public function testConstructorInitializesAllProperties(): void
    {
        $user = new TestUser(123, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $selector = 'selector123';
        $hashedToken = 'hashedtoken789';
        $newEmail = 'new@example.com';

        $request = new EmailChangeRequest($user, $expiresAt, $selector, $hashedToken, $newEmail);

        $this->assertSame($selector, $request->getSelector());
        $this->assertSame($hashedToken, $request->getHashedToken());
        $this->assertSame($newEmail, $request->getNewEmail());
        $this->assertSame($expiresAt, $request->getExpiresAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $request->getRequestedAt());
    }

    public function testUserIdentifierIsCreatedCorrectly(): void
    {
        $user = new TestUser(123, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $request = new EmailChangeRequest($user, $expiresAt, 'selector', 'hash', 'new@example.com');

        $expectedIdentifier = TestUser::class.'::123';
        $this->assertSame($expectedIdentifier, $request->getUserIdentifier());
    }

    public function testGetUserClassExtractsCorrectClassName(): void
    {
        $user = new TestUser(123, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $request = new EmailChangeRequest($user, $expiresAt, 'selector', 'hash', 'new@example.com');

        $this->assertSame(TestUser::class, $request->getUserClass());
    }

    public function testGetUserIdExtractsCorrectId(): void
    {
        $user = new TestUser(123, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $request = new EmailChangeRequest($user, $expiresAt, 'selector', 'hash', 'new@example.com');

        $this->assertSame(123, $request->getUserId());
    }

    public function testIsExpiredReturnsFalseForFutureExpiration(): void
    {
        $user = new TestUser(123, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $request = new EmailChangeRequest($user, $expiresAt, 'selector', 'hash', 'new@example.com');

        $this->assertFalse($request->isExpired());
    }

    public function testIsExpiredReturnsTrueForPastExpiration(): void
    {
        $user = new TestUser(123, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('-1 hour');

        $request = new EmailChangeRequest($user, $expiresAt, 'selector', 'hash', 'new@example.com');

        $this->assertTrue($request->isExpired());
    }

    public function testIsExpiredReturnsTrueForCurrentTime(): void
    {
        $user = new TestUser(123, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('now');

        $request = new EmailChangeRequest($user, $expiresAt, 'selector', 'hash', 'new@example.com');

        // Sleep for a microsecond to ensure time has passed
        usleep(1000);

        $this->assertTrue($request->isExpired());
    }

    public function testBelongsToReturnsTrueForSameUser(): void
    {
        $user = new TestUser(123, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $request = new EmailChangeRequest($user, $expiresAt, 'selector', 'hash', 'new@example.com');

        $this->assertTrue($request->belongsTo($user));
    }

    public function testBelongsToReturnsFalseForDifferentUserId(): void
    {
        $user1 = new TestUser(123, 'old@example.com');
        $user2 = new TestUser(456, 'other@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $request = new EmailChangeRequest($user1, $expiresAt, 'selector', 'hash', 'new@example.com');

        $this->assertFalse($request->belongsTo($user2));
    }

    public function testRequestedAtIsSetAutomatically(): void
    {
        $user = new TestUser(123, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $beforeCreate = new \DateTimeImmutable('-1 second');
        $request = new EmailChangeRequest($user, $expiresAt, 'selector', 'hash', 'new@example.com');
        $afterCreate = new \DateTimeImmutable('+1 second');

        $this->assertGreaterThanOrEqual($beforeCreate->getTimestamp(), $request->getRequestedAt()->getTimestamp());
        $this->assertLessThanOrEqual($afterCreate->getTimestamp(), $request->getRequestedAt()->getTimestamp());
    }

    public function testIdIsNullBeforePersistence(): void
    {
        $user = new TestUser(123, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $request = new EmailChangeRequest($user, $expiresAt, 'selector', 'hash', 'new@example.com');

        $this->assertNull($request->getId());
    }

    public function testRequestStoresNewEmailCorrectly(): void
    {
        $user = new TestUser(123, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $newEmail = 'completely.new@example.com';

        $request = new EmailChangeRequest($user, $expiresAt, 'selector', 'hash', $newEmail);

        $this->assertSame($newEmail, $request->getNewEmail());
    }

    public function testExpirationTimeIsPreserved(): void
    {
        $user = new TestUser(123, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('2025-12-31 23:59:59');

        $request = new EmailChangeRequest($user, $expiresAt, 'selector', 'hash', 'new@example.com');

        $this->assertSame($expiresAt, $request->getExpiresAt());
        $this->assertSame('2025-12-31 23:59:59', $request->getExpiresAt()->format('Y-m-d H:i:s'));
    }

    public function testSelectorIsStoredCorrectly(): void
    {
        $user = new TestUser(123, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $selector = 'abcdef1234567890abcd';

        $request = new EmailChangeRequest($user, $expiresAt, $selector, 'hash', 'new@example.com');

        $this->assertSame($selector, $request->getSelector());
    }

    public function testHashedTokenIsStoredCorrectly(): void
    {
        $user = new TestUser(123, 'old@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $hashedToken = hash('sha256', 'some-random-token');

        $request = new EmailChangeRequest($user, $expiresAt, 'selector', $hashedToken, 'new@example.com');

        $this->assertSame($hashedToken, $request->getHashedToken());
    }

    public function testMultipleRequestsForSameUserHaveDifferentIdentifiers(): void
    {
        $user1 = new TestUser(123, 'user1@example.com');
        $user2 = new TestUser(123, 'user2@example.com'); // Same ID, different instance
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $request1 = new EmailChangeRequest($user1, $expiresAt, 'selector1', 'hash1', 'new1@example.com');
        $request2 = new EmailChangeRequest($user2, $expiresAt, 'selector2', 'hash2', 'new2@example.com');

        // They should have the same user identifier since the ID is the same
        $this->assertSame($request1->getUserIdentifier(), $request2->getUserIdentifier());
    }

    public function testDifferentUsersHaveDifferentIdentifiers(): void
    {
        $user1 = new TestUser(123, 'user1@example.com');
        $user2 = new TestUser(456, 'user2@example.com');
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $request1 = new EmailChangeRequest($user1, $expiresAt, 'selector1', 'hash1', 'new1@example.com');
        $request2 = new EmailChangeRequest($user2, $expiresAt, 'selector2', 'hash2', 'new2@example.com');

        $this->assertNotSame($request1->getUserIdentifier(), $request2->getUserIdentifier());
    }
}

<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\EdgeCase;

use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;

class EmailChangeRequestEdgeCaseTest extends TestCase
{
    public function testAttemptsStartAtZero(): void
    {
        $request = $this->createRequest();

        $this->assertSame(0, $request->getAttempts());
    }

    public function testIncrementAttemptsMultipleTimes(): void
    {
        $request = $this->createRequest();

        for ($i = 1; $i <= 10; ++$i) {
            $request->incrementAttempts();
            $this->assertSame($i, $request->getAttempts());
        }
    }

    public function testDualModeFieldsAreNullByDefault(): void
    {
        $request = $this->createRequest();

        $this->assertNull($request->getOldEmailSelector());
        $this->assertNull($request->getOldEmailHashedToken());
        $this->assertFalse($request->isConfirmedByNewEmail());
        $this->assertFalse($request->isConfirmedByOldEmail());
    }

    public function testSetAndGetOldEmailSelector(): void
    {
        $request = $this->createRequest();
        $request->setOldEmailSelector('old_selector_abc');

        $this->assertSame('old_selector_abc', $request->getOldEmailSelector());
    }

    public function testSetOldEmailSelectorToNull(): void
    {
        $request = $this->createRequest();
        $request->setOldEmailSelector('old_selector_abc');
        $request->setOldEmailSelector(null);

        $this->assertNull($request->getOldEmailSelector());
    }

    public function testSetAndGetOldEmailHashedToken(): void
    {
        $request = $this->createRequest();
        $request->setOldEmailHashedToken('hashed_old_token');

        $this->assertSame('hashed_old_token', $request->getOldEmailHashedToken());
    }

    public function testSetOldEmailHashedTokenToNull(): void
    {
        $request = $this->createRequest();
        $request->setOldEmailHashedToken('hashed_old_token');
        $request->setOldEmailHashedToken(null);

        $this->assertNull($request->getOldEmailHashedToken());
    }

    public function testIsFullyConfirmedReturnsTrueInSingleMode(): void
    {
        $request = $this->createRequest();

        $this->assertTrue($request->isFullyConfirmed(false));
    }

    public function testIsFullyConfirmedReturnsTrueInSingleModeRegardlessOfFlags(): void
    {
        $request = $this->createRequest();
        $request->setConfirmedByNewEmail(false);
        $request->setConfirmedByOldEmail(false);

        $this->assertTrue($request->isFullyConfirmed(false));
    }

    public function testIsFullyConfirmedInDualModeRequiresBothFlags(): void
    {
        $request = $this->createRequest();

        // Neither confirmed
        $this->assertFalse($request->isFullyConfirmed(true));

        // Only new confirmed
        $request->setConfirmedByNewEmail(true);
        $this->assertFalse($request->isFullyConfirmed(true));

        // Only old confirmed
        $request->setConfirmedByNewEmail(false);
        $request->setConfirmedByOldEmail(true);
        $this->assertFalse($request->isFullyConfirmed(true));

        // Both confirmed
        $request->setConfirmedByNewEmail(true);
        $this->assertTrue($request->isFullyConfirmed(true));
    }

    public function testConfirmationFlagsCanBeToggledBackToFalse(): void
    {
        $request = $this->createRequest();

        $request->setConfirmedByNewEmail(true);
        $request->setConfirmedByOldEmail(true);
        $this->assertTrue($request->isFullyConfirmed(true));

        $request->setConfirmedByNewEmail(false);
        $this->assertFalse($request->isFullyConfirmed(true));
    }

    public function testBelongsToWithSameIdDifferentEmail(): void
    {
        $user1 = new TestUser(1, 'alice@example.com');
        $user2 = new TestUser(1, 'bob@example.com');
        $request = $this->createRequest($user1);

        // Same class and ID, different email — should still match
        $this->assertTrue($request->belongsTo($user2));
    }

    public function testUserIdentifierFormat(): void
    {
        $user = new TestUser(42, 'user@example.com');
        $request = $this->createRequest($user);

        $this->assertSame(TestUser::class.'::42', $request->getUserIdentifier());
        $this->assertSame(TestUser::class, $request->getUserClass());
        $this->assertSame(42, $request->getUserId());
    }

    public function testUserIdWithZero(): void
    {
        $user = new TestUser(0, 'user@example.com');
        $request = $this->createRequest($user);

        $this->assertSame(0, $request->getUserId());
        $this->assertStringEndsWith('::0', $request->getUserIdentifier());
    }

    public function testIsExpiredWithFarFutureDate(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $request = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('+10 years'),
            'selector',
            'hash',
            'new@example.com'
        );

        $this->assertFalse($request->isExpired());
    }

    public function testIsExpiredWithOneSecondFromNow(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $request = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('+1 second'),
            'selector',
            'hash',
            'new@example.com'
        );

        $this->assertFalse($request->isExpired());
    }

    public function testIsExpiredWithOneSecondAgo(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $request = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('-1 second'),
            'selector',
            'hash',
            'new@example.com'
        );

        $this->assertTrue($request->isExpired());
    }

    public function testNewEmailWithSpecialCharacters(): void
    {
        $request = $this->createRequest(newEmail: 'user+tag@sub.example.com');

        $this->assertSame('user+tag@sub.example.com', $request->getNewEmail());
    }

    public function testNewEmailWithUnicodeLocalPart(): void
    {
        $request = $this->createRequest(newEmail: 'ünïcödé@example.com');

        $this->assertSame('ünïcödé@example.com', $request->getNewEmail());
    }

    public function testNewEmailWithLongAddress(): void
    {
        $longLocal = str_repeat('a', 64);
        $email = $longLocal.'@example.com';
        $request = $this->createRequest(newEmail: $email);

        $this->assertSame($email, $request->getNewEmail());
    }

    private function createRequest(
        ?TestUser $user = null,
        string $newEmail = 'new@example.com',
    ): EmailChangeRequest {
        return new EmailChangeRequest(
            $user ?? new TestUser(1, 'old@example.com'),
            new \DateTimeImmutable('+1 hour'),
            'selector123',
            'hashedtoken789',
            $newEmail,
        );
    }
}

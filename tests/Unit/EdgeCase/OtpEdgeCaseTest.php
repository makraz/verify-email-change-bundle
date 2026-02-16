<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\EdgeCase;

use Makraz\Bundle\VerifyEmailChange\Exception\ExpiredEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\InvalidEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyVerificationAttemptsException;
use Makraz\Bundle\VerifyEmailChange\Generator\EmailChangeTokenGenerator;
use Makraz\Bundle\VerifyEmailChange\Otp\OtpEmailChangeHelper;
use Makraz\Bundle\VerifyEmailChange\Otp\OtpGenerator;
use Makraz\Bundle\VerifyEmailChange\Otp\OtpResult;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\EmailChangeRequestTestRepository;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;

class OtpEdgeCaseTest extends TestCase
{
    private EmailChangeRequestTestRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new EmailChangeRequestTestRepository();
    }

    private function createHelper(int $otpLength = 6, int $maxAttempts = 5, int $lifetime = 3600): OtpEmailChangeHelper
    {
        return new OtpEmailChangeHelper(
            $this->repository,
            new EmailChangeTokenGenerator(),
            new OtpGenerator($otpLength),
            $lifetime,
            $maxAttempts,
        );
    }

    // --- OTP Generator Boundary Tests ---

    public function testOtpMinLength4Digits(): void
    {
        $generator = new OtpGenerator(4);
        $otp = $generator->generate();

        $this->assertSame(4, strlen($otp));
        $this->assertGreaterThanOrEqual(1000, (int) $otp);
        $this->assertLessThanOrEqual(9999, (int) $otp);
    }

    public function testOtpMaxLength10Digits(): void
    {
        $generator = new OtpGenerator(10);
        $otp = $generator->generate();

        $this->assertSame(10, strlen($otp));
        $this->assertTrue(ctype_digit($otp));
        $this->assertGreaterThanOrEqual(1000000000, (int) $otp);
    }

    public function testOtpNeverStartsWithZero(): void
    {
        $generator = new OtpGenerator(6);

        for ($i = 0; $i < 100; ++$i) {
            $otp = $generator->generate();
            $this->assertNotSame('0', $otp[0], 'OTP should not start with zero');
        }
    }

    public function testOtpVerifyWithEmptyString(): void
    {
        $generator = new OtpGenerator();
        $hash = $generator->hash('123456');

        $this->assertFalse($generator->verify('', $hash));
    }

    public function testOtpVerifyWithWrongLengthCode(): void
    {
        $generator = new OtpGenerator(6);
        $hash = $generator->hash('123456');

        $this->assertFalse($generator->verify('1234', $hash));
        $this->assertFalse($generator->verify('12345678', $hash));
    }

    public function testOtpHashIsSha256(): void
    {
        $generator = new OtpGenerator();
        $otp = '123456';
        $hash = $generator->hash($otp);

        $this->assertSame(hash('sha256', $otp), $hash);
        $this->assertSame(64, strlen($hash));
    }

    // --- OTP Helper Edge Cases ---

    public function testVerifyOtpCorrectAfterFailedAttempts(): void
    {
        $helper = $this->createHelper(6, 5);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $result = $helper->generateOtp($user, 'new@example.com');

        // Fail 4 times
        for ($i = 0; $i < 4; ++$i) {
            try {
                $helper->verifyOtp($user, '000000');
            } catch (InvalidEmailChangeRequestException) {
            }
        }

        // 5th attempt with correct OTP should succeed
        $oldEmail = $helper->verifyOtp($user, $result->getOtp());
        $this->assertSame('old@example.com', $oldEmail);
        $this->assertSame('new@example.com', $user->getEmail());
    }

    public function testVerifyOtpExactlyAtMaxAttemptsThreshold(): void
    {
        $helper = $this->createHelper(6, 2);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $helper->generateOtp($user, 'new@example.com');

        // First wrong attempt
        try {
            $helper->verifyOtp($user, '000000');
        } catch (InvalidEmailChangeRequestException) {
        }

        // Second wrong attempt should trigger TooManyVerificationAttemptsException
        $this->expectException(TooManyVerificationAttemptsException::class);
        $helper->verifyOtp($user, '000000');
    }

    public function testVerifyOtpAfterMaxAttemptsRequestIsGone(): void
    {
        $helper = $this->createHelper(6, 1);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $result = $helper->generateOtp($user, 'new@example.com');

        // Exhaust the single attempt
        try {
            $helper->verifyOtp($user, '000000');
        } catch (TooManyVerificationAttemptsException) {
        }

        // Correct OTP should now fail with "no pending" since request was deleted
        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('No pending email change found.');

        $helper->verifyOtp($user, $result->getOtp());
    }

    public function testVerifyOtpWithExpiredRequest(): void
    {
        $helper = $this->createHelper(6, 5, 1); // 1 second lifetime
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $result = $helper->generateOtp($user, 'new@example.com');

        // Wait for expiry
        sleep(2);

        $this->expectException(ExpiredEmailChangeRequestException::class);
        $helper->verifyOtp($user, $result->getOtp());
    }

    public function testHasPendingReturnsFalseAfterExpiry(): void
    {
        $helper = $this->createHelper(6, 5, 1); // 1 second lifetime
        $user = new TestUser(1, 'old@example.com');

        $helper->generateOtp($user, 'new@example.com');

        sleep(2);

        $this->assertFalse($helper->hasPendingEmailChange($user));
        $this->assertNull($helper->getPendingEmail($user));
    }

    public function testOtpResultExpiresAtIsInFuture(): void
    {
        $helper = $this->createHelper();
        $user = new TestUser(1, 'old@example.com');

        $before = new \DateTimeImmutable();
        $result = $helper->generateOtp($user, 'new@example.com');
        $after = new \DateTimeImmutable('+3601 seconds');

        $this->assertGreaterThan($before, $result->getExpiresAt());
        $this->assertLessThanOrEqual($after, $result->getExpiresAt());
    }

    public function testOtpResultContainsDigitsOnly(): void
    {
        $helper = $this->createHelper(8);
        $user = new TestUser(1, 'old@example.com');

        $result = $helper->generateOtp($user, 'new@example.com');

        $this->assertSame(8, strlen($result->getOtp()));
        $this->assertTrue(ctype_digit($result->getOtp()));
    }

    public function testCancelNonExistentOtpRequestDoesNotThrow(): void
    {
        $helper = $this->createHelper();
        $user = new TestUser(1, 'old@example.com');

        $helper->cancelEmailChange($user);

        $this->assertFalse($helper->hasPendingEmailChange($user));
    }

    public function testMultipleUsersIndependentOtpRequests(): void
    {
        $helper = $this->createHelper();
        $user1 = new TestUser(1, 'user1@example.com');
        $user2 = new TestUser(2, 'user2@example.com');
        $this->repository->registerUser($user1);
        $this->repository->registerUser($user2);

        $result1 = $helper->generateOtp($user1, 'new1@example.com');
        $result2 = $helper->generateOtp($user2, 'new2@example.com');

        // Verify user1's OTP doesn't work for user2
        $this->assertSame('new1@example.com', $helper->getPendingEmail($user1));
        $this->assertSame('new2@example.com', $helper->getPendingEmail($user2));

        // Complete user1's change
        $helper->verifyOtp($user1, $result1->getOtp());
        $this->assertSame('new1@example.com', $user1->getEmail());

        // User2 should still be pending
        $this->assertTrue($helper->hasPendingEmailChange($user2));
    }
}

<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\Otp;

use Makraz\Bundle\VerifyEmailChange\Exception\ExpiredEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\InvalidEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyEmailChangeRequestsException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyVerificationAttemptsException;
use Makraz\Bundle\VerifyEmailChange\Generator\EmailChangeTokenGenerator;
use Makraz\Bundle\VerifyEmailChange\Otp\OtpEmailChangeHelper;
use Makraz\Bundle\VerifyEmailChange\Otp\OtpGenerator;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\EmailChangeRequestTestRepository;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;

class OtpEmailChangeHelperTest extends TestCase
{
    private EmailChangeRequestTestRepository $repository;
    private OtpEmailChangeHelper $helper;

    protected function setUp(): void
    {
        $this->repository = new EmailChangeRequestTestRepository();
        $this->helper = new OtpEmailChangeHelper(
            $this->repository,
            new EmailChangeTokenGenerator(),
            new OtpGenerator(6),
            3600,
            3,
        );
    }

    public function testGenerateOtpReturnsResult(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $result = $this->helper->generateOtp($user, 'new@example.com');

        $this->assertSame(6, strlen($result->getOtp()));
        $this->assertTrue(ctype_digit($result->getOtp()));
        $this->assertGreaterThan(new \DateTimeImmutable(), $result->getExpiresAt());
    }

    public function testGenerateOtpCreatesPendingRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $this->helper->generateOtp($user, 'new@example.com');

        $this->assertTrue($this->helper->hasPendingEmailChange($user));
        $this->assertSame('new@example.com', $this->helper->getPendingEmail($user));
    }

    public function testVerifyOtpChangesEmail(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $result = $this->helper->generateOtp($user, 'new@example.com');
        $oldEmail = $this->helper->verifyOtp($user, $result->getOtp());

        $this->assertSame('old@example.com', $oldEmail);
        $this->assertSame('new@example.com', $user->getEmail());
    }

    public function testVerifyOtpRemovesPendingRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $result = $this->helper->generateOtp($user, 'new@example.com');
        $this->helper->verifyOtp($user, $result->getOtp());

        $this->assertFalse($this->helper->hasPendingEmailChange($user));
    }

    public function testVerifyOtpWithWrongCodeThrows(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $this->helper->generateOtp($user, 'new@example.com');

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Invalid verification code.');

        $this->helper->verifyOtp($user, '000000');
    }

    public function testVerifyOtpExceedingMaxAttemptsThrows(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $this->helper->generateOtp($user, 'new@example.com');

        // Exhaust attempts (max is 3)
        for ($i = 0; $i < 2; ++$i) {
            try {
                $this->helper->verifyOtp($user, '000000');
            } catch (InvalidEmailChangeRequestException) {
                // Expected
            }
        }

        $this->expectException(TooManyVerificationAttemptsException::class);
        $this->helper->verifyOtp($user, '000000');
    }

    public function testVerifyOtpWithNoPendingRequestThrows(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('No pending email change found.');

        $this->helper->verifyOtp($user, '123456');
    }

    public function testDuplicateRequestThrowsTooManyRequests(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $this->helper->generateOtp($user, 'new@example.com');

        $this->expectException(TooManyEmailChangeRequestsException::class);
        $this->helper->generateOtp($user, 'another@example.com');
    }

    public function testCancelEmailChange(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $this->helper->generateOtp($user, 'new@example.com');
        $this->assertTrue($this->helper->hasPendingEmailChange($user));

        $this->helper->cancelEmailChange($user);
        $this->assertFalse($this->helper->hasPendingEmailChange($user));
    }

    public function testCancelNonExistentRequestDoesNotThrow(): void
    {
        $user = new TestUser(1, 'old@example.com');

        // Should not throw
        $this->helper->cancelEmailChange($user);
        $this->assertFalse($this->helper->hasPendingEmailChange($user));
    }

    public function testGetPendingEmailReturnsNullWhenNoPending(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $this->assertNull($this->helper->getPendingEmail($user));
    }
}

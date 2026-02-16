<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\EdgeCase;

use Makraz\Bundle\VerifyEmailChange\Exception\EmailAlreadyInUseException;
use Makraz\Bundle\VerifyEmailChange\Exception\ExpiredEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\InvalidEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\SameEmailException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyEmailChangeRequestsException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyVerificationAttemptsException;
use Makraz\Bundle\VerifyEmailChange\Exception\VerifyEmailChangeExceptionInterface;
use PHPUnit\Framework\TestCase;

class ExceptionEdgeCaseTest extends TestCase
{
    public function testAllExceptionsImplementInterface(): void
    {
        $exceptions = [
            new EmailAlreadyInUseException('test@example.com'),
            new ExpiredEmailChangeRequestException(),
            new InvalidEmailChangeRequestException('test'),
            new SameEmailException('test@example.com'),
            new TooManyEmailChangeRequestsException(new \DateTimeImmutable()),
            new TooManyVerificationAttemptsException(5),
        ];

        foreach ($exceptions as $exception) {
            $this->assertInstanceOf(VerifyEmailChangeExceptionInterface::class, $exception);
            $this->assertNotEmpty($exception->getReason(), get_class($exception).' should return a non-empty reason');
        }
    }

    public function testInvalidEmailChangeRequestExceptionUsesMessageWhenProvided(): void
    {
        $exception = new InvalidEmailChangeRequestException('Custom message');

        $this->assertSame('Custom message', $exception->getReason());
    }

    public function testInvalidEmailChangeRequestExceptionFallbackWhenNoMessage(): void
    {
        $exception = new InvalidEmailChangeRequestException();

        $this->assertSame('The email change link is invalid.', $exception->getReason());
    }

    public function testInvalidEmailChangeRequestExceptionWithEmptyMessage(): void
    {
        $exception = new InvalidEmailChangeRequestException('');

        $this->assertSame('The email change link is invalid.', $exception->getReason());
    }

    public function testSameEmailExceptionIncludesEmailInMessage(): void
    {
        $exception = new SameEmailException('user@example.com');

        $this->assertStringContainsString('user@example.com', $exception->getMessage());
        $this->assertSame('The new email address is identical to the current one.', $exception->getReason());
    }

    public function testEmailAlreadyInUseExceptionIncludesEmailInMessage(): void
    {
        $exception = new EmailAlreadyInUseException('taken@example.com');

        $this->assertStringContainsString('taken@example.com', $exception->getMessage());
        $this->assertSame('This email address is already in use.', $exception->getReason());
    }

    public function testTooManyEmailChangeRequestsExceptionContainsAvailableAt(): void
    {
        $availableAt = new \DateTimeImmutable('+1 hour');
        $exception = new TooManyEmailChangeRequestsException($availableAt);

        $this->assertSame($availableAt, $exception->getAvailableAt());
        $this->assertStringContainsString(
            $availableAt->format('Y-m-d H:i:s'),
            $exception->getReason()
        );
    }

    public function testTooManyVerificationAttemptsExceptionContainsMaxAttempts(): void
    {
        $exception = new TooManyVerificationAttemptsException(3);

        $this->assertSame(3, $exception->getMaxAttempts());
        $this->assertStringContainsString('3', $exception->getReason());
    }

    public function testTooManyVerificationAttemptsExceptionWithLargeMax(): void
    {
        $exception = new TooManyVerificationAttemptsException(1000);

        $this->assertSame(1000, $exception->getMaxAttempts());
        $this->assertStringContainsString('1000', $exception->getReason());
    }

    public function testExpiredExceptionReason(): void
    {
        $exception = new ExpiredEmailChangeRequestException();

        $this->assertStringContainsString('expired', strtolower($exception->getReason()));
    }

    public function testExceptionsWithPreviousException(): void
    {
        $previous = new \RuntimeException('Previous error');

        $exception1 = new SameEmailException('test@example.com', $previous);
        $exception2 = new EmailAlreadyInUseException('test@example.com', $previous);

        $this->assertSame($previous, $exception1->getPrevious());
        $this->assertSame($previous, $exception2->getPrevious());
    }

    public function testTooManyRequestsExceptionWithPreviousAndCode(): void
    {
        $previous = new \RuntimeException('Previous');
        $availableAt = new \DateTimeImmutable();

        $exception = new TooManyEmailChangeRequestsException($availableAt, 'Custom msg', 42, $previous);

        $this->assertSame('Custom msg', $exception->getMessage());
        $this->assertSame(42, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testTooManyAttemptsExceptionWithPreviousAndCode(): void
    {
        $previous = new \RuntimeException('Previous');

        $exception = new TooManyVerificationAttemptsException(5, 'Custom msg', 99, $previous);

        $this->assertSame('Custom msg', $exception->getMessage());
        $this->assertSame(99, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}

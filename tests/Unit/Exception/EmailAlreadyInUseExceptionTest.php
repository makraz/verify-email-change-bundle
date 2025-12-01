<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\Exception;

use Makraz\Bundle\VerifyEmailChange\Exception\EmailAlreadyInUseException;
use Makraz\Bundle\VerifyEmailChange\Exception\VerifyEmailChangeExceptionInterface;
use PHPUnit\Framework\TestCase;

class EmailAlreadyInUseExceptionTest extends TestCase
{
    public function testImplementsVerifyEmailChangeExceptionInterface(): void
    {
        $exception = new EmailAlreadyInUseException('test@example.com');

        $this->assertInstanceOf(VerifyEmailChangeExceptionInterface::class, $exception);
    }

    public function testGetReasonReturnsCorrectMessage(): void
    {
        $exception = new EmailAlreadyInUseException('test@example.com');

        $this->assertSame('This email address is already in use.', $exception->getReason());
    }

    public function testExceptionMessageContainsEmail(): void
    {
        $email = 'test@example.com';
        $exception = new EmailAlreadyInUseException($email);

        $this->assertStringContainsString($email, $exception->getMessage());
        $this->assertStringContainsString('This email address is already in use', $exception->getMessage());
    }

    public function testExceptionCanBeCaught(): void
    {
        $this->expectException(EmailAlreadyInUseException::class);

        throw new EmailAlreadyInUseException('duplicate@example.com');
    }

    public function testExceptionCanBeCaughtAsVerifyEmailChangeException(): void
    {
        $this->expectException(VerifyEmailChangeExceptionInterface::class);

        throw new EmailAlreadyInUseException('duplicate@example.com');
    }
}

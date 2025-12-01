<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\Exception;

use Makraz\Bundle\VerifyEmailChange\Exception\SameEmailException;
use Makraz\Bundle\VerifyEmailChange\Exception\VerifyEmailChangeExceptionInterface;
use PHPUnit\Framework\TestCase;

class SameEmailExceptionTest extends TestCase
{
    public function testImplementsVerifyEmailChangeExceptionInterface(): void
    {
        $exception = new SameEmailException('test@example.com');

        $this->assertInstanceOf(VerifyEmailChangeExceptionInterface::class, $exception);
    }

    public function testGetReasonReturnsCorrectMessage(): void
    {
        $exception = new SameEmailException('test@example.com');

        $this->assertSame('The new email address is identical to the current one.', $exception->getReason());
    }

    public function testExceptionMessageContainsEmail(): void
    {
        $email = 'test@example.com';
        $exception = new SameEmailException($email);

        $this->assertStringContainsString($email, $exception->getMessage());
        $this->assertStringContainsString('The new email address is identical to the current one', $exception->getMessage());
    }

    public function testExceptionCanBeCaught(): void
    {
        $this->expectException(SameEmailException::class);

        throw new SameEmailException('same@example.com');
    }

    public function testExceptionCanBeCaughtAsVerifyEmailChangeException(): void
    {
        $this->expectException(VerifyEmailChangeExceptionInterface::class);

        throw new SameEmailException('same@example.com');
    }
}

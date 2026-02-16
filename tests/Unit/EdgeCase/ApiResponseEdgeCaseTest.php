<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\EdgeCase;

use Makraz\Bundle\VerifyEmailChange\Api\EmailChangeResponseFactory;
use Makraz\Bundle\VerifyEmailChange\Exception\EmailAlreadyInUseException;
use Makraz\Bundle\VerifyEmailChange\Exception\ExpiredEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\InvalidEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\SameEmailException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyEmailChangeRequestsException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyVerificationAttemptsException;
use PHPUnit\Framework\TestCase;

class ApiResponseEdgeCaseTest extends TestCase
{
    private EmailChangeResponseFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new EmailChangeResponseFactory();
    }

    public function testErrorDefaultStatusCodeIs400(): void
    {
        $exception = new InvalidEmailChangeRequestException('test');
        $response = $this->factory->error($exception);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testErrorMessageDefaultStatusCodeIs400(): void
    {
        $response = $this->factory->errorMessage('Something wrong');

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testErrorResponseContainsExceptionReason(): void
    {
        $exception = new SameEmailException('user@example.com');
        $response = $this->factory->error($exception);
        $data = json_decode($response->getContent(), true);

        $this->assertSame($exception->getReason(), $data['message']);
    }

    /**
     * @dataProvider exceptionTypeProvider
     */
    public function testErrorResponseExceptionTypeMapping(string $exceptionClass, string $expectedType): void
    {
        $exception = match ($exceptionClass) {
            SameEmailException::class => new SameEmailException('test@example.com'),
            EmailAlreadyInUseException::class => new EmailAlreadyInUseException('test@example.com'),
            ExpiredEmailChangeRequestException::class => new ExpiredEmailChangeRequestException(),
            InvalidEmailChangeRequestException::class => new InvalidEmailChangeRequestException('test'),
            TooManyEmailChangeRequestsException::class => new TooManyEmailChangeRequestsException(new \DateTimeImmutable()),
            TooManyVerificationAttemptsException::class => new TooManyVerificationAttemptsException(5),
        };

        $response = $this->factory->error($exception);
        $data = json_decode($response->getContent(), true);

        $this->assertSame($expectedType, $data['error']['type']);
    }

    public static function exceptionTypeProvider(): iterable
    {
        yield 'SameEmailException' => [SameEmailException::class, 'SameEmailException'];
        yield 'EmailAlreadyInUseException' => [EmailAlreadyInUseException::class, 'EmailAlreadyInUseException'];
        yield 'ExpiredEmailChangeRequestException' => [ExpiredEmailChangeRequestException::class, 'ExpiredEmailChangeRequestException'];
        yield 'InvalidEmailChangeRequestException' => [InvalidEmailChangeRequestException::class, 'InvalidEmailChangeRequestException'];
        yield 'TooManyEmailChangeRequestsException' => [TooManyEmailChangeRequestsException::class, 'TooManyEmailChangeRequestsException'];
        yield 'TooManyVerificationAttemptsException' => [TooManyVerificationAttemptsException::class, 'TooManyVerificationAttemptsException'];
    }

    public function testPendingStatusBothConfirmed(): void
    {
        $response = $this->factory->pendingStatus('new@example.com', true, true);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('pending', $data['status']);
        $this->assertTrue($data['data']['confirmed_by_new_email']);
        $this->assertTrue($data['data']['confirmed_by_old_email']);
    }

    public function testPendingStatusNeitherConfirmed(): void
    {
        $response = $this->factory->pendingStatus('new@example.com', false, false);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('pending', $data['status']);
        $this->assertFalse($data['data']['confirmed_by_new_email']);
        $this->assertFalse($data['data']['confirmed_by_old_email']);
    }

    public function testPendingStatusDefaultConfirmationFlags(): void
    {
        $response = $this->factory->pendingStatus('new@example.com');
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['data']['confirmed_by_new_email']);
        $this->assertFalse($data['data']['confirmed_by_old_email']);
    }

    public function testInitiatedResponseDateFormat(): void
    {
        $expiresAt = new \DateTimeImmutable('2026-06-15T14:30:00+02:00');
        $response = $this->factory->initiated('new@example.com', $expiresAt);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('2026-06-15T14:30:00+02:00', $data['data']['expires_at']);
    }

    public function testTokenValidatedMessagesAreDifferent(): void
    {
        $single = $this->factory->tokenValidated(false);
        $dual = $this->factory->tokenValidated(true);

        $singleData = json_decode($single->getContent(), true);
        $dualData = json_decode($dual->getContent(), true);

        $this->assertNotSame($singleData['message'], $dualData['message']);
    }

    public function testCancelledResponseHasNoDataField(): void
    {
        $response = $this->factory->cancelled();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayNotHasKey('data', $data);
    }

    public function testNoPendingStatusHasNoPendingEmail(): void
    {
        $response = $this->factory->pendingStatus(null);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayNotHasKey('pending_email', $data['data']);
        $this->assertArrayNotHasKey('confirmed_by_new_email', $data['data']);
        $this->assertArrayNotHasKey('confirmed_by_old_email', $data['data']);
    }

    public function testErrorMessageDoesNotHaveErrorTypeField(): void
    {
        $response = $this->factory->errorMessage('Generic error');
        $data = json_decode($response->getContent(), true);

        $this->assertArrayNotHasKey('error', $data);
    }

    public function testAllResponsesAreValidJson(): void
    {
        $responses = [
            $this->factory->initiated('test@example.com', new \DateTimeImmutable()),
            $this->factory->tokenValidated(false),
            $this->factory->tokenValidated(true),
            $this->factory->confirmed('old@example.com', 'new@example.com'),
            $this->factory->cancelled(),
            $this->factory->pendingStatus('test@example.com'),
            $this->factory->pendingStatus(null),
            $this->factory->errorMessage('Error'),
            $this->factory->error(new InvalidEmailChangeRequestException('test')),
        ];

        foreach ($responses as $i => $response) {
            $decoded = json_decode($response->getContent(), true);
            $this->assertNotNull($decoded, "Response at index $i is not valid JSON");
            $this->assertArrayHasKey('status', $decoded, "Response at index $i missing 'status' key");
            $this->assertArrayHasKey('message', $decoded, "Response at index $i missing 'message' key");
        }
    }

    public function testAllSuccessResponsesReturn200(): void
    {
        $responses = [
            $this->factory->initiated('test@example.com', new \DateTimeImmutable()),
            $this->factory->tokenValidated(false),
            $this->factory->confirmed('old@example.com', 'new@example.com'),
            $this->factory->cancelled(),
            $this->factory->pendingStatus('test@example.com'),
            $this->factory->pendingStatus(null),
        ];

        foreach ($responses as $response) {
            $this->assertSame(200, $response->getStatusCode());
        }
    }
}

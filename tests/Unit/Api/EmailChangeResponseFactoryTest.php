<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\Api;

use Makraz\Bundle\VerifyEmailChange\Api\EmailChangeResponseFactory;
use Makraz\Bundle\VerifyEmailChange\Exception\ExpiredEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\InvalidEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\SameEmailException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyEmailChangeRequestsException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyVerificationAttemptsException;
use PHPUnit\Framework\TestCase;

class EmailChangeResponseFactoryTest extends TestCase
{
    private EmailChangeResponseFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new EmailChangeResponseFactory();
    }

    public function testInitiatedResponse(): void
    {
        $expiresAt = new \DateTimeImmutable('+1 hour');
        $response = $this->factory->initiated('new@example.com', $expiresAt);

        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('initiated', $data['status']);
        $this->assertSame('new@example.com', $data['data']['new_email']);
        $this->assertSame($expiresAt->format(\DateTimeInterface::ATOM), $data['data']['expires_at']);
    }

    public function testTokenValidatedResponse(): void
    {
        $response = $this->factory->tokenValidated(false);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('validated', $data['status']);
        $this->assertFalse($data['data']['requires_old_email_confirmation']);
    }

    public function testTokenValidatedWithDualMode(): void
    {
        $response = $this->factory->tokenValidated(true);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['requires_old_email_confirmation']);
        $this->assertStringContainsString('old email', $data['message']);
    }

    public function testConfirmedResponse(): void
    {
        $response = $this->factory->confirmed('old@example.com', 'new@example.com');
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('confirmed', $data['status']);
        $this->assertSame('old@example.com', $data['data']['old_email']);
        $this->assertSame('new@example.com', $data['data']['new_email']);
    }

    public function testCancelledResponse(): void
    {
        $response = $this->factory->cancelled();
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('cancelled', $data['status']);
    }

    public function testPendingStatusWithPendingChange(): void
    {
        $response = $this->factory->pendingStatus('new@example.com', true, false);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pending', $data['status']);
        $this->assertTrue($data['data']['has_pending']);
        $this->assertSame('new@example.com', $data['data']['pending_email']);
        $this->assertTrue($data['data']['confirmed_by_new_email']);
        $this->assertFalse($data['data']['confirmed_by_old_email']);
    }

    public function testPendingStatusWithNoPendingChange(): void
    {
        $response = $this->factory->pendingStatus(null);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('none', $data['status']);
        $this->assertFalse($data['data']['has_pending']);
    }

    public function testErrorFromException(): void
    {
        $exception = new SameEmailException('test@example.com');
        $response = $this->factory->error($exception);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('error', $data['status']);
        $this->assertSame('SameEmailException', $data['error']['type']);
    }

    public function testErrorWithCustomStatusCode(): void
    {
        $exception = new InvalidEmailChangeRequestException('Not found');
        $response = $this->factory->error($exception, 404);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testErrorFromExpiredException(): void
    {
        $exception = new ExpiredEmailChangeRequestException();
        $response = $this->factory->error($exception, 410);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(410, $response->getStatusCode());
        $this->assertSame('ExpiredEmailChangeRequestException', $data['error']['type']);
    }

    public function testErrorFromTooManyAttemptsException(): void
    {
        $exception = new TooManyVerificationAttemptsException(5);
        $response = $this->factory->error($exception, 429);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('TooManyVerificationAttemptsException', $data['error']['type']);
    }

    public function testErrorFromTooManyRequestsException(): void
    {
        $exception = new TooManyEmailChangeRequestsException(new \DateTimeImmutable('+1 hour'));
        $response = $this->factory->error($exception, 429);

        $this->assertSame(429, $response->getStatusCode());
    }

    public function testErrorMessage(): void
    {
        $response = $this->factory->errorMessage('Something went wrong', 500);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('error', $data['status']);
        $this->assertSame('Something went wrong', $data['message']);
    }

    public function testResponsesAreJsonResponses(): void
    {
        $response = $this->factory->initiated('new@example.com', new \DateTimeImmutable());

        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }
}

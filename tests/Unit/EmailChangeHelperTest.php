<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit;

use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeHelper;
use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeSignature;
use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Exception\ExpiredEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\InvalidEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyEmailChangeRequestsException;
use Makraz\Bundle\VerifyEmailChange\Generator\EmailChangeTokenGenerator;
use Makraz\Bundle\VerifyEmailChange\Generator\TokenComponents;
use Makraz\Bundle\VerifyEmailChange\Persistence\EmailChangeRequestRepositoryInterface;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailChangeHelperTest extends TestCase
{
    private EmailChangeRequestRepositoryInterface $repository;
    private EmailChangeTokenGenerator $tokenGenerator;
    private UrlGeneratorInterface $urlGenerator;
    private EmailChangeHelper $helper;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EmailChangeRequestRepositoryInterface::class);
        $this->tokenGenerator = $this->createMock(EmailChangeTokenGenerator::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->helper = new EmailChangeHelper(
            $this->repository,
            $this->tokenGenerator,
            $this->urlGenerator,
            3600
        );
    }

    public function testGenerateSignatureCreatesValidSignature(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $newEmail = 'new@example.com';

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('removeExpiredEmailChangeRequests');

        $tokenComponents = new TokenComponents('selector123', 'token456', 'hashedtoken789');
        $this->tokenGenerator->expects($this->once())
            ->method('createToken')
            ->willReturn($tokenComponents);

        $this->repository->expects($this->once())
            ->method('persistEmailChangeRequest')
            ->with($this->callback(function (EmailChangeRequest $request) use ($newEmail) {
                return $request->getNewEmail() === $newEmail
                    && $request->getSelector() === 'selector123'
                    && $request->getHashedToken() === 'hashedtoken789';
            }));

        $expectedUrl = 'https://example.com/verify?selector=selector123&token=token456';
        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                'verify_route',
                ['selector' => 'selector123', 'token' => 'token456'],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn($expectedUrl);

        $signature = $this->helper->generateSignature('verify_route', $user, $newEmail);

        $this->assertInstanceOf(EmailChangeSignature::class, $signature);
        $this->assertSame($expectedUrl, $signature->getSignedUrl());
    }

    public function testGenerateSignatureThrowsExceptionForTooManyRequests(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $existingRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('+1 hour'),
            'selector',
            'hashedtoken',
            'pending@example.com'
        );

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn($existingRequest);

        $this->expectException(TooManyEmailChangeRequestsException::class);

        $this->helper->generateSignature('verify_route', $user, 'new@example.com');
    }

    public function testGenerateSignatureRemovesExpiredRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $expiredRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('-1 hour'),
            'selector',
            'hashedtoken',
            'expired@example.com'
        );

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn($expiredRequest);

        $this->repository->expects($this->once())
            ->method('removeEmailChangeRequest')
            ->with($expiredRequest);

        $this->repository->expects($this->once())
            ->method('removeExpiredEmailChangeRequests');

        $tokenComponents = new TokenComponents('selector123', 'token456', 'hashedtoken789');
        $this->tokenGenerator->expects($this->once())
            ->method('createToken')
            ->willReturn($tokenComponents);

        $this->repository->expects($this->once())
            ->method('persistEmailChangeRequest');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('https://example.com/verify');

        $signature = $this->helper->generateSignature('verify_route', $user, 'new@example.com');

        $this->assertInstanceOf(EmailChangeSignature::class, $signature);
    }

    public function testGenerateSignatureWithExtraParams(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('removeExpiredEmailChangeRequests');

        $tokenComponents = new TokenComponents('selector123', 'token456', 'hashedtoken789');
        $this->tokenGenerator->expects($this->once())
            ->method('createToken')
            ->willReturn($tokenComponents);

        $this->repository->expects($this->once())
            ->method('persistEmailChangeRequest');

        $this->urlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                'verify_route',
                ['locale' => 'en', 'selector' => 'selector123', 'token' => 'token456'],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn('https://example.com/verify');

        $signature = $this->helper->generateSignature(
            'verify_route',
            $user,
            'new@example.com',
            ['locale' => 'en']
        );

        $this->assertInstanceOf(EmailChangeSignature::class, $signature);
    }

    public function testValidateTokenAndFetchUserWithValidToken(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $request = Request::create('/verify', 'GET', [
            'selector' => 'selector123',
            'token' => 'token456',
        ]);

        $emailChangeRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('+1 hour'),
            'selector123',
            'hashedtoken789',
            'new@example.com'
        );

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with('selector123')
            ->willReturn($emailChangeRequest);

        $this->tokenGenerator->expects($this->once())
            ->method('verifyToken')
            ->with($emailChangeRequest, 'token456')
            ->willReturn(true);

        $this->repository->expects($this->once())
            ->method('getUserFromRequest')
            ->with($emailChangeRequest)
            ->willReturn($user);

        $result = $this->helper->validateTokenAndFetchUser($request);

        $this->assertSame($user, $result);
    }

    public function testValidateTokenAndFetchUserThrowsExceptionForMissingSelector(): void
    {
        $request = Request::create('/verify', 'GET', ['token' => 'token456']);

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Missing or invalid verification parameters.');

        $this->helper->validateTokenAndFetchUser($request);
    }

    public function testValidateTokenAndFetchUserThrowsExceptionForMissingToken(): void
    {
        $request = Request::create('/verify', 'GET', ['selector' => 'selector123']);

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Missing or invalid verification parameters.');

        $this->helper->validateTokenAndFetchUser($request);
    }

    public function testValidateTokenAndFetchUserThrowsExceptionForInvalidSelector(): void
    {
        $request = Request::create('/verify', 'GET', [
            'selector' => 'invalid',
            'token' => 'token456',
        ]);

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with('invalid')
            ->willReturn(null);

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Invalid verification link.');

        $this->helper->validateTokenAndFetchUser($request);
    }

    public function testValidateTokenAndFetchUserThrowsExceptionForExpiredRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $request = Request::create('/verify', 'GET', [
            'selector' => 'selector123',
            'token' => 'token456',
        ]);

        $emailChangeRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('-1 hour'),
            'selector123',
            'hashedtoken789',
            'new@example.com'
        );

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with('selector123')
            ->willReturn($emailChangeRequest);

        $this->expectException(ExpiredEmailChangeRequestException::class);

        $this->helper->validateTokenAndFetchUser($request);
    }

    public function testValidateTokenAndFetchUserThrowsExceptionForInvalidToken(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $request = Request::create('/verify', 'GET', [
            'selector' => 'selector123',
            'token' => 'wrongtoken',
        ]);

        $emailChangeRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('+1 hour'),
            'selector123',
            'hashedtoken789',
            'new@example.com'
        );

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with('selector123')
            ->willReturn($emailChangeRequest);

        $this->tokenGenerator->expects($this->once())
            ->method('verifyToken')
            ->with($emailChangeRequest, 'wrongtoken')
            ->willReturn(false);

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Invalid verification token.');

        $this->helper->validateTokenAndFetchUser($request);
    }

    public function testValidateTokenAndFetchUserThrowsExceptionWhenUserNotFound(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $request = Request::create('/verify', 'GET', [
            'selector' => 'selector123',
            'token' => 'token456',
        ]);

        $emailChangeRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('+1 hour'),
            'selector123',
            'hashedtoken789',
            'new@example.com'
        );

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with('selector123')
            ->willReturn($emailChangeRequest);

        $this->tokenGenerator->expects($this->once())
            ->method('verifyToken')
            ->willReturn(true);

        $this->repository->expects($this->once())
            ->method('getUserFromRequest')
            ->with($emailChangeRequest)
            ->willReturn(null);

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('User not found.');

        $this->helper->validateTokenAndFetchUser($request);
    }

    public function testConfirmEmailChangeUpdatesEmailAndRemovesRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $emailChangeRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('+1 hour'),
            'selector123',
            'hashedtoken789',
            'new@example.com'
        );

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn($emailChangeRequest);

        $this->repository->expects($this->once())
            ->method('removeEmailChangeRequest')
            ->with($emailChangeRequest);

        $oldEmail = $this->helper->confirmEmailChange($user);

        $this->assertSame('old@example.com', $oldEmail);
        $this->assertSame('new@example.com', $user->getEmail());
    }

    public function testConfirmEmailChangeThrowsExceptionWhenNoRequestFound(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn(null);

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('No pending email change found.');

        $this->helper->confirmEmailChange($user);
    }

    public function testCancelEmailChangeRemovesRequestAndClearsPendingEmail(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $emailChangeRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('+1 hour'),
            'selector123',
            'hashedtoken789',
            'new@example.com'
        );

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn($emailChangeRequest);

        $this->repository->expects($this->once())
            ->method('removeEmailChangeRequest')
            ->with($emailChangeRequest);

        $this->helper->cancelEmailChange($user);
    }

    public function testCancelEmailChangeHandlesNoExistingRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn(null);

        $this->repository->expects($this->never())
            ->method('removeEmailChangeRequest');

        $this->helper->cancelEmailChange($user);
    }

    public function testHasPendingRequestReturnsTrueForValidRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $emailChangeRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('+1 hour'),
            'selector123',
            'hashedtoken789',
            'new@example.com'
        );

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn($emailChangeRequest);

        $result = $this->helper->hasPendingEmailChange($user);

        $this->assertTrue($result);
    }

    public function testHasPendingRequestReturnsFalseForExpiredRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $emailChangeRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('-1 hour'),
            'selector123',
            'hashedtoken789',
            'new@example.com'
        );

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn($emailChangeRequest);

        $result = $this->helper->hasPendingEmailChange($user);

        $this->assertFalse($result);
    }

    public function testHasPendingRequestReturnsFalseForNoRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn(null);

        $result = $this->helper->hasPendingEmailChange($user);

        $this->assertFalse($result);
    }

    public function testGetPendingRequestReturnsValidRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $emailChangeRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('+1 hour'),
            'selector123',
            'hashedtoken789',
            'new@example.com'
        );

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn($emailChangeRequest);

        $result = $this->helper->getPendingRequest($user);

        $this->assertSame($emailChangeRequest, $result);
    }

    public function testGetPendingRequestReturnsNullForExpiredRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $emailChangeRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('-1 hour'),
            'selector123',
            'hashedtoken789',
            'new@example.com'
        );

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn($emailChangeRequest);

        $result = $this->helper->getPendingRequest($user);

        $this->assertNull($result);
    }

    public function testGetPendingRequestReturnsNullWhenNoRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn(null);

        $result = $this->helper->getPendingRequest($user);

        $this->assertNull($result);
    }

    public function testGetPendingEmailReturnsValidEmail(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $emailChangeRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('+1 hour'),
            'selector123',
            'hashedtoken789',
            'new@example.com'
        );

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn($emailChangeRequest);

        $result = $this->helper->getPendingEmail($user);

        $this->assertSame('new@example.com', $result);
    }

    public function testGetPendingEmailReturnsNullForExpiredRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $emailChangeRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('-1 hour'),
            'selector123',
            'hashedtoken789',
            'new@example.com'
        );

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn($emailChangeRequest);

        $result = $this->helper->getPendingEmail($user);

        $this->assertNull($result);
    }

    public function testGetPendingEmailReturnsNullWhenNoRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $this->repository->expects($this->once())
            ->method('findEmailChangeRequest')
            ->with($user)
            ->willReturn(null);

        $result = $this->helper->getPendingEmail($user);

        $this->assertNull($result);
    }
}

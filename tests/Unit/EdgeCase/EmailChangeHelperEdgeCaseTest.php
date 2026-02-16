<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\EdgeCase;

use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeDualSignature;
use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeHelper;
use Makraz\Bundle\VerifyEmailChange\Exception\ExpiredEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\InvalidEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyVerificationAttemptsException;
use Makraz\Bundle\VerifyEmailChange\Generator\EmailChangeTokenGenerator;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\EmailChangeRequestTestRepository;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class EmailChangeHelperEdgeCaseTest extends TestCase
{
    private EmailChangeRequestTestRepository $repository;
    private UrlGenerator $urlGenerator;

    protected function setUp(): void
    {
        $this->repository = new EmailChangeRequestTestRepository();

        $routes = new RouteCollection();
        $routes->add('verify_route', new Route('/verify'));
        $context = new RequestContext();
        $context->setScheme('https');
        $context->setHost('example.com');
        $this->urlGenerator = new UrlGenerator($routes, $context);
    }

    private function createHelper(int $maxAttempts = 5, bool $dualMode = false): EmailChangeHelper
    {
        return new EmailChangeHelper(
            $this->repository,
            new EmailChangeTokenGenerator(),
            $this->urlGenerator,
            3600,
            $maxAttempts,
            $dualMode,
        );
    }

    public function testValidateTokenWithEmptyStringSelector(): void
    {
        $helper = $this->createHelper();
        $request = Request::create('/verify', 'GET', [
            'selector' => '',
            'token' => 'sometoken',
        ]);

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Missing or invalid verification parameters.');

        $helper->validateTokenAndFetchUser($request);
    }

    public function testValidateTokenWithEmptyStringToken(): void
    {
        $helper = $this->createHelper();
        $request = Request::create('/verify', 'GET', [
            'selector' => 'someselector',
            'token' => '',
        ]);

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Missing or invalid verification parameters.');

        $helper->validateTokenAndFetchUser($request);
    }

    public function testValidateTokenWithBothEmpty(): void
    {
        $helper = $this->createHelper();
        $request = Request::create('/verify', 'GET', [
            'selector' => '',
            'token' => '',
        ]);

        $this->expectException(InvalidEmailChangeRequestException::class);
        $helper->validateTokenAndFetchUser($request);
    }

    public function testValidateTokenWithNoQueryParams(): void
    {
        $helper = $this->createHelper();
        $request = Request::create('/verify', 'GET');

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Missing or invalid verification parameters.');

        $helper->validateTokenAndFetchUser($request);
    }

    public function testValidateOldEmailTokenWithMissingParams(): void
    {
        $helper = $this->createHelper(5, true);
        $request = Request::create('/verify', 'GET');

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Missing or invalid verification parameters.');

        $helper->validateOldEmailToken($request);
    }

    public function testValidateOldEmailTokenWithEmptySelector(): void
    {
        $helper = $this->createHelper(5, true);
        $request = Request::create('/verify', 'GET', [
            'selector' => '',
            'token' => 'sometoken',
        ]);

        $this->expectException(InvalidEmailChangeRequestException::class);
        $helper->validateOldEmailToken($request);
    }

    public function testValidateOldEmailTokenWithNonExistentSelector(): void
    {
        $helper = $this->createHelper(5, true);
        $request = Request::create('/verify', 'GET', [
            'selector' => 'nonexistent',
            'token' => 'sometoken',
        ]);

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Invalid verification link.');

        $helper->validateOldEmailToken($request);
    }

    public function testValidateOldEmailTokenWhenExpired(): void
    {
        $helper = $this->createHelper(5, true);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        /** @var EmailChangeDualSignature $signature */
        $signature = $helper->generateSignature('verify_route', $user, 'new@example.com');

        // Manually expire the request
        $pendingRequest = $this->repository->findEmailChangeRequest($user);
        $this->assertNotNull($pendingRequest);

        // Create a new expired request with the same old email selector
        $expiredRequest = new \Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest(
            $user,
            new \DateTimeImmutable('-1 hour'),
            $pendingRequest->getSelector(),
            $pendingRequest->getHashedToken(),
            'new@example.com'
        );
        $expiredRequest->setOldEmailSelector($pendingRequest->getOldEmailSelector());
        $expiredRequest->setOldEmailHashedToken($pendingRequest->getOldEmailHashedToken());

        // Replace with expired version
        $this->repository->removeEmailChangeRequest($pendingRequest);
        $this->repository->persistEmailChangeRequest($expiredRequest);

        parse_str(parse_url($signature->getOldEmailSignedUrl(), PHP_URL_QUERY), $oldParams);
        $request = Request::create('/verify', 'GET', $oldParams);

        $this->expectException(ExpiredEmailChangeRequestException::class);
        $helper->validateOldEmailToken($request);
    }

    public function testValidateOldEmailTokenWithWrongToken(): void
    {
        $helper = $this->createHelper(5, true);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        /** @var EmailChangeDualSignature $signature */
        $signature = $helper->generateSignature('verify_route', $user, 'new@example.com');

        parse_str(parse_url($signature->getOldEmailSignedUrl(), PHP_URL_QUERY), $oldParams);
        $oldParams['token'] = 'wrong_token_value';

        $request = Request::create('/verify', 'GET', $oldParams);

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Invalid verification token.');

        $helper->validateOldEmailToken($request);
    }

    public function testValidateOldEmailTokenWhenUserNotFound(): void
    {
        $helper = $this->createHelper(5, true);
        $user = new TestUser(1, 'old@example.com');
        // Intentionally NOT registering user in repository

        /** @var EmailChangeDualSignature $signature */
        $signature = $helper->generateSignature('verify_route', $user, 'new@example.com');

        parse_str(parse_url($signature->getOldEmailSignedUrl(), PHP_URL_QUERY), $oldParams);
        $request = Request::create('/verify', 'GET', $oldParams);

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('User not found.');

        $helper->validateOldEmailToken($request);
    }

    public function testCorrectTokenSucceedsAfterFailedAttempts(): void
    {
        $helper = $this->createHelper(5);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $signature = $helper->generateSignature('verify_route', $user, 'new@example.com');
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $params);

        // Fail 4 times (max is 5)
        for ($i = 0; $i < 4; ++$i) {
            $wrongRequest = Request::create('/verify', 'GET', [
                'selector' => $params['selector'],
                'token' => 'wrong_'.$i,
            ]);

            try {
                $helper->validateTokenAndFetchUser($wrongRequest);
                $this->fail('Expected InvalidEmailChangeRequestException');
            } catch (InvalidEmailChangeRequestException) {
            }
        }

        // 5th attempt with correct token should still succeed
        $correctRequest = Request::create('/verify', 'GET', $params);
        $validatedUser = $helper->validateTokenAndFetchUser($correctRequest);

        $this->assertSame(1, $validatedUser->getId());
    }

    public function testMaxAttemptsExactlyAtThreshold(): void
    {
        $helper = $this->createHelper(2);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $signature = $helper->generateSignature('verify_route', $user, 'new@example.com');
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $params);

        // First wrong attempt - should get InvalidEmailChangeRequestException
        $request = Request::create('/verify', 'GET', [
            'selector' => $params['selector'],
            'token' => 'wrong',
        ]);

        try {
            $helper->validateTokenAndFetchUser($request);
            $this->fail('Expected exception');
        } catch (InvalidEmailChangeRequestException) {
        }

        // Second wrong attempt - should get TooManyVerificationAttemptsException
        $this->expectException(TooManyVerificationAttemptsException::class);
        $helper->validateTokenAndFetchUser($request);
    }

    public function testRequestDeletedAfterMaxAttemptsCannotBeUsedAgain(): void
    {
        $helper = $this->createHelper(1);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $signature = $helper->generateSignature('verify_route', $user, 'new@example.com');
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $params);

        // Exhaust attempts
        try {
            $helper->validateTokenAndFetchUser(Request::create('/verify', 'GET', [
                'selector' => $params['selector'],
                'token' => 'wrong',
            ]));
        } catch (TooManyVerificationAttemptsException) {
        }

        // Now try with the correct token — request is gone
        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Invalid verification link.');

        $helper->validateTokenAndFetchUser(Request::create('/verify', 'GET', $params));
    }

    public function testConfirmEmailChangeInDualModeWithNoConfirmations(): void
    {
        $helper = $this->createHelper(5, true);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $helper->generateSignature('verify_route', $user, 'new@example.com');

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Email change requires confirmation from both old and new email addresses.');

        $helper->confirmEmailChange($user);
    }

    public function testConfirmEmailChangeInDualModeOnlyOldConfirmed(): void
    {
        $helper = $this->createHelper(5, true);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        /** @var EmailChangeDualSignature $signature */
        $signature = $helper->generateSignature('verify_route', $user, 'new@example.com');

        // Confirm old email only
        parse_str(parse_url($signature->getOldEmailSignedUrl(), PHP_URL_QUERY), $oldParams);
        $helper->validateOldEmailToken(Request::create('/verify', 'GET', $oldParams));

        $this->expectException(InvalidEmailChangeRequestException::class);
        $helper->confirmEmailChange($user);
    }

    public function testDualModeOldEmailConfirmationFirstThenNew(): void
    {
        $helper = $this->createHelper(5, true);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        /** @var EmailChangeDualSignature $signature */
        $signature = $helper->generateSignature('verify_route', $user, 'new@example.com');

        // Confirm old email first
        parse_str(parse_url($signature->getOldEmailSignedUrl(), PHP_URL_QUERY), $oldParams);
        $helper->validateOldEmailToken(Request::create('/verify', 'GET', $oldParams));

        // Then confirm new email
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $newParams);
        $helper->validateTokenAndFetchUser(Request::create('/verify', 'GET', $newParams));

        // Should succeed now
        $oldEmail = $helper->confirmEmailChange($user);
        $this->assertSame('old@example.com', $oldEmail);
        $this->assertSame('new@example.com', $user->getEmail());
    }

    public function testCancelAfterPartialDualConfirmation(): void
    {
        $helper = $this->createHelper(5, true);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        /** @var EmailChangeDualSignature $signature */
        $signature = $helper->generateSignature('verify_route', $user, 'new@example.com');

        // Confirm new email
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $newParams);
        $helper->validateTokenAndFetchUser(Request::create('/verify', 'GET', $newParams));

        // Cancel before old email confirms
        $helper->cancelEmailChange($user);

        $this->assertFalse($helper->hasPendingEmailChange($user));
        $this->assertSame('old@example.com', $user->getEmail());
    }

    public function testIsRequireOldEmailConfirmationGetter(): void
    {
        $helperSingle = $this->createHelper(5, false);
        $helperDual = $this->createHelper(5, true);

        $this->assertFalse($helperSingle->isRequireOldEmailConfirmation());
        $this->assertTrue($helperDual->isRequireOldEmailConfirmation());
    }

    public function testHasPendingRequestAndHasPendingEmailChangeAreConsistent(): void
    {
        $helper = $this->createHelper();
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        // Before generating
        $this->assertFalse($helper->hasPendingEmailChange($user));
        $this->assertFalse($helper->hasPendingRequest($user));

        $helper->generateSignature('verify_route', $user, 'new@example.com');

        // After generating
        $this->assertTrue($helper->hasPendingEmailChange($user));
        $this->assertTrue($helper->hasPendingRequest($user));

        $helper->cancelEmailChange($user);

        // After cancelling
        $this->assertFalse($helper->hasPendingEmailChange($user));
        $this->assertFalse($helper->hasPendingRequest($user));
    }

    public function testNewRequestReplacesExpiredOne(): void
    {
        $helper = new EmailChangeHelper(
            $this->repository,
            new EmailChangeTokenGenerator(),
            $this->urlGenerator,
            1, // 1 second lifetime
            5,
        );

        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $helper->generateSignature('verify_route', $user, 'first@example.com');

        // Wait for it to expire
        sleep(2);

        // Should succeed — the old request is expired
        $signature = $helper->generateSignature('verify_route', $user, 'second@example.com');
        $this->assertNotNull($signature);
        $this->assertSame('second@example.com', $helper->getPendingEmail($user));
    }

    public function testGetPendingRequestReturnsNullAfterConfirm(): void
    {
        $helper = $this->createHelper();
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $signature = $helper->generateSignature('verify_route', $user, 'new@example.com');
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $params);

        $helper->validateTokenAndFetchUser(Request::create('/verify', 'GET', $params));
        $helper->confirmEmailChange($user);

        $this->assertNull($helper->getPendingRequest($user));
        $this->assertNull($helper->getPendingEmail($user));
    }

    public function testMultipleUsersIndependentRequests(): void
    {
        $helper = $this->createHelper();
        $user1 = new TestUser(1, 'user1@example.com');
        $user2 = new TestUser(2, 'user2@example.com');
        $this->repository->registerUser($user1);
        $this->repository->registerUser($user2);

        $helper->generateSignature('verify_route', $user1, 'new1@example.com');
        $helper->generateSignature('verify_route', $user2, 'new2@example.com');

        $this->assertSame('new1@example.com', $helper->getPendingEmail($user1));
        $this->assertSame('new2@example.com', $helper->getPendingEmail($user2));

        // Cancel user1's request — user2 should be unaffected
        $helper->cancelEmailChange($user1);

        $this->assertNull($helper->getPendingEmail($user1));
        $this->assertSame('new2@example.com', $helper->getPendingEmail($user2));
    }
}

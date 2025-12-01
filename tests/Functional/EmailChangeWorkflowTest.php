<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Functional;

use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeHelper;
use Makraz\Bundle\VerifyEmailChange\Exception\ExpiredEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\InvalidEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyEmailChangeRequestsException;
use Makraz\Bundle\VerifyEmailChange\Generator\EmailChangeTokenGenerator;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\EmailChangeRequestTestRepository;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Functional tests for the complete email change workflow.
 */
class EmailChangeWorkflowTest extends TestCase
{
    private EmailChangeHelper $helper;
    private EmailChangeRequestTestRepository $repository;
    private UrlGenerator $urlGenerator;

    protected function setUp(): void
    {
        $this->repository = new EmailChangeRequestTestRepository();
        $tokenGenerator = new EmailChangeTokenGenerator();

        // Set up URL generator with test route
        $routes = new RouteCollection();
        $routes->add('verify_email_change', new Route('/verify'));
        $context = new RequestContext();
        $context->setScheme('https');
        $context->setHost('example.com');
        $this->urlGenerator = new UrlGenerator($routes, $context);

        $this->helper = new EmailChangeHelper(
            $this->repository,
            $tokenGenerator,
            $this->urlGenerator,
            3600
        );
    }

    protected function tearDown(): void
    {
        $this->repository->clear();
    }

    public function testCompleteEmailChangeWorkflow(): void
    {
        // Step 1: User initiates email change
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);
        $newEmail = 'new@example.com';

        $signature = $this->helper->generateSignature('verify_email_change', $user, $newEmail);

        // Verify signature was created
        $this->assertStringContainsString('https://example.com/verify', $signature->getSignedUrl());
        $this->assertStringContainsString('selector=', $signature->getSignedUrl());
        $this->assertStringContainsString('token=', $signature->getSignedUrl());

        // Verify request was stored
        $this->assertTrue($this->helper->hasPendingEmailChange($user));

        // Step 2: User clicks verification link
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $params);
        $request = Request::create('/verify', 'GET', $params);

        $validatedUser = $this->helper->validateTokenAndFetchUser($request);
        $this->assertSame($user->getId(), $validatedUser->getId());

        // Step 3: Confirm email change
        $oldEmail = $this->helper->confirmEmailChange($user);

        $this->assertSame('old@example.com', $oldEmail);
        $this->assertSame($newEmail, $user->getEmail());

        // Verify request was removed
        $this->assertFalse($this->helper->hasPendingEmailChange($user));
    }

    public function testCannotCreateMultipleRequestsQuickly(): void
    {
        $user = new TestUser(1, 'old@example.com');

        // First request succeeds
        $this->helper->generateSignature('verify_email_change', $user, 'new1@example.com');

        // Second request immediately fails
        $this->expectException(TooManyEmailChangeRequestsException::class);
        $this->helper->generateSignature('verify_email_change', $user, 'new2@example.com');
    }

    public function testExpiredRequestCanBeReplaced(): void
    {
        $user = new TestUser(1, 'old@example.com');

        // Create a helper with very short lifetime (1 second)
        $shortLivedHelper = new EmailChangeHelper(
            $this->repository,
            new EmailChangeTokenGenerator(),
            $this->urlGenerator,
            1
        );

        // Create first request
        $signature1 = $shortLivedHelper->generateSignature('verify_email_change', $user, 'new1@example.com');

        // Wait for it to expire
        sleep(2);

        // Now we can create a new request
        $signature2 = $shortLivedHelper->generateSignature('verify_email_change', $user, 'new2@example.com');

        $this->assertNotSame($signature1->getSignedUrl(), $signature2->getSignedUrl());
    }

    public function testExpiredTokenCannotBeValidated(): void
    {
        $user = new TestUser(1, 'old@example.com');

        // Create a helper with very short lifetime
        $shortLivedHelper = new EmailChangeHelper(
            $this->repository,
            new EmailChangeTokenGenerator(),
            $this->urlGenerator,
            1
        );

        $signature = $shortLivedHelper->generateSignature('verify_email_change', $user, 'new@example.com');

        // Extract parameters
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $params);

        // Wait for expiration
        sleep(2);

        // Try to validate
        $request = Request::create('/verify', 'GET', $params);

        $this->expectException(ExpiredEmailChangeRequestException::class);
        $shortLivedHelper->validateTokenAndFetchUser($request);
    }

    public function testInvalidTokenIsRejected(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $signature = $this->helper->generateSignature('verify_email_change', $user, 'new@example.com');

        // Extract parameters and modify the token
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $params);
        $params['token'] = 'invalid_token_12345';

        $request = Request::create('/verify', 'GET', $params);

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Invalid verification token.');
        $this->helper->validateTokenAndFetchUser($request);
    }

    public function testInvalidSelectorIsRejected(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $signature = $this->helper->generateSignature('verify_email_change', $user, 'new@example.com');

        // Use completely wrong selector
        $request = Request::create('/verify', 'GET', [
            'selector' => 'nonexistent',
            'token' => 'sometoken',
        ]);

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Invalid verification link.');
        $this->helper->validateTokenAndFetchUser($request);
    }

    public function testCancelEmailChangeRemovesRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $newEmail = 'new@example.com';

        // Create request
        $this->helper->generateSignature('verify_email_change', $user, $newEmail);

        $this->assertTrue($this->helper->hasPendingEmailChange($user));

        // Cancel it
        $this->helper->cancelEmailChange($user);

        $this->assertFalse($this->helper->hasPendingEmailChange($user));
    }

    public function testCancelNonExistentRequestIsHandledGracefully(): void
    {
        $user = new TestUser(1, 'old@example.com');

        // Verify no request exists
        $this->assertFalse($this->helper->hasPendingEmailChange($user));

        // Cancel when no request exists - should not throw exception
        $this->helper->cancelEmailChange($user);

        // Verify still no request
        $this->assertFalse($this->helper->hasPendingEmailChange($user));
    }

    public function testCannotConfirmWithoutValidation(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('No pending email change found.');
        $this->helper->confirmEmailChange($user);
    }

    public function testMultipleUsersCanHavePendingRequests(): void
    {
        $user1 = new TestUser(1, 'user1@example.com');
        $user2 = new TestUser(2, 'user2@example.com');
        $this->repository->registerUser($user1);
        $this->repository->registerUser($user2);

        $signature1 = $this->helper->generateSignature('verify_email_change', $user1, 'new1@example.com');
        $signature2 = $this->helper->generateSignature('verify_email_change', $user2, 'new2@example.com');

        $this->assertTrue($this->helper->hasPendingEmailChange($user1));
        $this->assertTrue($this->helper->hasPendingEmailChange($user2));

        $this->assertNotSame($signature1->getSignedUrl(), $signature2->getSignedUrl());

        // Validate both
        parse_str(parse_url($signature1->getSignedUrl(), PHP_URL_QUERY), $params1);
        $request1 = Request::create('/verify', 'GET', $params1);
        $validatedUser1 = $this->helper->validateTokenAndFetchUser($request1);

        parse_str(parse_url($signature2->getSignedUrl(), PHP_URL_QUERY), $params2);
        $request2 = Request::create('/verify', 'GET', $params2);
        $validatedUser2 = $this->helper->validateTokenAndFetchUser($request2);

        $this->assertSame(1, $validatedUser1->getId());
        $this->assertSame(2, $validatedUser2->getId());
    }

    public function testGetPendingRequestReturnsRequestDetails(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $newEmail = 'new@example.com';

        $this->helper->generateSignature('verify_email_change', $user, $newEmail);

        $request = $this->helper->getPendingRequest($user);

        $this->assertNotNull($request);
        $this->assertSame($newEmail, $request->getNewEmail());
        $this->assertFalse($request->isExpired());
    }

    public function testGetPendingRequestReturnsNullWhenNoRequest(): void
    {
        $user = new TestUser(1, 'old@example.com');

        $request = $this->helper->getPendingRequest($user);

        $this->assertNull($request);
    }

    public function testEmailChangeWithExtraRouteParameters(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $signature = $this->helper->generateSignature(
            'verify_email_change',
            $user,
            'new@example.com',
            ['locale' => 'en', 'utm_source' => 'email']
        );

        $url = $signature->getSignedUrl();
        $this->assertStringContainsString('locale=en', $url);
        $this->assertStringContainsString('utm_source=email', $url);

        // Extract all parameters
        parse_str(parse_url($url, PHP_URL_QUERY), $params);

        $this->assertArrayHasKey('locale', $params);
        $this->assertArrayHasKey('utm_source', $params);
        $this->assertArrayHasKey('selector', $params);
        $this->assertArrayHasKey('token', $params);

        // Validation should still work
        $request = Request::create('/verify', 'GET', $params);
        $validatedUser = $this->helper->validateTokenAndFetchUser($request);

        $this->assertSame($user->getId(), $validatedUser->getId());
    }
}

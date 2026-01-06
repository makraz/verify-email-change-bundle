<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\Security;

use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeHelper;
use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
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

class MaxVerificationAttemptsTest extends TestCase
{
    private EmailChangeRequestTestRepository $repository;
    private UrlGenerator $urlGenerator;

    protected function setUp(): void
    {
        $this->repository = new EmailChangeRequestTestRepository();

        $routes = new RouteCollection();
        $routes->add('verify_email_change', new Route('/verify'));
        $context = new RequestContext();
        $context->setScheme('https');
        $context->setHost('example.com');
        $this->urlGenerator = new UrlGenerator($routes, $context);
    }

    private function createHelper(int $maxAttempts = 5): EmailChangeHelper
    {
        return new EmailChangeHelper(
            $this->repository,
            new EmailChangeTokenGenerator(),
            $this->urlGenerator,
            3600,
            $maxAttempts,
        );
    }

    public function testVerificationFailsAfterMaxAttemptsExceeded(): void
    {
        $helper = $this->createHelper(3);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $signature = $helper->generateSignature('verify_email_change', $user, 'new@example.com');
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $params);

        // First 2 attempts with wrong token should fail with InvalidEmailChangeRequestException
        for ($i = 0; $i < 2; $i++) {
            $request = Request::create('/verify', 'GET', [
                'selector' => $params['selector'],
                'token' => 'wrong_token_'.$i,
            ]);

            try {
                $helper->validateTokenAndFetchUser($request);
                $this->fail('Expected InvalidEmailChangeRequestException');
            } catch (InvalidEmailChangeRequestException) {
                // Expected
            }
        }

        // Third attempt should trigger TooManyVerificationAttemptsException
        $request = Request::create('/verify', 'GET', [
            'selector' => $params['selector'],
            'token' => 'wrong_token_final',
        ]);

        $this->expectException(TooManyVerificationAttemptsException::class);
        $helper->validateTokenAndFetchUser($request);
    }

    public function testAttemptCounterIncrementsOnFailedAttempt(): void
    {
        $helper = $this->createHelper(5);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $signature = $helper->generateSignature('verify_email_change', $user, 'new@example.com');
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $params);

        $request = Request::create('/verify', 'GET', [
            'selector' => $params['selector'],
            'token' => 'wrong_token',
        ]);

        try {
            $helper->validateTokenAndFetchUser($request);
        } catch (InvalidEmailChangeRequestException) {
            // Expected
        }

        $emailChangeRequest = $this->repository->findEmailChangeRequest($params['selector']);
        $this->assertNotNull($emailChangeRequest);
        $this->assertSame(1, $emailChangeRequest->getAttempts());
    }

    public function testSuccessfulVerificationWorksRegardlessOfAttemptCount(): void
    {
        $helper = $this->createHelper(5);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $signature = $helper->generateSignature('verify_email_change', $user, 'new@example.com');
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $params);

        // Fail twice
        for ($i = 0; $i < 2; $i++) {
            $request = Request::create('/verify', 'GET', [
                'selector' => $params['selector'],
                'token' => 'wrong_token',
            ]);

            try {
                $helper->validateTokenAndFetchUser($request);
            } catch (InvalidEmailChangeRequestException) {
                // Expected
            }
        }

        // Now use the correct token — should still work
        $request = Request::create('/verify', 'GET', $params);
        $validatedUser = $helper->validateTokenAndFetchUser($request);
        $this->assertSame(1, $validatedUser->getId());
    }

    public function testConfigurableMaxAttemptsValue(): void
    {
        // With max_attempts=1, first wrong attempt should invalidate
        $helper = $this->createHelper(1);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $signature = $helper->generateSignature('verify_email_change', $user, 'new@example.com');
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $params);

        $request = Request::create('/verify', 'GET', [
            'selector' => $params['selector'],
            'token' => 'wrong_token',
        ]);

        $this->expectException(TooManyVerificationAttemptsException::class);
        $helper->validateTokenAndFetchUser($request);
    }

    public function testRequestIsDeletedAfterMaxAttempts(): void
    {
        $helper = $this->createHelper(1);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $signature = $helper->generateSignature('verify_email_change', $user, 'new@example.com');
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $params);

        $request = Request::create('/verify', 'GET', [
            'selector' => $params['selector'],
            'token' => 'wrong_token',
        ]);

        try {
            $helper->validateTokenAndFetchUser($request);
        } catch (TooManyVerificationAttemptsException) {
            // Expected
        }

        // Verify the request was deleted
        $this->assertNull($this->repository->findEmailChangeRequest($params['selector']));
        $this->assertFalse($helper->hasPendingEmailChange($user));
    }

    public function testTooManyVerificationAttemptsExceptionContainsMaxAttempts(): void
    {
        $exception = new TooManyVerificationAttemptsException(5);
        $this->assertSame(5, $exception->getMaxAttempts());
        $this->assertStringContainsString('5', $exception->getReason());
    }
}

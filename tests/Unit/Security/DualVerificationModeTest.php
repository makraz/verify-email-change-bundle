<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\Security;

use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeDualSignature;
use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeHelper;
use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeSignature;
use Makraz\Bundle\VerifyEmailChange\Exception\InvalidEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Generator\EmailChangeTokenGenerator;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\EmailChangeRequestTestRepository;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class DualVerificationModeTest extends TestCase
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

    private function createHelper(bool $dualMode = true): EmailChangeHelper
    {
        return new EmailChangeHelper(
            $this->repository,
            new EmailChangeTokenGenerator(),
            $this->urlGenerator,
            3600,
            5,
            $dualMode,
        );
    }

    public function testDualModeGeneratesDualSignature(): void
    {
        $helper = $this->createHelper(true);
        $user = new TestUser(1, 'old@example.com');

        $signature = $helper->generateSignature('verify_email_change', $user, 'new@example.com');

        $this->assertInstanceOf(EmailChangeDualSignature::class, $signature);
        $this->assertStringContainsString('https://example.com/verify', $signature->getSignedUrl());
        $this->assertStringContainsString('https://example.com/verify', $signature->getOldEmailSignedUrl());
        $this->assertStringContainsString('confirm_old=1', $signature->getOldEmailSignedUrl());
    }

    public function testSingleModeGeneratesRegularSignature(): void
    {
        $helper = $this->createHelper(false);
        $user = new TestUser(1, 'old@example.com');

        $signature = $helper->generateSignature('verify_email_change', $user, 'new@example.com');

        $this->assertInstanceOf(EmailChangeSignature::class, $signature);
        $this->assertNotInstanceOf(EmailChangeDualSignature::class, $signature);
    }

    public function testEmailChangeRequiresBothConfirmationsWhenEnabled(): void
    {
        $helper = $this->createHelper(true);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        /** @var EmailChangeDualSignature $signature */
        $signature = $helper->generateSignature('verify_email_change', $user, 'new@example.com');

        // Confirm new email
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $newParams);
        $request = Request::create('/verify', 'GET', $newParams);
        $helper->validateTokenAndFetchUser($request);

        // Try to confirm without old email - should fail
        $this->expectException(InvalidEmailChangeRequestException::class);
        $this->expectExceptionMessage('Email change requires confirmation from both old and new email addresses.');
        $helper->confirmEmailChange($user);
    }

    public function testEmailChangeCompletesWithBothConfirmations(): void
    {
        $helper = $this->createHelper(true);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        /** @var EmailChangeDualSignature $signature */
        $signature = $helper->generateSignature('verify_email_change', $user, 'new@example.com');

        // Confirm new email
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $newParams);
        $request = Request::create('/verify', 'GET', $newParams);
        $helper->validateTokenAndFetchUser($request);

        // Confirm old email
        parse_str(parse_url($signature->getOldEmailSignedUrl(), PHP_URL_QUERY), $oldParams);
        $request = Request::create('/verify', 'GET', $oldParams);
        $helper->validateOldEmailToken($request);

        // Now confirm should succeed
        $oldEmail = $helper->confirmEmailChange($user);
        $this->assertSame('old@example.com', $oldEmail);
        $this->assertSame('new@example.com', $user->getEmail());
    }

    public function testSingleModeWorksWithoutDualConfirmation(): void
    {
        $helper = $this->createHelper(false);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $signature = $helper->generateSignature('verify_email_change', $user, 'new@example.com');

        // Confirm new email only
        parse_str(parse_url($signature->getSignedUrl(), PHP_URL_QUERY), $params);
        $request = Request::create('/verify', 'GET', $params);
        $helper->validateTokenAndFetchUser($request);

        // Confirm should succeed without old email confirmation
        $oldEmail = $helper->confirmEmailChange($user);
        $this->assertSame('old@example.com', $oldEmail);
        $this->assertSame('new@example.com', $user->getEmail());
    }

    public function testPartialConfirmationState(): void
    {
        $helper = $this->createHelper(true);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        /** @var EmailChangeDualSignature $signature */
        $signature = $helper->generateSignature('verify_email_change', $user, 'new@example.com');

        // Only confirm old email
        parse_str(parse_url($signature->getOldEmailSignedUrl(), PHP_URL_QUERY), $oldParams);
        $request = Request::create('/verify', 'GET', $oldParams);
        $helper->validateOldEmailToken($request);

        // Check the request state
        $pendingRequest = $helper->getPendingRequest($user);
        $this->assertNotNull($pendingRequest);
        $this->assertTrue($pendingRequest->isConfirmedByOldEmail());
        $this->assertFalse($pendingRequest->isConfirmedByNewEmail());

        // Should still fail because new email not confirmed
        $this->expectException(InvalidEmailChangeRequestException::class);
        $helper->confirmEmailChange($user);
    }

    public function testCancellationWorksInDualMode(): void
    {
        $helper = $this->createHelper(true);
        $user = new TestUser(1, 'old@example.com');
        $this->repository->registerUser($user);

        $helper->generateSignature('verify_email_change', $user, 'new@example.com');

        $this->assertTrue($helper->hasPendingEmailChange($user));

        $helper->cancelEmailChange($user);

        $this->assertFalse($helper->hasPendingEmailChange($user));
    }

    public function testDualModeOldEmailTokensAreDifferentFromNewEmailTokens(): void
    {
        $helper = $this->createHelper(true);
        $user = new TestUser(1, 'old@example.com');

        /** @var EmailChangeDualSignature $signature */
        $signature = $helper->generateSignature('verify_email_change', $user, 'new@example.com');

        $this->assertNotSame($signature->getSignedUrl(), $signature->getOldEmailSignedUrl());
    }
}

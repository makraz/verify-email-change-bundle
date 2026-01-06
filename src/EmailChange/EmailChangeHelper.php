<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\EmailChange;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Exception\ExpiredEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\InvalidEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyEmailChangeRequestsException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyVerificationAttemptsException;
use Makraz\Bundle\VerifyEmailChange\Generator\EmailChangeTokenGenerator;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;
use Makraz\Bundle\VerifyEmailChange\Persistence\EmailChangeRequestRepositoryInterface;

class EmailChangeHelper
{
    private const RETRY_TTL = 3600; // 1 hour between requests

    public function __construct(
        private readonly EmailChangeRequestRepositoryInterface $repository,
        private readonly EmailChangeTokenGenerator $tokenGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly int $requestLifetime = 3600,
        private readonly int $maxAttempts = 5,
    ) {
    }

    /**
     * Generate a signed verification URL for an email change request.
     *
     * @param string $routeName     The route name for the verification endpoint
     * @param EmailChangeableInterface $user The user requesting the change
     * @param string $newEmail      The new email address
     * @param array  $extraParams   Additional route parameters
     *
     * @throws TooManyEmailChangeRequestsException if user has a recent pending request
     *
     * @return EmailChangeSignature Contains the signed URL and expiration info
     */
    public function generateSignature(
        string $routeName,
        EmailChangeableInterface $user,
        string $newEmail,
        array $extraParams = []
    ): EmailChangeSignature {
        $existingRequest = $this->repository->findEmailChangeRequest($user);
        if ($existingRequest && !$existingRequest->isExpired()) {
            $availableAt = $existingRequest->getRequestedAt()->modify('+'.self::RETRY_TTL.' seconds');
            throw new TooManyEmailChangeRequestsException($availableAt);
        }

        $this->repository->removeExpiredEmailChangeRequests();

        if ($existingRequest) {
            $this->repository->removeEmailChangeRequest($existingRequest);
        }

        $expiresAt = new \DateTimeImmutable('+'.$this->requestLifetime.' seconds');
        $tokenComponents = $this->tokenGenerator->createToken($expiresAt, $user->getId(), $newEmail);

        $emailChangeRequest = new EmailChangeRequest(
            $user,
            $expiresAt,
            $tokenComponents->getSelector(),
            $tokenComponents->getHashedToken(),
            $newEmail
        );

        $this->repository->persistEmailChangeRequest($emailChangeRequest);

        $url = $this->urlGenerator->generate(
            $routeName,
            array_merge($extraParams, [
                'selector' => $tokenComponents->getSelector(),
                'token' => $tokenComponents->getToken(),
            ]),
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new EmailChangeSignature($url, $expiresAt);
    }

    /**
     * Validate an email change request from URL parameters and fetch the user.
     *
     * @throws ExpiredEmailChangeRequestException  if the link has expired
     * @throws InvalidEmailChangeRequestException if the link is invalid
     *
     * @return EmailChangeableInterface The user who initiated the email change
     */
    public function validateTokenAndFetchUser(Request $request): EmailChangeableInterface
    {
        $selector = $request->query->get('selector');
        $token = $request->query->get('token');

        if (!$selector || !$token || !is_string($selector) || !is_string($token)) {
            throw new InvalidEmailChangeRequestException('Missing or invalid verification parameters.');
        }

        $emailChangeRequest = $this->repository->findEmailChangeRequest($selector);

        if (!$emailChangeRequest) {
            throw new InvalidEmailChangeRequestException('Invalid verification link.');
        }

        if ($emailChangeRequest->isExpired()) {
            throw new ExpiredEmailChangeRequestException();
        }

        if (!$this->tokenGenerator->verifyToken($emailChangeRequest, $token)) {
            $emailChangeRequest->incrementAttempts();

            if ($emailChangeRequest->getAttempts() >= $this->maxAttempts) {
                $this->repository->removeEmailChangeRequest($emailChangeRequest);
                throw new TooManyVerificationAttemptsException($this->maxAttempts);
            }

            $this->repository->persistEmailChangeRequest($emailChangeRequest);
            throw new InvalidEmailChangeRequestException('Invalid verification token.');
        }

        $user = $this->repository->getUserFromRequest($emailChangeRequest);

        if (!$user) {
            throw new InvalidEmailChangeRequestException('User not found.');
        }

        return $user;
    }

    /**
     * Complete the email change after validation.
     *
     * @throws InvalidEmailChangeRequestException if no pending request exists
     *
     * @return string The user's old email address
     */
    public function confirmEmailChange(EmailChangeableInterface $user): string
    {
        $emailChangeRequest = $this->repository->findEmailChangeRequest($user);

        if (!$emailChangeRequest) {
            throw new InvalidEmailChangeRequestException('No pending email change found.');
        }

        $newEmail = $emailChangeRequest->getNewEmail();
        $oldEmail = $user->getEmail();

        // Update user email
        $user->setEmail($newEmail);

        // Remove the request
        $this->repository->removeEmailChangeRequest($emailChangeRequest);

        return $oldEmail;
    }

    /**
     * Cancel a pending email change.
     *
     * Note: You must call flush() to persist the changes!
     */
    public function cancelEmailChange(EmailChangeableInterface $user): void
    {
        $emailChangeRequest = $this->repository->findEmailChangeRequest($user);

        if ($emailChangeRequest) {
            $this->repository->removeEmailChangeRequest($emailChangeRequest);
        }
    }

    /**
     * Check if a user has a pending email change request.
     */
    public function hasPendingEmailChange(EmailChangeableInterface $user): bool
    {
        $request = $this->repository->findEmailChangeRequest($user);

        return $request !== null && !$request->isExpired();
    }

    /**
     * Get the pending email change request for a user.
     */
    public function getPendingEmail(EmailChangeableInterface $user): ?string
    {
        $request = $this->repository->findEmailChangeRequest($user);

        return $request && !$request->isExpired() ? $request->getNewEmail() : null;
    }

    /**
     * Check if a user has a pending email change request.
     */
    public function hasPendingRequest(EmailChangeableInterface $user): bool
    {
        $request = $this->repository->findEmailChangeRequest($user);

        return $request !== null && !$request->isExpired();
    }

    /**
     * Get the pending email change request for a user.
     */
    public function getPendingRequest(EmailChangeableInterface $user): ?EmailChangeRequest
    {
        $request = $this->repository->findEmailChangeRequest($user);

        return $request && !$request->isExpired() ? $request : null;
    }
}

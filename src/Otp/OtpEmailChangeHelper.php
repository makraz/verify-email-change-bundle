<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Otp;

use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Exception\ExpiredEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\InvalidEmailChangeRequestException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyEmailChangeRequestsException;
use Makraz\Bundle\VerifyEmailChange\Exception\TooManyVerificationAttemptsException;
use Makraz\Bundle\VerifyEmailChange\Generator\EmailChangeTokenGenerator;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;
use Makraz\Bundle\VerifyEmailChange\Persistence\EmailChangeRequestRepositoryInterface;

/**
 * OTP-based email change verification helper.
 *
 * Instead of signed URL links, this helper generates numeric codes
 * that users can enter manually. Ideal for mobile apps and API-first flows.
 */
class OtpEmailChangeHelper
{
    private const RETRY_TTL = 3600;

    public function __construct(
        private readonly EmailChangeRequestRepositoryInterface $repository,
        private readonly EmailChangeTokenGenerator $tokenGenerator,
        private readonly OtpGenerator $otpGenerator,
        private readonly int $requestLifetime = 3600,
        private readonly int $maxAttempts = 5,
    ) {
    }

    /**
     * Initiate an OTP-based email change.
     *
     * Returns the OTP that should be sent to the new email address.
     * The OTP is only returned once — it is stored as a hash internally.
     *
     * @throws TooManyEmailChangeRequestsException if a recent request exists
     */
    public function generateOtp(EmailChangeableInterface $user, string $newEmail): OtpResult
    {
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

        // Generate a random OTP
        $otp = $this->otpGenerator->generate();
        $hashedOtp = $this->otpGenerator->hash($otp);

        // Use token generator for the selector (unique identifier for the request)
        $tokenComponents = $this->tokenGenerator->createToken($expiresAt, $user->getId(), $newEmail);

        $emailChangeRequest = new EmailChangeRequest(
            $user,
            $expiresAt,
            $tokenComponents->getSelector(),
            $hashedOtp, // Store OTP hash instead of URL token hash
            $newEmail
        );

        $this->repository->persistEmailChangeRequest($emailChangeRequest);

        return new OtpResult($otp, $expiresAt);
    }

    /**
     * Verify the OTP and complete the email change.
     *
     * @throws InvalidEmailChangeRequestException if no pending request
     * @throws ExpiredEmailChangeRequestException if the request expired
     * @throws TooManyVerificationAttemptsException if max attempts exceeded
     *
     * @return string The user's old email address
     */
    public function verifyOtp(EmailChangeableInterface $user, string $otp): string
    {
        $emailChangeRequest = $this->repository->findEmailChangeRequest($user);

        if (!$emailChangeRequest) {
            throw new InvalidEmailChangeRequestException('No pending email change found.');
        }

        if ($emailChangeRequest->isExpired()) {
            throw new ExpiredEmailChangeRequestException();
        }

        if (!$this->otpGenerator->verify($otp, $emailChangeRequest->getHashedToken())) {
            $emailChangeRequest->incrementAttempts();

            if ($emailChangeRequest->getAttempts() >= $this->maxAttempts) {
                $this->repository->removeEmailChangeRequest($emailChangeRequest);
                throw new TooManyVerificationAttemptsException($this->maxAttempts);
            }

            $this->repository->persistEmailChangeRequest($emailChangeRequest);
            throw new InvalidEmailChangeRequestException('Invalid verification code.');
        }

        $oldEmail = $user->getEmail();
        $user->setEmail($emailChangeRequest->getNewEmail());
        $this->repository->removeEmailChangeRequest($emailChangeRequest);

        return $oldEmail;
    }

    /**
     * Cancel a pending OTP email change.
     */
    public function cancelEmailChange(EmailChangeableInterface $user): void
    {
        $emailChangeRequest = $this->repository->findEmailChangeRequest($user);

        if ($emailChangeRequest) {
            $this->repository->removeEmailChangeRequest($emailChangeRequest);
        }
    }

    /**
     * Check if a user has a pending email change.
     */
    public function hasPendingEmailChange(EmailChangeableInterface $user): bool
    {
        $request = $this->repository->findEmailChangeRequest($user);

        return $request !== null && !$request->isExpired();
    }

    /**
     * Get the pending new email for a user.
     */
    public function getPendingEmail(EmailChangeableInterface $user): ?string
    {
        $request = $this->repository->findEmailChangeRequest($user);

        return $request && !$request->isExpired() ? $request->getNewEmail() : null;
    }
}

<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Otp;

/**
 * Value object returned when generating an OTP.
 *
 * The plaintext OTP should be sent to the user and then discarded.
 * It is NOT stored — only its hash is persisted.
 */
class OtpResult
{
    public function __construct(
        private readonly string $otp,
        private readonly \DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * Get the plaintext OTP to send to the user.
     */
    public function getOtp(): string
    {
        return $this->otp;
    }

    /**
     * Get the expiration time.
     */
    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }
}

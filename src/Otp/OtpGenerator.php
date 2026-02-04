<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Otp;

/**
 * Generates and verifies one-time passwords (OTPs) for email verification.
 *
 * OTPs provide a numeric code alternative to signed URL links,
 * suitable for mobile apps and API-first applications.
 */
class OtpGenerator
{
    public function __construct(
        private readonly int $length = 6,
    ) {
    }

    /**
     * Generate a random numeric OTP.
     */
    public function generate(): string
    {
        $min = (int) str_pad('1', $this->length, '0');
        $max = (int) str_pad('', $this->length, '9');

        return (string) random_int($min, $max);
    }

    /**
     * Hash an OTP for secure storage.
     */
    public function hash(string $otp): string
    {
        return hash('sha256', $otp);
    }

    /**
     * Verify an OTP against a stored hash using timing-safe comparison.
     */
    public function verify(string $otp, string $hashedOtp): bool
    {
        return hash_equals($hashedOtp, $this->hash($otp));
    }

    /**
     * Get the configured OTP length.
     */
    public function getLength(): int
    {
        return $this->length;
    }
}

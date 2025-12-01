<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\EmailChange;

/**
 * Value object containing the signed URL and expiration information.
 *
 * This is returned by EmailChangeHelper::generateSignature() and should
 * be used to send the verification email to the user.
 */
class EmailChangeSignature
{
    public function __construct(
        private readonly string $signedUrl,
        private readonly \DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * Get the complete signed URL to include in the verification email.
     */
    public function getSignedUrl(): string
    {
        return $this->signedUrl;
    }

    /**
     * Get the expiration time of this verification link.
     */
    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * Get a translation key for the expiration message.
     *
     * Compatible with Symfony's translation component.
     */
    public function getExpirationMessageKey(): string
    {
        return 'This link will expire in %count% hour.|This link will expire in %count% hours.';
    }

    /**
     * Get the data for the expiration message translation.
     */
    public function getExpirationMessageData(): array
    {
        $hours = ceil(($this->expiresAt->getTimestamp() - time()) / 3600);

        return ['%count%' => max(1, $hours)];
    }
}

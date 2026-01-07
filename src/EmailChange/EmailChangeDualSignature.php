<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\EmailChange;

/**
 * Value object containing signed URLs for both new and old email verification.
 *
 * Returned by EmailChangeHelper::generateSignature() when dual verification mode is enabled.
 */
class EmailChangeDualSignature extends EmailChangeSignature
{
    public function __construct(
        string $signedUrl,
        private readonly string $oldEmailSignedUrl,
        \DateTimeImmutable $expiresAt,
    ) {
        parent::__construct($signedUrl, $expiresAt);
    }

    /**
     * Get the signed URL to send to the OLD email address for confirmation.
     */
    public function getOldEmailSignedUrl(): string
    {
        return $this->oldEmailSignedUrl;
    }
}

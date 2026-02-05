<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Event;

use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;

/**
 * Audit event dispatched for security-relevant email change operations.
 *
 * Listen to this event for logging, monitoring, and compliance purposes.
 */
class EmailChangeAuditEvent extends EmailChangeEvent
{
    public const ACTION_INITIATED = 'initiated';
    public const ACTION_VERIFIED = 'verified';
    public const ACTION_CONFIRMED = 'confirmed';
    public const ACTION_CANCELLED = 'cancelled';
    public const ACTION_FAILED_VERIFICATION = 'failed_verification';
    public const ACTION_MAX_ATTEMPTS_EXCEEDED = 'max_attempts_exceeded';
    public const ACTION_EXPIRED_ACCESS = 'expired_access';
    public const ACTION_OLD_EMAIL_CONFIRMED = 'old_email_confirmed';

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        EmailChangeableInterface $user,
        private readonly string $action,
        private readonly array $metadata = [],
    ) {
        parent::__construct($user);
    }

    /**
     * Get the audit action type.
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Get additional metadata about the action.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get a specific metadata value.
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get the IP address from metadata (if available).
     */
    public function getIpAddress(): ?string
    {
        return $this->metadata['ip_address'] ?? null;
    }

    /**
     * Get the user agent from metadata (if available).
     */
    public function getUserAgent(): ?string
    {
        return $this->metadata['user_agent'] ?? null;
    }
}

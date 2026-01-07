<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Persistence;

use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;

interface EmailChangeRequestRepositoryInterface
{
    /**
     * Save an email change request.
     */
    public function persistEmailChangeRequest(EmailChangeRequest $request): void;

    /**
     * Find an email change request by selector or user.
     *
     * @param EmailChangeableInterface|string $userOrSelector User object or selector string
     */
    public function findEmailChangeRequest(EmailChangeableInterface|string $userOrSelector): ?EmailChangeRequest;

    /**
     * Get the user entity from an email change request.
     */
    public function getUserFromRequest(EmailChangeRequest $request): ?EmailChangeableInterface;

    /**
     * Remove an email change request.
     */
    public function removeEmailChangeRequest(EmailChangeRequest $request): void;

    /**
     * Remove all expired email change requests.
     *
     * @return int Number of removed requests
     */
    public function removeExpiredEmailChangeRequests(): int;

    /**
     * Count all expired email change requests.
     *
     * @return int Number of expired requests
     */
    public function countExpiredEmailChangeRequests(): int;

    /**
     * Remove expired email change requests older than the given cutoff.
     *
     * @return int Number of removed requests
     */
    public function removeExpiredOlderThan(\DateTimeImmutable $cutoff): int;

    /**
     * Find an email change request by old email selector (for dual verification mode).
     */
    public function findByOldEmailSelector(string $selector): ?EmailChangeRequest;

    /**
     * Count expired email change requests older than the given cutoff.
     *
     * @return int Number of expired requests
     */
    public function countExpiredOlderThan(\DateTimeImmutable $cutoff): int;
}

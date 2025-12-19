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
}

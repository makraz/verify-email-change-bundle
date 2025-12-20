<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Fixtures;

use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;
use Makraz\Bundle\VerifyEmailChange\Persistence\EmailChangeRequestRepositoryInterface;

/**
 * In-memory repository implementation for testing.
 */
class EmailChangeRequestTestRepository implements EmailChangeRequestRepositoryInterface
{
    /**
     * @var array<string, EmailChangeRequest>
     */
    private array $requestsBySelector = [];

    /**
     * @var array<string, EmailChangeRequest>
     */
    private array $requestsByUser = [];

    /**
     * @var array<string, EmailChangeableInterface>
     */
    private array $users = [];

    public function persistEmailChangeRequest(EmailChangeRequest $request): void
    {
        $this->requestsBySelector[$request->getSelector()] = $request;
        $this->requestsByUser[$request->getUserIdentifier()] = $request;
    }

    public function findEmailChangeRequest(EmailChangeableInterface|string $selectorOrUser): ?EmailChangeRequest
    {
        if ($selectorOrUser instanceof EmailChangeableInterface) {
            $identifier = $this->createUserIdentifier($selectorOrUser);

            return $this->requestsByUser[$identifier] ?? null;
        }

        return $this->requestsBySelector[$selectorOrUser] ?? null;
    }

    public function getUserFromRequest(EmailChangeRequest $request): ?EmailChangeableInterface
    {
        return $this->users[$request->getUserIdentifier()] ?? null;
    }

    /**
     * Register a user for testing purposes.
     */
    public function registerUser(EmailChangeableInterface $user): void
    {
        $identifier = $this->createUserIdentifier($user);
        $this->users[$identifier] = $user;
    }

    public function removeEmailChangeRequest(EmailChangeRequest $request): void
    {
        unset($this->requestsBySelector[$request->getSelector()]);
        unset($this->requestsByUser[$request->getUserIdentifier()]);
    }

    public function removeExpiredEmailChangeRequests(): int
    {
        $removed = 0;
        $now = new \DateTimeImmutable();

        foreach ($this->requestsBySelector as $selector => $request) {
            if ($request->getExpiresAt() <= $now) {
                $this->removeEmailChangeRequest($request);
                ++$removed;
            }
        }

        return $removed;
    }

    public function countExpiredEmailChangeRequests(): int
    {
        $count = 0;
        $now = new \DateTimeImmutable();

        foreach ($this->requestsBySelector as $request) {
            if ($request->getExpiresAt() <= $now) {
                ++$count;
            }
        }

        return $count;
    }

    public function removeExpiredOlderThan(\DateTimeImmutable $cutoff): int
    {
        $removed = 0;

        foreach ($this->requestsBySelector as $selector => $request) {
            if ($request->getExpiresAt() < $cutoff) {
                $this->removeEmailChangeRequest($request);
                ++$removed;
            }
        }

        return $removed;
    }

    public function countExpiredOlderThan(\DateTimeImmutable $cutoff): int
    {
        $count = 0;

        foreach ($this->requestsBySelector as $request) {
            if ($request->getExpiresAt() < $cutoff) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Get all requests (for testing purposes).
     *
     * @return array<EmailChangeRequest>
     */
    public function getAllRequests(): array
    {
        return array_values($this->requestsBySelector);
    }

    /**
     * Clear all requests (for testing purposes).
     */
    public function clear(): void
    {
        $this->requestsBySelector = [];
        $this->requestsByUser = [];
        $this->users = [];
    }

    private function createUserIdentifier(EmailChangeableInterface $user): string
    {
        return get_class($user).'::'.$user->getId();
    }
}

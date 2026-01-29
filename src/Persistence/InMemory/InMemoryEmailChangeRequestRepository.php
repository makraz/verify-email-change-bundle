<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Persistence\InMemory;

use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;
use Makraz\Bundle\VerifyEmailChange\Persistence\EmailChangeRequestRepositoryInterface;

/**
 * In-memory repository implementation.
 *
 * Useful for testing and stateless/API-first applications.
 * Data only persists for the lifetime of the PHP process.
 *
 * Note: getUserFromRequest() requires a user provider callback.
 */
class InMemoryEmailChangeRequestRepository implements EmailChangeRequestRepositoryInterface
{
    /** @var array<string, EmailChangeRequest> Indexed by selector */
    private array $bySelector = [];

    /** @var array<string, EmailChangeRequest> Indexed by user identifier */
    private array $byUser = [];

    /** @var callable(EmailChangeRequest): ?EmailChangeableInterface */
    private $userProvider;

    /**
     * @param callable(EmailChangeRequest): ?EmailChangeableInterface $userProvider
     */
    public function __construct(callable $userProvider)
    {
        $this->userProvider = $userProvider;
    }

    public function persistEmailChangeRequest(EmailChangeRequest $request): void
    {
        $this->bySelector[$request->getSelector()] = $request;
        $this->byUser[$request->getUserIdentifier()] = $request;
    }

    public function findEmailChangeRequest(EmailChangeableInterface|string $userOrSelector): ?EmailChangeRequest
    {
        if (is_string($userOrSelector)) {
            return $this->bySelector[$userOrSelector] ?? null;
        }

        $identifier = get_class($userOrSelector).'::'.$userOrSelector->getId();

        return $this->byUser[$identifier] ?? null;
    }

    public function getUserFromRequest(EmailChangeRequest $request): ?EmailChangeableInterface
    {
        return ($this->userProvider)($request);
    }

    public function removeEmailChangeRequest(EmailChangeRequest $request): void
    {
        unset($this->bySelector[$request->getSelector()]);
        unset($this->byUser[$request->getUserIdentifier()]);
    }

    public function findByOldEmailSelector(string $selector): ?EmailChangeRequest
    {
        foreach ($this->bySelector as $request) {
            if ($request->getOldEmailSelector() === $selector) {
                return $request;
            }
        }

        return null;
    }

    public function removeExpiredEmailChangeRequests(): int
    {
        return $this->removeExpiredBefore(new \DateTimeImmutable());
    }

    public function countExpiredEmailChangeRequests(): int
    {
        return $this->countExpiredBefore(new \DateTimeImmutable());
    }

    public function removeExpiredOlderThan(\DateTimeImmutable $cutoff): int
    {
        return $this->removeExpiredBefore($cutoff);
    }

    public function countExpiredOlderThan(\DateTimeImmutable $cutoff): int
    {
        return $this->countExpiredBefore($cutoff);
    }

    /**
     * Get all stored requests (for inspection in tests).
     *
     * @return array<EmailChangeRequest>
     */
    public function getAll(): array
    {
        return array_values($this->bySelector);
    }

    /**
     * Clear all stored requests.
     */
    public function clear(): void
    {
        $this->bySelector = [];
        $this->byUser = [];
    }

    private function removeExpiredBefore(\DateTimeImmutable $cutoff): int
    {
        $removed = 0;

        foreach ($this->bySelector as $request) {
            if ($request->getExpiresAt() < $cutoff) {
                $this->removeEmailChangeRequest($request);
                ++$removed;
            }
        }

        return $removed;
    }

    private function countExpiredBefore(\DateTimeImmutable $cutoff): int
    {
        $count = 0;

        foreach ($this->bySelector as $request) {
            if ($request->getExpiresAt() < $cutoff) {
                ++$count;
            }
        }

        return $count;
    }
}

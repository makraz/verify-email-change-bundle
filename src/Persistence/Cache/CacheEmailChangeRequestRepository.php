<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Persistence\Cache;

use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;
use Makraz\Bundle\VerifyEmailChange\Persistence\EmailChangeRequestRepositoryInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * PSR-6 Cache-based repository implementation.
 *
 * Stores email change requests in a cache pool (e.g., Redis, Memcached, filesystem).
 * Useful for applications that don't use Doctrine ORM or want faster lookups.
 *
 * Note: This adapter does NOT store user entities — getUserFromRequest()
 * requires a user provider callback.
 */
class CacheEmailChangeRequestRepository implements EmailChangeRequestRepositoryInterface
{
    private const SELECTOR_PREFIX = 'email_change_selector_';
    private const USER_PREFIX = 'email_change_user_';
    private const OLD_SELECTOR_PREFIX = 'email_change_old_selector_';
    private const INDEX_KEY = 'email_change_index';

    /** @var callable(EmailChangeRequest): ?EmailChangeableInterface */
    private $userProvider;

    /**
     * @param callable(EmailChangeRequest): ?EmailChangeableInterface $userProvider
     */
    public function __construct(
        private readonly CacheItemPoolInterface $cachePool,
        callable $userProvider,
    ) {
        $this->userProvider = $userProvider;
    }

    public function persistEmailChangeRequest(EmailChangeRequest $request): void
    {
        // Add a buffer to TTL so expired requests remain in cache for cleanup.
        // Expiration is checked at the application level by the entity.
        $ttl = max(3600, $request->getExpiresAt()->getTimestamp() - time() + 3600);

        // Store by selector
        $selectorItem = $this->cachePool->getItem(self::SELECTOR_PREFIX.$request->getSelector());
        $selectorItem->set($request);
        $selectorItem->expiresAfter($ttl);
        $this->cachePool->save($selectorItem);

        // Store by user identifier
        $userItem = $this->cachePool->getItem(self::USER_PREFIX.$this->sanitizeKey($request->getUserIdentifier()));
        $userItem->set($request);
        $userItem->expiresAfter($ttl);
        $this->cachePool->save($userItem);

        // Store by old email selector if present
        if ($request->getOldEmailSelector()) {
            $oldSelectorItem = $this->cachePool->getItem(self::OLD_SELECTOR_PREFIX.$request->getOldEmailSelector());
            $oldSelectorItem->set($request);
            $oldSelectorItem->expiresAfter($ttl);
            $this->cachePool->save($oldSelectorItem);
        }

        // Track selectors in index for purge operations
        $this->addToIndex($request->getSelector());
    }

    public function findEmailChangeRequest(EmailChangeableInterface|string $userOrSelector): ?EmailChangeRequest
    {
        if (is_string($userOrSelector)) {
            $item = $this->cachePool->getItem(self::SELECTOR_PREFIX.$userOrSelector);

            return $item->isHit() ? $item->get() : null;
        }

        $userIdentifier = get_class($userOrSelector).'::'.$userOrSelector->getId();
        $item = $this->cachePool->getItem(self::USER_PREFIX.$this->sanitizeKey($userIdentifier));

        return $item->isHit() ? $item->get() : null;
    }

    public function getUserFromRequest(EmailChangeRequest $request): ?EmailChangeableInterface
    {
        return ($this->userProvider)($request);
    }

    public function removeEmailChangeRequest(EmailChangeRequest $request): void
    {
        $this->cachePool->deleteItem(self::SELECTOR_PREFIX.$request->getSelector());
        $this->cachePool->deleteItem(self::USER_PREFIX.$this->sanitizeKey($request->getUserIdentifier()));

        if ($request->getOldEmailSelector()) {
            $this->cachePool->deleteItem(self::OLD_SELECTOR_PREFIX.$request->getOldEmailSelector());
        }

        $this->removeFromIndex($request->getSelector());
    }

    public function findByOldEmailSelector(string $selector): ?EmailChangeRequest
    {
        $item = $this->cachePool->getItem(self::OLD_SELECTOR_PREFIX.$selector);

        return $item->isHit() ? $item->get() : null;
    }

    public function removeExpiredEmailChangeRequests(): int
    {
        return $this->removeExpiredFromIndex(new \DateTimeImmutable());
    }

    public function countExpiredEmailChangeRequests(): int
    {
        return $this->countExpiredFromIndex(new \DateTimeImmutable());
    }

    public function removeExpiredOlderThan(\DateTimeImmutable $cutoff): int
    {
        return $this->removeExpiredFromIndex($cutoff);
    }

    public function countExpiredOlderThan(\DateTimeImmutable $cutoff): int
    {
        return $this->countExpiredFromIndex($cutoff);
    }

    /**
     * @return array<string>
     */
    private function getIndex(): array
    {
        $item = $this->cachePool->getItem(self::INDEX_KEY);

        return $item->isHit() ? $item->get() : [];
    }

    private function saveIndex(array $index): void
    {
        $item = $this->cachePool->getItem(self::INDEX_KEY);
        $item->set($index);
        $this->cachePool->save($item);
    }

    private function addToIndex(string $selector): void
    {
        $index = $this->getIndex();
        if (!in_array($selector, $index, true)) {
            $index[] = $selector;
            $this->saveIndex($index);
        }
    }

    private function removeFromIndex(string $selector): void
    {
        $index = $this->getIndex();
        $index = array_values(array_filter($index, fn (string $s) => $s !== $selector));
        $this->saveIndex($index);
    }

    private function removeExpiredFromIndex(\DateTimeImmutable $cutoff): int
    {
        $removed = 0;

        foreach ($this->getIndex() as $selector) {
            $item = $this->cachePool->getItem(self::SELECTOR_PREFIX.$selector);

            if (!$item->isHit()) {
                // Item already expired from cache
                $this->removeFromIndex($selector);
                ++$removed;
                continue;
            }

            $request = $item->get();
            if ($request instanceof EmailChangeRequest && $request->getExpiresAt() < $cutoff) {
                $this->removeEmailChangeRequest($request);
                ++$removed;
            }
        }

        return $removed;
    }

    private function countExpiredFromIndex(\DateTimeImmutable $cutoff): int
    {
        $count = 0;

        foreach ($this->getIndex() as $selector) {
            $item = $this->cachePool->getItem(self::SELECTOR_PREFIX.$selector);

            if (!$item->isHit()) {
                ++$count;
                continue;
            }

            $request = $item->get();
            if ($request instanceof EmailChangeRequest && $request->getExpiresAt() < $cutoff) {
                ++$count;
            }
        }

        return $count;
    }

    private function sanitizeKey(string $key): string
    {
        return str_replace(['\\', ':'], ['_', '_'], $key);
    }
}

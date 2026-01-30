<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\Persistence;

use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Persistence\InMemory\InMemoryEmailChangeRequestRepository;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;

class InMemoryEmailChangeRequestRepositoryTest extends TestCase
{
    private InMemoryEmailChangeRequestRepository $repository;
    /** @var array<string, TestUser> */
    private array $users = [];

    protected function setUp(): void
    {
        $this->users = [];

        $this->repository = new InMemoryEmailChangeRequestRepository(
            function (EmailChangeRequest $request) {
                return $this->users[$request->getUserIdentifier()] ?? null;
            },
        );
    }

    private function registerUser(TestUser $user): void
    {
        $identifier = get_class($user).'::'.$user->getId();
        $this->users[$identifier] = $user;
    }

    public function testPersistAndFindBySelector(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $request = new EmailChangeRequest($user, new \DateTimeImmutable('+1 hour'), 'sel123', 'hash', 'new@example.com');

        $this->repository->persistEmailChangeRequest($request);

        $found = $this->repository->findEmailChangeRequest('sel123');
        $this->assertNotNull($found);
        $this->assertSame($request, $found);
    }

    public function testPersistAndFindByUser(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $request = new EmailChangeRequest($user, new \DateTimeImmutable('+1 hour'), 'sel123', 'hash', 'new@example.com');

        $this->repository->persistEmailChangeRequest($request);

        $found = $this->repository->findEmailChangeRequest($user);
        $this->assertNotNull($found);
        $this->assertSame($request, $found);
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findEmailChangeRequest('nonexistent'));

        $user = new TestUser(999, 'unknown@example.com');
        $this->assertNull($this->repository->findEmailChangeRequest($user));
    }

    public function testRemoveEmailChangeRequest(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $request = new EmailChangeRequest($user, new \DateTimeImmutable('+1 hour'), 'sel123', 'hash', 'new@example.com');

        $this->repository->persistEmailChangeRequest($request);
        $this->repository->removeEmailChangeRequest($request);

        $this->assertNull($this->repository->findEmailChangeRequest('sel123'));
        $this->assertNull($this->repository->findEmailChangeRequest($user));
    }

    public function testGetUserFromRequest(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $this->registerUser($user);

        $request = new EmailChangeRequest($user, new \DateTimeImmutable('+1 hour'), 'sel123', 'hash', 'new@example.com');

        $this->assertSame($user, $this->repository->getUserFromRequest($request));
    }

    public function testGetUserFromRequestReturnsNullForUnknownUser(): void
    {
        $user = new TestUser(999, 'unknown@example.com');
        $request = new EmailChangeRequest($user, new \DateTimeImmutable('+1 hour'), 'sel123', 'hash', 'new@example.com');

        $this->assertNull($this->repository->getUserFromRequest($request));
    }

    public function testFindByOldEmailSelector(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $request = new EmailChangeRequest($user, new \DateTimeImmutable('+1 hour'), 'sel123', 'hash', 'new@example.com');
        $request->setOldEmailSelector('old_sel');

        $this->repository->persistEmailChangeRequest($request);

        $found = $this->repository->findByOldEmailSelector('old_sel');
        $this->assertNotNull($found);
        $this->assertSame($request, $found);
    }

    public function testFindByOldEmailSelectorReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findByOldEmailSelector('nonexistent'));
    }

    public function testRemoveExpiredEmailChangeRequests(): void
    {
        $user1 = new TestUser(1, 'user1@example.com');
        $user2 = new TestUser(2, 'user2@example.com');

        $expired = new EmailChangeRequest($user1, new \DateTimeImmutable('-1 hour'), 'exp1', 'hash1', 'new1@example.com');
        $active = new EmailChangeRequest($user2, new \DateTimeImmutable('+1 hour'), 'act1', 'hash2', 'new2@example.com');

        $this->repository->persistEmailChangeRequest($expired);
        $this->repository->persistEmailChangeRequest($active);

        $removed = $this->repository->removeExpiredEmailChangeRequests();
        $this->assertSame(1, $removed);
        $this->assertCount(1, $this->repository->getAll());
    }

    public function testCountExpiredEmailChangeRequests(): void
    {
        $user1 = new TestUser(1, 'user1@example.com');
        $user2 = new TestUser(2, 'user2@example.com');

        $expired = new EmailChangeRequest($user1, new \DateTimeImmutable('-1 hour'), 'exp1', 'hash1', 'new1@example.com');
        $active = new EmailChangeRequest($user2, new \DateTimeImmutable('+1 hour'), 'act1', 'hash2', 'new2@example.com');

        $this->repository->persistEmailChangeRequest($expired);
        $this->repository->persistEmailChangeRequest($active);

        $this->assertSame(1, $this->repository->countExpiredEmailChangeRequests());
    }

    public function testRemoveExpiredOlderThan(): void
    {
        $user1 = new TestUser(1, 'user1@example.com');
        $user2 = new TestUser(2, 'user2@example.com');

        $veryOld = new EmailChangeRequest($user1, new \DateTimeImmutable('-2 days'), 'old1', 'hash1', 'new1@example.com');
        $recentExpired = new EmailChangeRequest($user2, new \DateTimeImmutable('-30 minutes'), 'recent1', 'hash2', 'new2@example.com');

        $this->repository->persistEmailChangeRequest($veryOld);
        $this->repository->persistEmailChangeRequest($recentExpired);

        $cutoff = new \DateTimeImmutable('-1 hour');
        $removed = $this->repository->removeExpiredOlderThan($cutoff);

        $this->assertSame(1, $removed);
        $this->assertNull($this->repository->findEmailChangeRequest('old1'));
        $this->assertNotNull($this->repository->findEmailChangeRequest('recent1'));
    }

    public function testCountExpiredOlderThan(): void
    {
        $user1 = new TestUser(1, 'user1@example.com');
        $user2 = new TestUser(2, 'user2@example.com');

        $veryOld = new EmailChangeRequest($user1, new \DateTimeImmutable('-2 days'), 'old1', 'hash1', 'new1@example.com');
        $recentExpired = new EmailChangeRequest($user2, new \DateTimeImmutable('-30 minutes'), 'recent1', 'hash2', 'new2@example.com');

        $this->repository->persistEmailChangeRequest($veryOld);
        $this->repository->persistEmailChangeRequest($recentExpired);

        $cutoff = new \DateTimeImmutable('-1 hour');
        $this->assertSame(1, $this->repository->countExpiredOlderThan($cutoff));
    }

    public function testClear(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $request = new EmailChangeRequest($user, new \DateTimeImmutable('+1 hour'), 'sel123', 'hash', 'new@example.com');

        $this->repository->persistEmailChangeRequest($request);
        $this->assertCount(1, $this->repository->getAll());

        $this->repository->clear();
        $this->assertCount(0, $this->repository->getAll());
    }

    public function testGetAll(): void
    {
        $user1 = new TestUser(1, 'user1@example.com');
        $user2 = new TestUser(2, 'user2@example.com');

        $request1 = new EmailChangeRequest($user1, new \DateTimeImmutable('+1 hour'), 'sel1', 'hash1', 'new1@example.com');
        $request2 = new EmailChangeRequest($user2, new \DateTimeImmutable('+1 hour'), 'sel2', 'hash2', 'new2@example.com');

        $this->repository->persistEmailChangeRequest($request1);
        $this->repository->persistEmailChangeRequest($request2);

        $all = $this->repository->getAll();
        $this->assertCount(2, $all);
    }

    public function testImplementsRepositoryInterface(): void
    {
        $this->assertInstanceOf(
            \Makraz\Bundle\VerifyEmailChange\Persistence\EmailChangeRequestRepositoryInterface::class,
            $this->repository
        );
    }
}

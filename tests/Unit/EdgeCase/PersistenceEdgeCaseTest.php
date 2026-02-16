<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\EdgeCase;

use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Persistence\InMemory\InMemoryEmailChangeRequestRepository;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;

class PersistenceEdgeCaseTest extends TestCase
{
    private InMemoryEmailChangeRequestRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryEmailChangeRequestRepository(
            fn () => null
        );
    }

    // --- Empty State Operations ---

    public function testGetAllOnEmptyRepoReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->repository->getAll());
    }

    public function testClearOnEmptyRepoDoesNotThrow(): void
    {
        $this->repository->clear();
        $this->assertSame([], $this->repository->getAll());
    }

    public function testRemoveExpiredOnEmptyRepoReturnsZero(): void
    {
        $this->assertSame(0, $this->repository->removeExpiredEmailChangeRequests());
    }

    public function testCountExpiredOnEmptyRepoReturnsZero(): void
    {
        $this->assertSame(0, $this->repository->countExpiredEmailChangeRequests());
    }

    public function testFindBySelectorReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->repository->findEmailChangeRequest('nonexistent'));
    }

    public function testFindByUserReturnsNullWhenEmpty(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $this->assertNull($this->repository->findEmailChangeRequest($user));
    }

    public function testFindByOldEmailSelectorReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->repository->findByOldEmailSelector('nonexistent'));
    }

    // --- Persist and Retrieve ---

    public function testPersistAndFindBySelector(): void
    {
        $request = $this->createRequest('selector_abc');
        $this->repository->persistEmailChangeRequest($request);

        $found = $this->repository->findEmailChangeRequest('selector_abc');
        $this->assertSame($request, $found);
    }

    public function testPersistAndFindByUser(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $request = $this->createRequest('selector', $user);
        $this->repository->persistEmailChangeRequest($request);

        $found = $this->repository->findEmailChangeRequest($user);
        $this->assertSame($request, $found);
    }

    public function testPersistOverwritesSameSelector(): void
    {
        $user = new TestUser(1, 'old@example.com');
        $request1 = $this->createRequest('same_selector', $user, 'first@example.com');
        $request2 = $this->createRequest('same_selector', $user, 'second@example.com');

        $this->repository->persistEmailChangeRequest($request1);
        $this->repository->persistEmailChangeRequest($request2);

        $found = $this->repository->findEmailChangeRequest('same_selector');
        $this->assertSame('second@example.com', $found->getNewEmail());
    }

    public function testRemoveNonExistentRequestDoesNotThrow(): void
    {
        $request = $this->createRequest('nonexistent');

        // Should not throw
        $this->repository->removeEmailChangeRequest($request);
        $this->assertSame([], $this->repository->getAll());
    }

    // --- Old Email Selector ---

    public function testFindByOldEmailSelectorFindsCorrectRequest(): void
    {
        $request = $this->createRequest('main_selector');
        $request->setOldEmailSelector('old_selector_123');
        $this->repository->persistEmailChangeRequest($request);

        $found = $this->repository->findByOldEmailSelector('old_selector_123');
        $this->assertSame($request, $found);
    }

    public function testFindByOldEmailSelectorReturnsNullWhenNoMatch(): void
    {
        $request = $this->createRequest('main_selector');
        $request->setOldEmailSelector('old_selector_123');
        $this->repository->persistEmailChangeRequest($request);

        $this->assertNull($this->repository->findByOldEmailSelector('wrong_selector'));
    }

    public function testFindByOldEmailSelectorWhenNoRequestsHaveOldSelector(): void
    {
        $request = $this->createRequest('main_selector');
        // No old email selector set (null by default)
        $this->repository->persistEmailChangeRequest($request);

        $this->assertNull($this->repository->findByOldEmailSelector('any_selector'));
    }

    // --- Expiration ---

    public function testRemoveExpiredOnlyRemovesExpired(): void
    {
        $user1 = new TestUser(1, 'user1@example.com');
        $user2 = new TestUser(2, 'user2@example.com');

        $expired = $this->createRequest('expired_sel', $user1, 'new@example.com', '-1 hour');
        $valid = $this->createRequest('valid_sel', $user2, 'new2@example.com', '+1 hour');

        $this->repository->persistEmailChangeRequest($expired);
        $this->repository->persistEmailChangeRequest($valid);

        $removed = $this->repository->removeExpiredEmailChangeRequests();

        $this->assertSame(1, $removed);
        $this->assertNull($this->repository->findEmailChangeRequest('expired_sel'));
        $this->assertNotNull($this->repository->findEmailChangeRequest('valid_sel'));
    }

    public function testCountExpiredMatchesActualExpiredCount(): void
    {
        $user1 = new TestUser(1, 'user1@example.com');
        $user2 = new TestUser(2, 'user2@example.com');
        $user3 = new TestUser(3, 'user3@example.com');

        $this->repository->persistEmailChangeRequest(
            $this->createRequest('sel1', $user1, 'new1@example.com', '-2 hours')
        );
        $this->repository->persistEmailChangeRequest(
            $this->createRequest('sel2', $user2, 'new2@example.com', '-1 hour')
        );
        $this->repository->persistEmailChangeRequest(
            $this->createRequest('sel3', $user3, 'new3@example.com', '+1 hour')
        );

        $this->assertSame(2, $this->repository->countExpiredEmailChangeRequests());
    }

    public function testRemoveExpiredOlderThanCutoff(): void
    {
        $user1 = new TestUser(1, 'user1@example.com');
        $user2 = new TestUser(2, 'user2@example.com');

        // Expired 3 hours ago
        $this->repository->persistEmailChangeRequest(
            $this->createRequest('old_expired', $user1, 'new1@example.com', '-3 hours')
        );
        // Expired 30 minutes ago (more recent)
        $this->repository->persistEmailChangeRequest(
            $this->createRequest('recent_expired', $user2, 'new2@example.com', '-30 minutes')
        );

        // Only remove things expired before 1 hour ago
        $cutoff = new \DateTimeImmutable('-1 hour');
        $removed = $this->repository->removeExpiredOlderThan($cutoff);

        $this->assertSame(1, $removed);
        $this->assertNull($this->repository->findEmailChangeRequest('old_expired'));
        $this->assertNotNull($this->repository->findEmailChangeRequest('recent_expired'));
    }

    public function testCountExpiredOlderThanCutoff(): void
    {
        $user1 = new TestUser(1, 'user1@example.com');
        $user2 = new TestUser(2, 'user2@example.com');
        $user3 = new TestUser(3, 'user3@example.com');

        $this->repository->persistEmailChangeRequest(
            $this->createRequest('sel1', $user1, 'n@e.com', '-5 hours')
        );
        $this->repository->persistEmailChangeRequest(
            $this->createRequest('sel2', $user2, 'n@e.com', '-3 hours')
        );
        $this->repository->persistEmailChangeRequest(
            $this->createRequest('sel3', $user3, 'n@e.com', '-30 minutes')
        );

        $cutoff = new \DateTimeImmutable('-2 hours');
        $this->assertSame(2, $this->repository->countExpiredOlderThan($cutoff));
    }

    // --- User Provider ---

    public function testGetUserFromRequestUsesProvider(): void
    {
        $expectedUser = new TestUser(42, 'found@example.com');
        $repository = new InMemoryEmailChangeRequestRepository(
            fn () => $expectedUser
        );

        $request = $this->createRequest();
        $repository->persistEmailChangeRequest($request);

        $user = $repository->getUserFromRequest($request);
        $this->assertSame($expectedUser, $user);
    }

    public function testGetUserFromRequestReturnsNullWhenProviderReturnsNull(): void
    {
        $request = $this->createRequest();
        $this->repository->persistEmailChangeRequest($request);

        $user = $this->repository->getUserFromRequest($request);
        $this->assertNull($user);
    }

    // --- Multiple Requests ---

    public function testMultipleRequestsDifferentUsers(): void
    {
        $user1 = new TestUser(1, 'u1@example.com');
        $user2 = new TestUser(2, 'u2@example.com');
        $user3 = new TestUser(3, 'u3@example.com');

        $this->repository->persistEmailChangeRequest($this->createRequest('sel1', $user1));
        $this->repository->persistEmailChangeRequest($this->createRequest('sel2', $user2));
        $this->repository->persistEmailChangeRequest($this->createRequest('sel3', $user3));

        $this->assertCount(3, $this->repository->getAll());

        $this->repository->removeEmailChangeRequest(
            $this->repository->findEmailChangeRequest('sel2')
        );

        $this->assertCount(2, $this->repository->getAll());
        $this->assertNull($this->repository->findEmailChangeRequest($user2));
    }

    public function testClearRemovesEverything(): void
    {
        $user1 = new TestUser(1, 'u1@example.com');
        $user2 = new TestUser(2, 'u2@example.com');

        $this->repository->persistEmailChangeRequest($this->createRequest('sel1', $user1));
        $this->repository->persistEmailChangeRequest($this->createRequest('sel2', $user2));

        $this->assertCount(2, $this->repository->getAll());

        $this->repository->clear();

        $this->assertSame([], $this->repository->getAll());
        $this->assertNull($this->repository->findEmailChangeRequest($user1));
        $this->assertNull($this->repository->findEmailChangeRequest('sel1'));
    }

    private function createRequest(
        string $selector = 'selector123',
        ?TestUser $user = null,
        string $newEmail = 'new@example.com',
        string $expiresAt = '+1 hour',
    ): EmailChangeRequest {
        return new EmailChangeRequest(
            $user ?? new TestUser(1, 'old@example.com'),
            new \DateTimeImmutable($expiresAt),
            $selector,
            'hashedtoken',
            $newEmail,
        );
    }
}

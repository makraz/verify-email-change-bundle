<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Command;

use Makraz\Bundle\VerifyEmailChange\Command\PurgeExpiredEmailChangeRequestsCommand;
use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\EmailChangeRequestTestRepository;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class PurgeExpiredEmailChangeRequestsCommandTest extends TestCase
{
    private EmailChangeRequestTestRepository $repository;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->repository = new EmailChangeRequestTestRepository();

        $command = new PurgeExpiredEmailChangeRequestsCommand($this->repository);
        $application = new Application();
        $application->addCommands([$command]);

        $this->commandTester = new CommandTester($application->find('verify:email-change:purge-expired'));
    }

    public function testDryRunOutputsCountWithoutDeleting(): void
    {
        $user = new TestUser(1, 'user@example.com');
        $expiredRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('-1 hour'),
            'selector1',
            'hashed1',
            'new@example.com'
        );
        $this->repository->persistEmailChangeRequest($expiredRequest);

        $this->commandTester->execute(['--dry-run' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Would purge 1 expired email change request(s).', $output);

        // Verify the record still exists (dry-run should not delete)
        $this->assertCount(1, $this->repository->getAllRequests());
    }

    public function testActualPurgeDeletesExpiredRecords(): void
    {
        $user = new TestUser(1, 'user@example.com');
        $expiredRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('-1 hour'),
            'selector1',
            'hashed1',
            'new@example.com'
        );
        $this->repository->persistEmailChangeRequest($expiredRequest);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Purged 1 expired email change request(s).', $output);
        $this->assertCount(0, $this->repository->getAllRequests());
    }

    public function testNonExpiredRecordsAreNotDeleted(): void
    {
        $user = new TestUser(1, 'user@example.com');

        // Expired request
        $expiredRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('-1 hour'),
            'selector_expired',
            'hashed1',
            'expired@example.com'
        );
        $this->repository->persistEmailChangeRequest($expiredRequest);

        $user2 = new TestUser(2, 'user2@example.com');

        // Non-expired request
        $validRequest = new EmailChangeRequest(
            $user2,
            new \DateTimeImmutable('+1 hour'),
            'selector_valid',
            'hashed2',
            'valid@example.com'
        );
        $this->repository->persistEmailChangeRequest($validRequest);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Purged 1 expired email change request(s).', $output);

        $remaining = $this->repository->getAllRequests();
        $this->assertCount(1, $remaining);
        $this->assertSame('selector_valid', $remaining[0]->getSelector());
    }

    public function testOlderThanOption(): void
    {
        $user1 = new TestUser(1, 'user1@example.com');

        // Expired 2 hours ago
        $oldExpired = new EmailChangeRequest(
            $user1,
            new \DateTimeImmutable('-2 hours'),
            'selector_old',
            'hashed1',
            'old@example.com'
        );
        $this->repository->persistEmailChangeRequest($oldExpired);

        $user2 = new TestUser(2, 'user2@example.com');

        // Expired 30 minutes ago
        $recentExpired = new EmailChangeRequest(
            $user2,
            new \DateTimeImmutable('-30 minutes'),
            'selector_recent',
            'hashed2',
            'recent@example.com'
        );
        $this->repository->persistEmailChangeRequest($recentExpired);

        // Only purge requests expired more than 1 hour ago
        $this->commandTester->execute(['--older-than' => '3600']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Purged 1 expired email change request(s).', $output);

        $remaining = $this->repository->getAllRequests();
        $this->assertCount(1, $remaining);
        $this->assertSame('selector_recent', $remaining[0]->getSelector());
    }

    public function testEmptyTableOutputsZeroPurged(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Purged 0 expired email change request(s).', $output);
    }

    public function testDryRunWithOlderThanOption(): void
    {
        $user = new TestUser(1, 'user@example.com');
        $expiredRequest = new EmailChangeRequest(
            $user,
            new \DateTimeImmutable('-2 hours'),
            'selector1',
            'hashed1',
            'new@example.com'
        );
        $this->repository->persistEmailChangeRequest($expiredRequest);

        $this->commandTester->execute(['--dry-run' => true, '--older-than' => '3600']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Would purge 1 expired email change request(s).', $output);

        // Verify record still exists
        $this->assertCount(1, $this->repository->getAllRequests());
    }

    public function testCommandReturnsSuccessExitCode(): void
    {
        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testPurgeMultipleExpiredRecords(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $user = new TestUser($i, "user{$i}@example.com");
            $request = new EmailChangeRequest(
                $user,
                new \DateTimeImmutable('-1 hour'),
                "selector_{$i}",
                "hashed_{$i}",
                "new{$i}@example.com"
            );
            $this->repository->persistEmailChangeRequest($request);
        }

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Purged 5 expired email change request(s).', $output);
        $this->assertCount(0, $this->repository->getAllRequests());
    }
}

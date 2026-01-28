<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;
use Makraz\Bundle\VerifyEmailChange\Persistence\Doctrine\DoctrineEmailChangeRequestRepository;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DoctrineEmailChangeRequestRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private DoctrineEmailChangeRequestRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = new DoctrineEmailChangeRequestRepository($this->entityManager);
    }

    public function testPersistEmailChangeRequest(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $request = new EmailChangeRequest($user, new \DateTimeImmutable('+1 hour'), 'selector', 'hash', 'new@example.com');

        $this->entityManager->expects($this->once())->method('persist')->with($request);
        $this->entityManager->expects($this->once())->method('flush');

        $this->repository->persistEmailChangeRequest($request);
    }

    public function testFindEmailChangeRequestBySelector(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $request = new EmailChangeRequest($user, new \DateTimeImmutable('+1 hour'), 'selector123', 'hash', 'new@example.com');

        $entityRepo = $this->createMock(EntityRepository::class);
        $entityRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['selector' => 'selector123'])
            ->willReturn($request);

        $this->entityManager->method('getRepository')
            ->with(EmailChangeRequest::class)
            ->willReturn($entityRepo);

        $result = $this->repository->findEmailChangeRequest('selector123');

        $this->assertSame($request, $result);
    }

    public function testFindEmailChangeRequestByUser(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $request = new EmailChangeRequest($user, new \DateTimeImmutable('+1 hour'), 'selector', 'hash', 'new@example.com');

        $entityRepo = $this->createMock(EntityRepository::class);
        $entityRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['userIdentifier' => TestUser::class.'::1'])
            ->willReturn($request);

        $this->entityManager->method('getRepository')
            ->with(EmailChangeRequest::class)
            ->willReturn($entityRepo);

        $result = $this->repository->findEmailChangeRequest($user);

        $this->assertSame($request, $result);
    }

    public function testFindEmailChangeRequestReturnsNullWhenNotFound(): void
    {
        $entityRepo = $this->createMock(EntityRepository::class);
        $entityRepo->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->with(EmailChangeRequest::class)
            ->willReturn($entityRepo);

        $result = $this->repository->findEmailChangeRequest('nonexistent');

        $this->assertNull($result);
    }

    public function testGetUserFromRequest(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $request = new EmailChangeRequest($user, new \DateTimeImmutable('+1 hour'), 'selector', 'hash', 'new@example.com');

        $entityRepo = $this->createMock(EntityRepository::class);
        $entityRepo->method('find')->with(1)->willReturn($user);

        $this->entityManager->method('getRepository')
            ->with(TestUser::class)
            ->willReturn($entityRepo);

        $result = $this->repository->getUserFromRequest($request);

        $this->assertSame($user, $result);
    }

    public function testGetUserFromRequestReturnsNullForNonEmailChangeable(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $request = new EmailChangeRequest($user, new \DateTimeImmutable('+1 hour'), 'selector', 'hash', 'new@example.com');

        $nonEmailChangeable = new \stdClass();
        $entityRepo = $this->createMock(EntityRepository::class);
        $entityRepo->method('find')->willReturn($nonEmailChangeable);

        $this->entityManager->method('getRepository')
            ->with(TestUser::class)
            ->willReturn($entityRepo);

        $result = $this->repository->getUserFromRequest($request);

        $this->assertNull($result);
    }

    public function testRemoveEmailChangeRequest(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $request = new EmailChangeRequest($user, new \DateTimeImmutable('+1 hour'), 'selector', 'hash', 'new@example.com');

        $this->entityManager->expects($this->once())->method('remove')->with($request);
        $this->entityManager->expects($this->once())->method('flush');

        $this->repository->removeEmailChangeRequest($request);
    }

    public function testFindByOldEmailSelector(): void
    {
        $user = new TestUser(1, 'test@example.com');
        $request = new EmailChangeRequest($user, new \DateTimeImmutable('+1 hour'), 'selector', 'hash', 'new@example.com');
        $request->setOldEmailSelector('old_selector');

        $entityRepo = $this->createMock(EntityRepository::class);
        $entityRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['oldEmailSelector' => 'old_selector'])
            ->willReturn($request);

        $this->entityManager->method('getRepository')
            ->with(EmailChangeRequest::class)
            ->willReturn($entityRepo);

        $result = $this->repository->findByOldEmailSelector('old_selector');

        $this->assertSame($request, $result);
    }

    public function testImplementsRepositoryInterface(): void
    {
        $this->assertInstanceOf(
            \Makraz\Bundle\VerifyEmailChange\Persistence\EmailChangeRequestRepositoryInterface::class,
            $this->repository
        );
    }

    public function testBackwardCompatibilityClass(): void
    {
        $deprecatedRepository = new \Makraz\Bundle\VerifyEmailChange\Persistence\EmailChangeRequestRepository(
            $this->entityManager
        );

        $this->assertInstanceOf(DoctrineEmailChangeRequestRepository::class, $deprecatedRepository);
        $this->assertInstanceOf(
            \Makraz\Bundle\VerifyEmailChange\Persistence\EmailChangeRequestRepositoryInterface::class,
            $deprecatedRepository
        );
    }
}

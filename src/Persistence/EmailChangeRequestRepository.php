<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;

class EmailChangeRequestRepository implements EmailChangeRequestRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function persistEmailChangeRequest(EmailChangeRequest $request): void
    {
        $this->entityManager->persist($request);
        $this->entityManager->flush();
    }

    public function findEmailChangeRequest(EmailChangeableInterface|string $userOrSelector): ?EmailChangeRequest
    {
        $repository = $this->entityManager->getRepository(EmailChangeRequest::class);

        if (is_string($userOrSelector)) {
            return $repository->findOneBy(['selector' => $userOrSelector]);
        }

        $userIdentifier = get_class($userOrSelector).'::'.$userOrSelector->getId();

        return $repository->findOneBy(['userIdentifier' => $userIdentifier]);
    }

    public function getUserFromRequest(EmailChangeRequest $request): ?EmailChangeableInterface
    {
        $userClass = $request->getUserClass();
        $userId = $request->getUserId();

        $user = $this->entityManager->getRepository($userClass)->find($userId);

        if (!$user instanceof EmailChangeableInterface) {
            return null;
        }

        return $user;
    }

    public function removeEmailChangeRequest(EmailChangeRequest $request): void
    {
        $this->entityManager->remove($request);
        $this->entityManager->flush();
    }

    public function removeExpiredEmailChangeRequests(): int
    {
        $qb = $this->entityManager->createQueryBuilder();

        return $qb->delete(EmailChangeRequest::class, 'ecr')
            ->where('ecr.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}

<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Persistence;

use Makraz\Bundle\VerifyEmailChange\Persistence\Doctrine\DoctrineEmailChangeRequestRepository;

/**
 * @deprecated since 1.4, use DoctrineEmailChangeRequestRepository instead.
 *             This class will be removed in 2.0.
 */
class EmailChangeRequestRepository extends DoctrineEmailChangeRequestRepository
{
}

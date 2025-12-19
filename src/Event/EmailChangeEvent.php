<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;

abstract class EmailChangeEvent extends Event
{
    public function __construct(
        private readonly EmailChangeableInterface $user,
    ) {
    }

    public function getUser(): EmailChangeableInterface
    {
        return $this->user;
    }
}

<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeInterface;

abstract class EmailChangeEvent extends Event
{
    public function __construct(
        private readonly EmailChangeInterface $user,
    ) {
    }

    public function getUser(): EmailChangeInterface
    {
        return $this->user;
    }
}

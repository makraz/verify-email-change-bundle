<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Event;

use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;

class EmailChangeNewEmailConfirmedEvent extends EmailChangeEvent
{
    public function __construct(
        EmailChangeableInterface $user,
        private readonly string $newEmail,
    ) {
        parent::__construct($user);
    }

    public function getNewEmail(): string
    {
        return $this->newEmail;
    }
}

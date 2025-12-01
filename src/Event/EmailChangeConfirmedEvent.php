<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Event;

use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeInterface;

class EmailChangeConfirmedEvent extends EmailChangeEvent
{
    public function __construct(
        EmailChangeInterface $user,
        private readonly string $oldEmail,
        private readonly string $newEmail,
    ) {
        parent::__construct($user);
    }

    public function getOldEmail(): string
    {
        return $this->oldEmail;
    }

    public function getNewEmail(): string
    {
        return $this->newEmail;
    }
}

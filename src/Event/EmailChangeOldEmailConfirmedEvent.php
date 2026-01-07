<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Event;

use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;

class EmailChangeOldEmailConfirmedEvent extends EmailChangeEvent
{
    public function __construct(
        EmailChangeableInterface $user,
        private readonly string $oldEmail,
    ) {
        parent::__construct($user);
    }

    public function getOldEmail(): string
    {
        return $this->oldEmail;
    }
}

<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Event;

use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeInterface;

class EmailChangeCancelledEvent extends EmailChangeEvent
{
    public function __construct(
        EmailChangeInterface $user,
        private readonly string $cancelledEmail,
    ) {
        parent::__construct($user);
    }

    public function getCancelledEmail(): string
    {
        return $this->cancelledEmail;
    }
}

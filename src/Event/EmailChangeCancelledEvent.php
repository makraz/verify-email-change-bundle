<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Event;

use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;

class EmailChangeCancelledEvent extends EmailChangeEvent
{
    public function __construct(
        EmailChangeableInterface $user,
        private readonly string $cancelledEmail,
    ) {
        parent::__construct($user);
    }

    public function getCancelledEmail(): string
    {
        return $this->cancelledEmail;
    }
}

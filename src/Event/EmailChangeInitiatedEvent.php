<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Event;

use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;

class EmailChangeInitiatedEvent extends EmailChangeEvent
{
    public function __construct(
        EmailChangeableInterface $user,
        private readonly string $newEmail,
        private readonly string $oldEmail,
        private readonly string $verificationUrl,
    ) {
        parent::__construct($user);
    }

    public function getNewEmail(): string
    {
        return $this->newEmail;
    }

    public function getOldEmail(): string
    {
        return $this->oldEmail;
    }

    public function getVerificationUrl(): string
    {
        return $this->verificationUrl;
    }
}

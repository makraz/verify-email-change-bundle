<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Exception;

interface VerifyEmailChangeExceptionInterface extends \Throwable
{
    public function getReason(): string;
}

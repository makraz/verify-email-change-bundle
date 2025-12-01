<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Exception;

class ExpiredEmailChangeRequestException extends \Exception implements VerifyEmailChangeExceptionInterface
{
    public function getReason(): string
    {
        return 'The email change link has expired. Please request a new one.';
    }
}

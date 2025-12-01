<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Exception;

class InvalidEmailChangeRequestException extends \Exception implements VerifyEmailChangeExceptionInterface
{
    public function getReason(): string
    {
        return $this->getMessage() ?: 'The email change link is invalid.';
    }
}

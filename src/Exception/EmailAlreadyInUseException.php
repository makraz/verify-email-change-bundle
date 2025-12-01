<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Exception;

/**
 * Exception thrown when attempting to change to an email address that is already in use.
 */
class EmailAlreadyInUseException extends \Exception implements VerifyEmailChangeExceptionInterface
{
    public function __construct(string $email, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('This email address is already in use: %s', $email),
            0,
            $previous
        );
    }

    public function getReason(): string
    {
        return 'This email address is already in use.';
    }
}

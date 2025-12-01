<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Exception;

/**
 * Exception thrown when attempting to change to the same email address.
 */
class SameEmailException extends \Exception implements VerifyEmailChangeExceptionInterface
{
    public function __construct(string $email, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('The new email address is identical to the current one: %s', $email),
            0,
            $previous
        );
    }

    public function getReason(): string
    {
        return 'The new email address is identical to the current one.';
    }
}

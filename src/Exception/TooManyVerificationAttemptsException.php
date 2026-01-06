<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Exception;

class TooManyVerificationAttemptsException extends \Exception implements VerifyEmailChangeExceptionInterface
{
    public function __construct(
        private readonly int $maxAttempts,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getReason(): string
    {
        return sprintf(
            'Too many verification attempts. The request has been invalidated after %d failed attempts.',
            $this->maxAttempts
        );
    }
}

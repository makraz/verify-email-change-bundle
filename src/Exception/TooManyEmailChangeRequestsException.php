<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Exception;

class TooManyEmailChangeRequestsException extends \Exception implements VerifyEmailChangeExceptionInterface
{
    public function __construct(
        private readonly \DateTimeInterface $availableAt,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getAvailableAt(): \DateTimeInterface
    {
        return $this->availableAt;
    }

    public function getReason(): string
    {
        return sprintf(
            'You have already requested an email change. Please wait until %s before trying again.',
            $this->availableAt->format('Y-m-d H:i:s')
        );
    }
}

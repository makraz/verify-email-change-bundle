<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Generator;

/**
 * Value object holding the components of a generated token.
 */
class TokenComponents
{
    public function __construct(
        private readonly string $selector,
        private readonly string $token,
        private readonly string $hashedToken,
    ) {
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getHashedToken(): string
    {
        return $this->hashedToken;
    }
}

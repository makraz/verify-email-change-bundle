<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Generator;

use Makraz\Bundle\VerifyEmailChange\Entity\EmailChangeRequest;

/**
 * Generates and verifies cryptographically secure tokens for email changes.
 *
 * Uses a selector + token pattern:
 * - Selector: publicly visible, used to look up the request
 * - Token: secret, never stored in plain text, only the hash
 */
class EmailChangeTokenGenerator
{
    /**
     * Create a new token for an email change request.
     */
    public function createToken(\DateTimeImmutable $expiresAt, int $userId, string $newEmail): TokenComponents
    {
        // Generate random selector (20 characters hex = 10 bytes)
        $selector = bin2hex(random_bytes(10));

        // Generate random token (64 characters hex = 32 bytes)
        $token = bin2hex(random_bytes(32));

        // Hash the token before storing (never store plain tokens!)
        $hashedToken = $this->hashToken($token);

        return new TokenComponents($selector, $token, $hashedToken);
    }

    /**
     * Verify that a token matches the stored hashed token.
     *
     * Uses hash_equals() to prevent timing attacks.
     */
    public function verifyToken(EmailChangeRequest $request, string $token): bool
    {
        return hash_equals($request->getHashedToken(), $this->hashToken($token));
    }

    /**
     * Hash a token using SHA-256.
     */
    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}

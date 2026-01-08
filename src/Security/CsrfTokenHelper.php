<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Helper for CSRF token validation on email change endpoints.
 *
 * This is an optional convenience service. You can use Symfony's CSRF
 * protection directly if you prefer.
 *
 * Usage in a controller:
 *
 *     if (!$csrfHelper->isTokenValid($request)) {
 *         throw $this->createAccessDeniedException('Invalid CSRF token.');
 *     }
 *     $this->emailChangeHelper->cancelEmailChange($user);
 */
class CsrfTokenHelper
{
    public const TOKEN_ID = 'email_change_cancel';
    public const TOKEN_FIELD = '_csrf_token';

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    /**
     * Generate a CSRF token for the cancel email change action.
     */
    public function generateToken(string $tokenId = self::TOKEN_ID): string
    {
        return $this->csrfTokenManager->getToken($tokenId)->getValue();
    }

    /**
     * Validate a CSRF token from a request.
     */
    public function isTokenValid(Request $request, string $tokenId = self::TOKEN_ID, string $fieldName = self::TOKEN_FIELD): bool
    {
        $tokenValue = $request->request->get($fieldName);

        if (!is_string($tokenValue)) {
            return false;
        }

        return $this->csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $tokenValue));
    }
}

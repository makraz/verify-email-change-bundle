<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Api;

use Makraz\Bundle\VerifyEmailChange\Exception\VerifyEmailChangeExceptionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Factory for creating JSON responses for headless/API email change flows.
 *
 * Provides a consistent JSON structure for success and error responses.
 */
class EmailChangeResponseFactory
{
    /**
     * Create a success response for email change initiation.
     */
    public function initiated(string $newEmail, \DateTimeImmutable $expiresAt): JsonResponse
    {
        return new JsonResponse([
            'status' => 'initiated',
            'message' => 'Verification email sent.',
            'data' => [
                'new_email' => $newEmail,
                'expires_at' => $expiresAt->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }

    /**
     * Create a success response for token validation.
     */
    public function tokenValidated(bool $requiresOldEmailConfirmation = false): JsonResponse
    {
        return new JsonResponse([
            'status' => 'validated',
            'message' => $requiresOldEmailConfirmation
                ? 'Token validated. Awaiting confirmation from old email.'
                : 'Token validated. Email change can be confirmed.',
            'data' => [
                'requires_old_email_confirmation' => $requiresOldEmailConfirmation,
            ],
        ]);
    }

    /**
     * Create a success response for email change confirmation.
     */
    public function confirmed(string $oldEmail, string $newEmail): JsonResponse
    {
        return new JsonResponse([
            'status' => 'confirmed',
            'message' => 'Email address changed successfully.',
            'data' => [
                'old_email' => $oldEmail,
                'new_email' => $newEmail,
            ],
        ]);
    }

    /**
     * Create a success response for email change cancellation.
     */
    public function cancelled(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'cancelled',
            'message' => 'Email change request cancelled.',
        ]);
    }

    /**
     * Create a response showing pending email change status.
     */
    public function pendingStatus(
        ?string $pendingEmail,
        bool $confirmedByNewEmail = false,
        bool $confirmedByOldEmail = false,
    ): JsonResponse {
        if ($pendingEmail === null) {
            return new JsonResponse([
                'status' => 'none',
                'message' => 'No pending email change.',
                'data' => [
                    'has_pending' => false,
                ],
            ]);
        }

        return new JsonResponse([
            'status' => 'pending',
            'message' => 'Email change is pending verification.',
            'data' => [
                'has_pending' => true,
                'pending_email' => $pendingEmail,
                'confirmed_by_new_email' => $confirmedByNewEmail,
                'confirmed_by_old_email' => $confirmedByOldEmail,
            ],
        ]);
    }

    /**
     * Create an error response from a bundle exception.
     */
    public function error(VerifyEmailChangeExceptionInterface $exception, int $statusCode = 400): JsonResponse
    {
        return new JsonResponse([
            'status' => 'error',
            'message' => $exception->getReason(),
            'error' => [
                'type' => (new \ReflectionClass($exception))->getShortName(),
            ],
        ], $statusCode);
    }

    /**
     * Create a generic error response.
     */
    public function errorMessage(string $message, int $statusCode = 400): JsonResponse
    {
        return new JsonResponse([
            'status' => 'error',
            'message' => $message,
        ], $statusCode);
    }
}

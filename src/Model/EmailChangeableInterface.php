<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Model;

/**
 * Minimal interface for entities that support email changes.
 *
 * Implement this interface on your User entity to use the email change functionality.
 * This requires only the essential methods: getId(), getEmail(), and setEmail().
 */
interface EmailChangeableInterface
{
    public function getId(): mixed;

    public function getEmail(): string;

    public function setEmail(string $email): static;
}

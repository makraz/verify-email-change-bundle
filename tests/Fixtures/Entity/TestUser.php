<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity;

use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeInterface;

/**
 * Test user entity for unit and integration tests.
 */
class TestUser implements EmailChangeInterface
{
    private ?int $id;
    private string $email;

    public function __construct(?int $id = null, string $email = 'user@example.com')
    {
        $this->id = $id;
        $this->email = $email;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function hasPendingEmailChange(): bool
    {
        // For test fixtures, return false by default
        return false;
    }

    public function getPendingEmail(): ?string
    {
        // For test fixtures, return null by default
        return null;
    }
}

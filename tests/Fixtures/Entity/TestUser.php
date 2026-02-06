<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity;

use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;

/**
 * Test user entity for unit and integration tests.
 */
class TestUser implements EmailChangeableInterface
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
}

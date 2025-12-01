<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Model;

interface EmailChangeInterface
{
    public function getId(): ?int;

    public function getEmail(): string;

    public function setEmail(string $email): static;
}

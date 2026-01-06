<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;

/**
 * Stores email change verification requests.
 *
 * Uses a selector + hashed token pattern (like reset-password-bundle)
 * to prevent timing attacks and ensure security.
 */
#[ORM\Entity]
#[ORM\Table(name: 'email_change_request')]
#[ORM\Index(name: 'email_change_selector_idx', columns: ['selector'])]
#[ORM\Index(name: 'email_change_user_idx', columns: ['user_identifier'])]
class EmailChangeRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 20, unique: true)]
    private string $selector;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $hashedToken;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $newEmail;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $userIdentifier;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $attempts = 0;

    public function __construct(
        EmailChangeableInterface $user,
        \DateTimeImmutable $expiresAt,
        string $selector,
        string $hashedToken,
        string $newEmail
    ) {
        $this->userIdentifier = $this->createUserIdentifier($user);
        $this->requestedAt = new \DateTimeImmutable();
        $this->expiresAt = $expiresAt;
        $this->selector = $selector;
        $this->hashedToken = $hashedToken;
        $this->newEmail = $newEmail;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function getHashedToken(): string
    {
        return $this->hashedToken;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable();
    }

    public function getNewEmail(): string
    {
        return $this->newEmail;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getUserClass(): string
    {
        return explode('::', $this->userIdentifier)[0];
    }

    public function getUserId(): int
    {
        return (int) explode('::', $this->userIdentifier)[1];
    }

    private function createUserIdentifier(EmailChangeableInterface $user): string
    {
        return get_class($user).'::'.$user->getId();
    }

    public function belongsTo(EmailChangeableInterface $user): bool
    {
        return $this->userIdentifier === $this->createUserIdentifier($user);
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): void
    {
        ++$this->attempts;
    }
}

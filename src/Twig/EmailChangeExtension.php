<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Twig;

use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeHelper;
use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for email change functionality.
 *
 * Provides convenient Twig functions to check for pending email changes.
 */
class EmailChangeExtension extends AbstractExtension
{
    public function __construct(
        private readonly EmailChangeHelper $emailChangeHelper
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('has_pending_email_change', [$this, 'hasPendingEmailChange']),
            new TwigFunction('get_pending_email', [$this, 'getPendingEmail']),
        ];
    }

    /**
     * Check if a user has a pending email change request.
     */
    public function hasPendingEmailChange(EmailChangeInterface $user): bool
    {
        return $this->emailChangeHelper->hasPendingEmailChange($user);
    }

    /**
     * Get the pending email address for a user.
     */
    public function getPendingEmail(EmailChangeInterface $user): ?string
    {
        return $this->emailChangeHelper->getPendingEmail($user);
    }
}

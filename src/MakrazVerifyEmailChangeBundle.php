<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Makraz Verify Email Change Bundle.
 *
 * Provides secure email address change functionality with verification.
 *
 * @author Makraz <support@Makraz.com>
 */
class MakrazVerifyEmailChangeBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}

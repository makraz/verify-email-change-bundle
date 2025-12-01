<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\Twig;

use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeHelper;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use Makraz\Bundle\VerifyEmailChange\Twig\EmailChangeExtension;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class EmailChangeExtensionTest extends TestCase
{
    private EmailChangeHelper&MockObject $emailChangeHelper;
    private EmailChangeExtension $extension;

    protected function setUp(): void
    {
        $this->emailChangeHelper = $this->createMock(EmailChangeHelper::class);
        $this->extension = new EmailChangeExtension($this->emailChangeHelper);
    }

    public function testGetFunctionsReturnsTwigFunctions(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertCount(2, $functions);
        $this->assertSame('has_pending_email_change', $functions[0]->getName());
        $this->assertSame('get_pending_email', $functions[1]->getName());
    }

    public function testHasPendingEmailChangeCallsHelper(): void
    {
        $user = new TestUser(1, 'test@example.com');

        $this->emailChangeHelper->expects($this->once())
            ->method('hasPendingEmailChange')
            ->with($user)
            ->willReturn(true);

        $result = $this->extension->hasPendingEmailChange($user);

        $this->assertTrue($result);
    }

    public function testGetPendingEmailCallsHelper(): void
    {
        $user = new TestUser(1, 'test@example.com');

        $this->emailChangeHelper->expects($this->once())
            ->method('getPendingEmail')
            ->with($user)
            ->willReturn('new@example.com');

        $result = $this->extension->getPendingEmail($user);

        $this->assertSame('new@example.com', $result);
    }

    public function testGetPendingEmailReturnsNullWhenNoPending(): void
    {
        $user = new TestUser(1, 'test@example.com');

        $this->emailChangeHelper->expects($this->once())
            ->method('getPendingEmail')
            ->with($user)
            ->willReturn(null);

        $result = $this->extension->getPendingEmail($user);

        $this->assertNull($result);
    }
}

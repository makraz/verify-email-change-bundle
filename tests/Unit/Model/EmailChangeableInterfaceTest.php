<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\Model;

use Makraz\Bundle\VerifyEmailChange\Model\EmailChangeableInterface;
use Makraz\Bundle\VerifyEmailChange\Tests\Fixtures\Entity\TestUser;
use PHPUnit\Framework\TestCase;

class EmailChangeableInterfaceTest extends TestCase
{
    public function testEmailChangeableInterfaceHasRequiredMethods(): void
    {
        $reflection = new \ReflectionClass(EmailChangeableInterface::class);
        $methods = array_map(fn (\ReflectionMethod $m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('getId', $methods);
        $this->assertContains('getEmail', $methods);
        $this->assertContains('setEmail', $methods);
        $this->assertCount(3, $methods);
    }

    public function testTestUserImplementsEmailChangeableInterface(): void
    {
        $user = new TestUser(1, 'test@example.com');

        $this->assertInstanceOf(EmailChangeableInterface::class, $user);
    }

    public function testEmailChangeableInterfaceMethodsWorkCorrectly(): void
    {
        $user = new TestUser(42, 'user@example.com');

        $this->assertSame(42, $user->getId());
        $this->assertSame('user@example.com', $user->getEmail());

        $result = $user->setEmail('new@example.com');
        $this->assertSame('new@example.com', $user->getEmail());
        $this->assertInstanceOf(EmailChangeableInterface::class, $result);
    }

    public function testCanTypeHintAgainstEmailChangeableInterface(): void
    {
        $user = new TestUser(1, 'test@example.com');

        $email = $this->extractEmail($user);
        $this->assertSame('test@example.com', $email);
    }

    private function extractEmail(EmailChangeableInterface $user): string
    {
        return $user->getEmail();
    }
}

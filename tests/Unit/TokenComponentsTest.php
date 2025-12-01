<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit;

use Makraz\Bundle\VerifyEmailChange\Generator\TokenComponents;
use PHPUnit\Framework\TestCase;

class TokenComponentsTest extends TestCase
{
    public function testConstructorInitializesAllProperties(): void
    {
        $selector = 'abc123';
        $token = 'token456';
        $hashedToken = 'hashedtoken789';

        $components = new TokenComponents($selector, $token, $hashedToken);

        $this->assertSame($selector, $components->getSelector());
        $this->assertSame($token, $components->getToken());
        $this->assertSame($hashedToken, $components->getHashedToken());
    }

    public function testGettersReturnCorrectValues(): void
    {
        $components = new TokenComponents('sel', 'tok', 'hash');

        $this->assertSame('sel', $components->getSelector());
        $this->assertSame('tok', $components->getToken());
        $this->assertSame('hash', $components->getHashedToken());
    }

    public function testCanHandleEmptyStrings(): void
    {
        $components = new TokenComponents('', '', '');

        $this->assertSame('', $components->getSelector());
        $this->assertSame('', $components->getToken());
        $this->assertSame('', $components->getHashedToken());
    }

    public function testCanHandleLongStrings(): void
    {
        $longSelector = str_repeat('a', 1000);
        $longToken = str_repeat('b', 1000);
        $longHashedToken = str_repeat('c', 1000);

        $components = new TokenComponents($longSelector, $longToken, $longHashedToken);

        $this->assertSame($longSelector, $components->getSelector());
        $this->assertSame($longToken, $components->getToken());
        $this->assertSame($longHashedToken, $components->getHashedToken());
    }
}

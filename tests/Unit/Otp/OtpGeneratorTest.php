<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\Otp;

use Makraz\Bundle\VerifyEmailChange\Otp\OtpGenerator;
use PHPUnit\Framework\TestCase;

class OtpGeneratorTest extends TestCase
{
    public function testDefaultLengthIsSix(): void
    {
        $generator = new OtpGenerator();
        $this->assertSame(6, $generator->getLength());
    }

    public function testGeneratesCodeOfCorrectLength(): void
    {
        $generator = new OtpGenerator(6);
        $otp = $generator->generate();

        $this->assertSame(6, strlen($otp));
        $this->assertTrue(ctype_digit($otp));
    }

    public function testGeneratesCodeOfCustomLength(): void
    {
        $generator = new OtpGenerator(8);
        $otp = $generator->generate();

        $this->assertSame(8, strlen($otp));
        $this->assertTrue(ctype_digit($otp));
    }

    public function testGeneratesFourDigitCode(): void
    {
        $generator = new OtpGenerator(4);
        $otp = $generator->generate();

        $this->assertSame(4, strlen($otp));
        $this->assertGreaterThanOrEqual(1000, (int) $otp);
        $this->assertLessThanOrEqual(9999, (int) $otp);
    }

    public function testGeneratedCodesAreRandom(): void
    {
        $generator = new OtpGenerator(6);
        $codes = [];

        for ($i = 0; $i < 50; ++$i) {
            $codes[] = $generator->generate();
        }

        // With 50 6-digit codes, we should have many unique values
        $unique = array_unique($codes);
        $this->assertGreaterThan(40, count($unique));
    }

    public function testHashProducesConsistentResult(): void
    {
        $generator = new OtpGenerator();
        $otp = '123456';

        $hash1 = $generator->hash($otp);
        $hash2 = $generator->hash($otp);

        $this->assertSame($hash1, $hash2);
    }

    public function testHashDiffersForDifferentInputs(): void
    {
        $generator = new OtpGenerator();

        $hash1 = $generator->hash('123456');
        $hash2 = $generator->hash('654321');

        $this->assertNotSame($hash1, $hash2);
    }

    public function testVerifyWithCorrectOtp(): void
    {
        $generator = new OtpGenerator();
        $otp = '123456';
        $hash = $generator->hash($otp);

        $this->assertTrue($generator->verify($otp, $hash));
    }

    public function testVerifyWithIncorrectOtp(): void
    {
        $generator = new OtpGenerator();
        $hash = $generator->hash('123456');

        $this->assertFalse($generator->verify('654321', $hash));
    }

    public function testVerifyRoundTrip(): void
    {
        $generator = new OtpGenerator();
        $otp = $generator->generate();
        $hash = $generator->hash($otp);

        $this->assertTrue($generator->verify($otp, $hash));
        $this->assertFalse($generator->verify('000000', $hash));
    }
}

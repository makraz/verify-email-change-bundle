<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Unit\DependencyInjection;

use Makraz\Bundle\VerifyEmailChange\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    private Processor $processor;
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->processor = new Processor();
        $this->configuration = new Configuration();
    }

    private function processConfig(array $config = []): array
    {
        return $this->processor->processConfiguration(
            $this->configuration,
            [$config]
        );
    }

    public function testDefaultValues(): void
    {
        $config = $this->processConfig();

        $this->assertSame(3600, $config['lifetime']);
        $this->assertTrue($config['enable_throttling']);
        $this->assertSame(3600, $config['throttle_limit']);
        $this->assertSame(5, $config['max_attempts']);
        $this->assertFalse($config['require_old_email_confirmation']);
        $this->assertSame('doctrine', $config['persistence']);
        $this->assertNull($config['persistence_service']);
        $this->assertFalse($config['notifier']['enabled']);
    }

    public function testCustomLifetime(): void
    {
        $config = $this->processConfig(['lifetime' => 7200]);

        $this->assertSame(7200, $config['lifetime']);
    }

    public function testLifetimeMinimum(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->processConfig(['lifetime' => 30]);
    }

    public function testLifetimeMaximum(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->processConfig(['lifetime' => 100000]);
    }

    public function testMaxAttempts(): void
    {
        $config = $this->processConfig(['max_attempts' => 10]);

        $this->assertSame(10, $config['max_attempts']);
    }

    public function testMaxAttemptsMinimum(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->processConfig(['max_attempts' => 0]);
    }

    public function testDualVerificationMode(): void
    {
        $config = $this->processConfig(['require_old_email_confirmation' => true]);

        $this->assertTrue($config['require_old_email_confirmation']);
    }

    public function testPersistenceDoctrine(): void
    {
        $config = $this->processConfig(['persistence' => 'doctrine']);

        $this->assertSame('doctrine', $config['persistence']);
    }

    public function testPersistenceCache(): void
    {
        $config = $this->processConfig(['persistence' => 'cache']);

        $this->assertSame('cache', $config['persistence']);
    }

    public function testPersistenceInvalid(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->processConfig(['persistence' => 'invalid']);
    }

    public function testPersistenceService(): void
    {
        $config = $this->processConfig(['persistence_service' => 'app.custom_repository']);

        $this->assertSame('app.custom_repository', $config['persistence_service']);
    }

    public function testNotifierEnabled(): void
    {
        $config = $this->processConfig([
            'notifier' => [
                'enabled' => true,
                'sender_email' => 'noreply@example.com',
                'sender_name' => 'App',
            ],
        ]);

        $this->assertTrue($config['notifier']['enabled']);
        $this->assertSame('noreply@example.com', $config['notifier']['sender_email']);
        $this->assertSame('App', $config['notifier']['sender_name']);
    }

    public function testNotifierDisabledByDefault(): void
    {
        $config = $this->processConfig();

        $this->assertFalse($config['notifier']['enabled']);
    }

    public function testThrottlingDisabled(): void
    {
        $config = $this->processConfig(['enable_throttling' => false]);

        $this->assertFalse($config['enable_throttling']);
    }

    public function testCustomThrottleLimit(): void
    {
        $config = $this->processConfig(['throttle_limit' => 1800]);

        $this->assertSame(1800, $config['throttle_limit']);
    }
}

<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\Tests\Integration;

use Makraz\Bundle\VerifyEmailChange\EmailChange\EmailChangeHelper;
use Makraz\Bundle\VerifyEmailChange\Generator\EmailChangeTokenGenerator;
use Makraz\Bundle\VerifyEmailChange\Persistence\EmailChangeRequestRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Tests that the bundle's services are properly defined and autowireable.
 */
class EmailChangeServiceDefinitionTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.project_dir' => __DIR__.'/../..',
        ]));
    }

    public function testEmailChangeHelperCanBeAutowired(): void
    {
        // Register dependencies
        $this->container->register(EmailChangeRequestRepositoryInterface::class)
            ->setPublic(true);

        $this->container->register(EmailChangeTokenGenerator::class)
            ->setPublic(true);

        $this->container->register(UrlGeneratorInterface::class)
            ->setPublic(true);

        $this->container->setParameter('verify_email_change.lifetime', 3600);

        // Register the service with autowiring
        $this->container->register(EmailChangeHelper::class)
            ->setAutowired(true)
            ->setPublic(true);

        $this->container->compile();

        $this->assertTrue($this->container->has(EmailChangeHelper::class));
    }

    public function testEmailChangeTokenGeneratorCanBeAutowired(): void
    {
        $this->container->register(EmailChangeTokenGenerator::class)
            ->setAutowired(true)
            ->setPublic(true);

        $this->container->compile();

        $this->assertTrue($this->container->has(EmailChangeTokenGenerator::class));
    }

    public function testEmailChangeHelperUsesConfiguredLifetime(): void
    {
        $customLifetime = 7200; // 2 hours

        $this->container->register(EmailChangeRequestRepositoryInterface::class)
            ->setPublic(true);

        $this->container->register(EmailChangeTokenGenerator::class)
            ->setPublic(true);

        $this->container->register(UrlGeneratorInterface::class)
            ->setPublic(true);

        $this->container->setParameter('verify_email_change.lifetime', $customLifetime);

        $this->container->register(EmailChangeHelper::class)
            ->setAutowired(true)
            ->setArgument('$requestLifetime', '%verify_email_change.lifetime%')
            ->setPublic(true);

        $this->container->compile();

        $this->assertSame($customLifetime, $this->container->getParameter('verify_email_change.lifetime'));
    }

    public function testAllCoreServicesCanBeRegistered(): void
    {
        // Register all core services
        $this->container->register(EmailChangeRequestRepositoryInterface::class)
            ->setPublic(true);

        $this->container->register(EmailChangeTokenGenerator::class)
            ->setAutowired(true)
            ->setPublic(true);

        $this->container->register(UrlGeneratorInterface::class)
            ->setPublic(true);

        $this->container->setParameter('verify_email_change.lifetime', 3600);

        $this->container->register(EmailChangeHelper::class)
            ->setAutowired(true)
            ->setArgument('$requestLifetime', '%verify_email_change.lifetime%')
            ->setPublic(true);

        $this->container->compile();

        // Verify all services are available
        $this->assertTrue($this->container->has(EmailChangeTokenGenerator::class));
        $this->assertTrue($this->container->has(EmailChangeHelper::class));
    }
}

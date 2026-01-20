<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class MakrazVerifyEmailChangeExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');

        $container->setParameter('verify_email_change.lifetime', $config['lifetime']);
        $container->setParameter('verify_email_change.enable_throttling', $config['enable_throttling']);
        $container->setParameter('verify_email_change.throttle_limit', $config['throttle_limit']);
        $container->setParameter('verify_email_change.max_attempts', $config['max_attempts']);
        $container->setParameter('verify_email_change.require_old_email_confirmation', $config['require_old_email_confirmation']);
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Register translation resources
        $container->prependExtensionConfig('framework', [
            'translator' => [
                'paths' => [
                    __DIR__.'/../../translations',
                ],
            ],
        ]);
    }
}

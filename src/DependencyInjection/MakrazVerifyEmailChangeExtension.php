<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class MakrazVerifyEmailChangeExtension extends Extension
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
    }
}

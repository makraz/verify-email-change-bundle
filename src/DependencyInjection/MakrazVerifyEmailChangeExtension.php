<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\DependencyInjection;

use Makraz\Bundle\VerifyEmailChange\Notifier\EmailChangeNotifier;
use Makraz\Bundle\VerifyEmailChange\Otp\OtpEmailChangeHelper;
use Makraz\Bundle\VerifyEmailChange\Otp\OtpGenerator;
use Makraz\Bundle\VerifyEmailChange\Persistence\Cache\CacheEmailChangeRequestRepository;
use Makraz\Bundle\VerifyEmailChange\Persistence\Doctrine\DoctrineEmailChangeRequestRepository;
use Makraz\Bundle\VerifyEmailChange\Persistence\EmailChangeRequestRepositoryInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

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

        // Configure persistence adapter
        $this->configurePersistence($config, $container);

        // Register EmailChangeNotifier if enabled
        if ($config['notifier']['enabled']) {
            $notifierDefinition = new Definition(EmailChangeNotifier::class);
            $notifierDefinition->setArguments([
                new Reference('mailer'),
                new Reference('twig'),
                $config['notifier']['sender_email'],
                $config['notifier']['sender_name'],
            ]);
            $notifierDefinition->setAutowired(false);
            $notifierDefinition->setPublic(false);
            $container->setDefinition(EmailChangeNotifier::class, $notifierDefinition);
        }

        // Register OTP services if enabled
        if ($config['otp']['enabled']) {
            $otpGeneratorDefinition = new Definition(OtpGenerator::class);
            $otpGeneratorDefinition->setArguments([$config['otp']['length']]);
            $container->setDefinition(OtpGenerator::class, $otpGeneratorDefinition);

            $otpHelperDefinition = new Definition(OtpEmailChangeHelper::class);
            $otpHelperDefinition->setArguments([
                new Reference(EmailChangeRequestRepositoryInterface::class),
                new Reference(\Makraz\Bundle\VerifyEmailChange\Generator\EmailChangeTokenGenerator::class),
                new Reference(OtpGenerator::class),
                $config['lifetime'],
                $config['max_attempts'],
            ]);
            $container->setDefinition(OtpEmailChangeHelper::class, $otpHelperDefinition);
        }
    }

    private function configurePersistence(array $config, ContainerBuilder $container): void
    {
        $storage = $config['storage'];
        $customService = $config['storage_service'];

        if ($customService !== null) {
            // Custom service ID provided — alias the interface to it
            $container->setAlias(EmailChangeRequestRepositoryInterface::class, $customService);

            return;
        }

        $adapterMap = [
            'database' => DoctrineEmailChangeRequestRepository::class,
            'stateless' => CacheEmailChangeRequestRepository::class,
        ];

        if (isset($adapterMap[$storage])) {
            $container->setAlias(
                EmailChangeRequestRepositoryInterface::class,
                $adapterMap[$storage]
            );
        }
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

        // Register Twig templates
        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    __DIR__.'/../../templates' => 'MakrazVerifyEmailChange',
                ],
            ]);
        }
    }
}

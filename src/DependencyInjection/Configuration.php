<?php

declare(strict_types=1);

namespace Makraz\Bundle\VerifyEmailChange\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('verify_email_change');

        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('lifetime')
                    ->info('Time in seconds that an email change request is valid')
                    ->defaultValue(3600)
                    ->min(60)
                    ->max(86400)
                ->end()
                ->booleanNode('enable_throttling')
                    ->info('Enable request throttling to prevent abuse')
                    ->defaultValue(true)
                ->end()
                ->integerNode('throttle_limit')
                    ->info('Time in seconds before a new request can be made')
                    ->defaultValue(3600)
                    ->min(60)
                ->end()
                ->integerNode('max_attempts')
                    ->info('Maximum number of failed verification attempts before auto-invalidation')
                    ->defaultValue(5)
                    ->min(1)
                ->end()
                ->booleanNode('require_old_email_confirmation')
                    ->info('Require confirmation from the old email address before completing email change')
                    ->defaultValue(false)
                ->end()
                ->enumNode('storage')
                    ->info('Storage backend: "database" uses Doctrine ORM, "stateless" uses PSR-6 cache (Redis, Memcached, etc.)')
                    ->values(['database', 'stateless'])
                    ->defaultValue('database')
                ->end()
                ->scalarNode('storage_service')
                    ->info('Custom service ID for the repository (overrides storage option)')
                    ->defaultNull()
                ->end()
                ->arrayNode('otp')
                    ->info('Configuration for OTP-based verification (alternative to signed URLs)')
                    ->canBeEnabled()
                    ->children()
                        ->integerNode('length')
                            ->info('Number of digits in the OTP code')
                            ->defaultValue(6)
                            ->min(4)
                            ->max(10)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('notifier')
                    ->info('Configuration for the optional EmailChangeNotifier service')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('sender_email')
                            ->info('The sender email address for notification emails')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('sender_name')
                            ->info('The sender name for notification emails')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}

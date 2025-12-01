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
            ->end()
        ;

        return $treeBuilder;
    }
}

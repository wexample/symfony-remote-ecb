<?php

namespace Wexample\SymfonyRemoteEcb\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Wexample\SymfonyRemoteEcb\Service\EcbExchangeRateProvider;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_remote_ecb');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('url')->defaultValue(EcbExchangeRateProvider::DEFAULT_URL)->info('Where the ECB publishes its euro reference rates.')->end()
            ->end();

        return $treeBuilder;
    }
}

<?php

namespace Wexample\SymfonyRemoteEcb\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;
use Wexample\SymfonyMoney\Interface\ExchangeRateProviderInterface;
use Wexample\SymfonyRemoteEcb\Service\EcbExchangeRateProvider;

class WexampleSymfonyRemoteEcbExtension extends AbstractWexampleSymfonyExtension
{
    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setParameter('wexample_symfony_remote_ecb.url', $config['url']);

        $this->loadConfig(
            __DIR__,
            $container
        );

        $container->setAlias(ExchangeRateProviderInterface::class, EcbExchangeRateProvider::class);
    }
}

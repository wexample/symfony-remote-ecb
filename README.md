# symfony-remote-ecb

Version: 3.0.4

Exchange rates from the European Central Bank reference rates, as symfony-money's provider, declared as a symfony-remote

## Table of Contents

- [Installation](#installation)
- [Introduction](#introduction)

## Installation

```php
// config/bundles.php
Wexample\SymfonyRemote\WexampleSymfonyRemoteBundle::class => ['all' => true],
Wexample\SymfonyMoney\WexampleSymfonyMoneyBundle::class => ['all' => true],
Wexample\SymfonyRemoteEcb\WexampleSymfonyRemoteEcbBundle::class => ['all' => true],
```

Nothing to configure: the public ECB feed is the default (`wexample_symfony_remote_ecb.url` to point elsewhere). Feeds are cached in `cache.app` for six hours.

`symfony-remote-ecb` gives `symfony-money` its exchange rates from the European Central Bank: the euro foreign exchange reference rates, about thirty currencies against the euro, published each working day around 16:00 CET. `EcbExchangeRateProvider` implements money's `ExchangeRateProviderInterface`, so `ExchangeRateService` answers through it once the bundle is installed. It is also declared as a `symfony-remote`, so `remote:status` checks the feed.

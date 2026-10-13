## Installation

```php
// config/bundles.php
Wexample\SymfonyRemote\WexampleSymfonyRemoteBundle::class => ['all' => true],
Wexample\SymfonyMoney\WexampleSymfonyMoneyBundle::class => ['all' => true],
Wexample\SymfonyRemoteEcb\WexampleSymfonyRemoteEcbBundle::class => ['all' => true],
```

Nothing to configure: the public ECB feed is the default (`wexample_symfony_remote_ecb.url` to point elsewhere). Feeds are cached in `cache.app` for six hours.

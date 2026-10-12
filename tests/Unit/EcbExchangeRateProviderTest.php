<?php

namespace Wexample\SymfonyRemoteEcb\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Wexample\PhpRemote\Enum\RemoteState;
use Wexample\SymfonyRemoteEcb\Exception\EcbException;
use Wexample\SymfonyRemoteEcb\Service\EcbExchangeRateProvider;

class EcbExchangeRateProviderTest extends TestCase
{
    private function feed(): string
    {
        $today = new DateTimeImmutable('today');
        // A Friday, then the following Monday: nothing published in between.
        $friday = $today->modify('last friday')->modify('-7 days');
        $monday = $friday->modify('+3 days');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
  <gesmes:subject>Reference rates</gesmes:subject>
  <Cube>
    <Cube time="{$monday->format('Y-m-d')}">
      <Cube currency="USD" rate="1.0900"/>
      <Cube currency="JPY" rate="160.50"/>
      <Cube currency="GBP" rate="0.8500"/>
    </Cube>
    <Cube time="{$friday->format('Y-m-d')}">
      <Cube currency="USD" rate="1.0845"/>
      <Cube currency="JPY" rate="159.80"/>
      <Cube currency="GBP" rate="0.8460"/>
    </Cube>
  </Cube>
</gesmes:Envelope>
XML;
    }

    private function provider(?array &$urls = null): EcbExchangeRateProvider
    {
        $http = new MockHttpClient(function (string $method, string $url) use (&$urls) {
            $urls[] = $url;

            return new MockResponse($this->feed());
        });

        return new EcbExchangeRateProvider($http, 'https://ecb.test/stats/eurofxref/');
    }

    public function testARateAgainstTheEuroAndItsInverse(): void
    {
        $friday = (new DateTimeImmutable('today'))->modify('last friday')->modify('-7 days');
        $provider = $this->provider($urls);

        $this->assertSame('1.0845', $provider->getRate('eur', 'usd', $friday)->rate);
        $this->assertSame('0.922084', $provider->getRate('USD', 'EUR', $friday)->rate);
        $this->assertSame('ecb', $provider->getRate('USD', 'EUR', $friday)->source);
        $this->assertSame(['https://ecb.test/stats/eurofxref/eurofxref-hist-90d.xml'], $urls);
    }

    public function testACrossRateGoesThroughTheEuro(): void
    {
        $friday = (new DateTimeImmutable('today'))->modify('last friday')->modify('-7 days');

        // 1 GBP = 1.0845 / 0.8460 USD.
        $this->assertSame('1.281915', $this->provider()->getRate('GBP', 'USD', $friday)->rate);
    }

    public function testAWeekendTakesTheLastPublishedDay(): void
    {
        $friday = (new DateTimeImmutable('today'))->modify('last friday')->modify('-7 days');
        $rate = $this->provider()->getRate('EUR', 'USD', $friday->modify('+1 day'));

        $this->assertSame('1.0845', $rate->rate);
        $this->assertSame($friday->format('Y-m-d'), $rate->date->format('Y-m-d'));
    }

    public function testAnUnknownCurrencyOrAnOldDayHasNoRate(): void
    {
        $friday = (new DateTimeImmutable('today'))->modify('last friday')->modify('-7 days');
        $provider = $this->provider($urls);

        $this->assertNull($provider->getRate('EUR', 'XAU', $friday));
        $this->assertNull($provider->getRate('EUR', 'USD', new DateTimeImmutable('2001-01-01')));
        $this->assertContains('https://ecb.test/stats/eurofxref/eurofxref-hist.xml', $urls);
    }

    public function testAnUnreachableFeedIsAnError(): void
    {
        $provider = new EcbExchangeRateProvider(new MockHttpClient(fn () => new MockResponse('', ['http_code' => 503])));

        $this->assertSame(RemoteState::Down, $provider->checkStatus()->state);
        $this->expectException(EcbException::class);
        $provider->getRate('EUR', 'USD', new DateTimeImmutable('today'));
    }
}

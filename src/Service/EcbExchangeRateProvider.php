<?php

namespace Wexample\SymfonyRemoteEcb\Service;

use DateTimeImmutable;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;
use Wexample\PhpRemote\Class\RemoteStatus;
use Wexample\PhpRemote\Interface\RemoteInterface;
use Wexample\SymfonyMoney\Class\ExchangeRate;
use Wexample\SymfonyMoney\Helper\RateHelper;
use Wexample\SymfonyMoney\Interface\ExchangeRateProviderInterface;
use Wexample\SymfonyRemoteEcb\Exception\EcbException;

/**
 * The euro foreign exchange reference rates of the European Central Bank: about
 * thirty currencies against the euro, published each working day around 16:00 CET.
 * A rate between two other currencies goes through the euro.
 *
 * A day without publication (weekend, holiday, today before 16:00) takes the last
 * one before it, up to a week back. Recent days are read from the 90-day feed,
 * older ones from the full history; each feed is cached for a few hours.
 */
class EcbExchangeRateProvider implements ExchangeRateProviderInterface, RemoteInterface, ResetInterface
{
    public const string DEFAULT_URL = 'https://www.ecb.europa.eu/stats/eurofxref';

    public const string FEED_DAILY = 'eurofxref-daily.xml';
    public const string FEED_90_DAYS = 'eurofxref-hist-90d.xml';
    public const string FEED_HISTORY = 'eurofxref-hist.xml';

    private const int LOOK_BACK_DAYS = 7;

    /** @var array<string, array<string, array<string, string>>> Feed → day → currency → rate against the euro. */
    private array $feeds = [];

    public function __construct(
        private readonly ?HttpClientInterface $httpClient = null,
        private readonly string $url = self::DEFAULT_URL,
        private readonly ?CacheInterface $cache = null,
    ) {
    }

    /**
     * The feed changes every day: a worker living longer must read it again.
     */
    public function reset(): void
    {
        $this->feeds = [];
    }

    public function getKey(): string
    {
        return 'ecb';
    }

    public function getLabel(): string
    {
        return 'European Central Bank exchange rates';
    }

    public function checkStatus(): RemoteStatus
    {
        if (null === $this->httpClient) {
            return RemoteStatus::unconfigured('No HTTP client.');
        }

        $response = $this->httpClient->request('GET', $this->feedUrl(self::FEED_DAILY));

        return 200 === $response->getStatusCode() ? RemoteStatus::up() : RemoteStatus::down('HTTP '.$response->getStatusCode());
    }

    public function getRate(
        string $from,
        string $to,
        DateTimeImmutable $date
    ): ?ExchangeRate {
        $from = strtoupper($from);
        $to = strtoupper($to);
        $found = $this->findDay($date);

        if (null === $found) {
            return null;
        }

        [$day, $rates] = $found;
        $rates['EUR'] = '1';

        if (! isset($rates[$from], $rates[$to])) {
            return null;
        }

        // Both against the euro: from → to = rate(to) / rate(from).
        $scaled = RateHelper::mulDiv(
            (new ExchangeRate('EUR', $to, $rates[$to], $day))->scaled(),
            ExchangeRate::SCALE,
            (new ExchangeRate('EUR', $from, $rates[$from], $day))->scaled(),
        );

        return new ExchangeRate($from, $to, ExchangeRate::format($scaled), $day, 'ecb');
    }

    /**
     * @return array{0: DateTimeImmutable, 1: array<string, string>}|null
     */
    private function findDay(DateTimeImmutable $date): ?array
    {
        $feed = $date >= new DateTimeImmutable('-85 days') ? self::FEED_90_DAYS : self::FEED_HISTORY;
        $days = $this->load($feed);

        for ($back = 0; $back <= self::LOOK_BACK_DAYS; ++$back) {
            $day = $date->setTime(0, 0)->modify(sprintf('-%d days', $back));

            if (isset($days[$day->format('Y-m-d')])) {
                return [$day, $days[$day->format('Y-m-d')]];
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function load(string $feed): array
    {
        return $this->feeds[$feed] ??= null === $this->cache
            ? $this->fetch($feed)
            : $this->cache->get('wexample_remote_ecb_'.str_replace(['-', '.'], '_', $feed), function (ItemInterface $item) use ($feed): array {
                $item->expiresAfter(6 * 3600);

                return $this->fetch($feed);
            });
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function fetch(string $feed): array
    {
        if (null === $this->httpClient) {
            throw new EcbException('No HTTP client to reach the ECB.');
        }

        $response = $this->httpClient->request('GET', $this->feedUrl($feed));

        if (200 !== $response->getStatusCode()) {
            throw new EcbException(sprintf('The ECB answered HTTP %d for %s.', $response->getStatusCode(), $feed));
        }

        return self::parse($response->getContent());
    }

    /**
     * The ECB's XML, day by day.
     *
     * @return array<string, array<string, string>>
     */
    public static function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);

        if (false === $document) {
            throw new EcbException('The ECB feed is no XML.');
        }

        $document->registerXPathNamespace('ecb', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');
        $days = [];

        foreach ($document->xpath('//ecb:Cube[@time]') ?: [] as $day) {
            $rates = [];

            foreach ($day->children() as $rate) {
                $rates[(string) $rate['currency']] = (string) $rate['rate'];
            }

            $days[(string) $day['time']] = $rates;
        }

        return $days;
    }

    private function feedUrl(string $feed): string
    {
        return rtrim($this->url, '/').'/'.$feed;
    }
}

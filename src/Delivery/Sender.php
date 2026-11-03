<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Delivery;

use Frootbox\Core\Config\Config;
use Frootbox\Core\Config\Environment;
use Frootbox\Core\Security\StandardWebhooks;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends one attempt of a delivery (decision 0253): POST with the headers
 * of Standard Webhooks, 10 seconds at most, no redirects. Outside of
 * development never to a private or local address: the name is resolved
 * and checked first, and the connection goes to the IP checked.
 */
final readonly class Sender
{
    public const int TIMEOUT = 10;

    private HttpClientInterface $client;

    private bool $anywhere;

    /** @var (callable(string): list<string>)|null */
    private mixed $resolve;

    /**
     * @param (callable(string): list<string>)|null $resolve the IPs of a name, by default from DNS
     */
    public function __construct(Config $config, ?HttpClientInterface $client = null, ?callable $resolve = null)
    {
        $this->client = $client ?? HttpClient::create();
        $this->anywhere = Environment::Development === $config->environment;
        $this->resolve = $resolve;
    }

    public function send(string $url, string $secret, string $id, string $body): Attempt
    {
        $started = hrtime(true);
        $options = [
            'headers' => ['Content-Type' => 'application/json', 'User-Agent' => 'Webhooks (Standard Webhooks)'] + StandardWebhooks::headers($secret, $id, $body),
            'body' => $body,
            'timeout' => self::TIMEOUT,
            'max_duration' => self::TIMEOUT,
            'max_redirects' => 0,
        ];

        if (!$this->anywhere) {
            $host = parse_url($url, \PHP_URL_HOST);
            [$ip, $problem] = PublicAddress::check(\is_string($host) ? $host : '', $this->resolve);

            if (null !== $problem) {
                return new Attempt(null, self::since($started), $problem);
            }

            if (null !== $ip && \is_string($host)) {
                $options['resolve'] = [$host => $ip];
            }
        }

        try {
            $response = $this->client->request('POST', $url, $options);
            $code = $response->getStatusCode();
            $response->cancel();
        } catch (ExceptionInterface $error) {
            return new Attempt(null, self::since($started), $error->getMessage());
        }

        return new Attempt($code, self::since($started), $code >= 200 && $code < 300 ? null : \sprintf('The address answered with status %d.', $code));
    }

    private static function since(int|float $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}

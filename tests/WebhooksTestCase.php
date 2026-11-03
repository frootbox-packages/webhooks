<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Tests;

use Frootbox\Core\Config\Config;
use Frootbox\Core\Extension\ExtensionPackage;
use Frootbox\Core\Extension\Extensions;
use Frootbox\Core\Queue\Worker;
use Frootbox\Core\Tests\Support\NotesTestCase;
use Frootbox\Webhooks\Delivery\Deliveries;
use Frootbox\Webhooks\Delivery\Sender;
use Frootbox\Webhooks\Webhook\Webhooks;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The notes with webhooks, whose receiver answers from here: nothing
 * goes to the network.
 */
abstract class WebhooksTestCase extends NotesTestCase
{
    public const string ROOT = __DIR__ . '/..';

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string}> */
    protected array $received = [];

    /** The status the receiver answers with, null for no answer at all. */
    protected ?int $answer = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->received = [];
        $this->answer = 200;
        $this->container->set(Sender::class, new Sender($this->get(Config::class), new MockHttpClient($this->receive(...))));
    }

    public static function package(): ExtensionPackage
    {
        return ExtensionPackage::fromManifest('frootbox/webhooks', (string) realpath(self::ROOT), psr4: ['Frootbox\Webhooks\\' => ['src/'], 'Frootbox\Webhooks\Migrations\\' => ['migrations/']]);
    }

    protected function extensions(): Extensions
    {
        return new Extensions([self::package()], 'webhooks');
    }

    protected function tables(): array
    {
        return [Deliveries::TABLE, Webhooks::TABLE];
    }

    protected function work(): void
    {
        $this->get(Worker::class)->run(5);
    }

    /**
     * A value of the JSON a request sent, by a path like "data.input.title".
     */
    protected function sent(int $request, string $path): mixed
    {
        $value = json_decode($this->received[$request]['body'] ?? 'null', true, flags: \JSON_THROW_ON_ERROR);

        foreach (explode('.', $path) as $key) {
            $value = \is_array($value) ? $value[$key] ?? null : null;
        }

        return $value;
    }

    protected function rows(string $sql): int
    {
        $count = $this->connection()->fetchOne($sql);

        return is_numeric($count) ? (int) $count : -1;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function receive(string $method, string $url, array $options): ResponseInterface
    {
        $headers = [];

        foreach (\is_array($options['headers'] ?? null) ? $options['headers'] : [] as $line) {
            if (\is_string($line) && str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        $this->received[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => \is_string($options['body'] ?? null) ? $options['body'] : ''];

        return null === $this->answer ? new MockResponse('', ['error' => 'Could not resolve host']) : new MockResponse('ok', ['http_code' => $this->answer]);
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Tests;

use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Config\Config;
use Frootbox\Core\Config\Environment;
use Frootbox\Webhooks\Delivery\Sender;
use Frootbox\Webhooks\Webhook\CreateWebhook;
use Frootbox\Webhooks\Webhook\WebhookEditor;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Live, webhooks go only to the internet.
 */
#[CoversClass(WebhookEditor::class)]
#[CoversClass(Sender::class)]
final class ProductionTest extends WebhooksTestCase
{
    /** @var list<array{string, mixed}> */
    private array $sent = [];
    protected function environment(): Environment
    {
        return Environment::Production;
    }

    public function testOnlyHttpsOnTheInternet(): void
    {
        foreach (['http://example.com/in', 'https://localhost/in', 'https://shop.localhost/in', 'https://127.0.0.1/in', 'https://10.0.0.5/in', 'https://192.168.1.10/in', 'https://[::1]/in', 'https://169.254.169.254/latest'] as $url) {
            try {
                $this->runner()->run(new CreateWebhook('X', $url, ['note.create']));
                self::fail(\sprintf('%s is accepted.', $url));
            } catch (InvalidInputException $error) {
                self::assertSame(['/url'], array_keys($error->errors), $url);
            }
        }

        self::assertSame('https://hooks.example.com/in', $this->runner()->run(new CreateWebhook('X', 'https://hooks.example.com/in', ['note.create']))->webhook->url);
    }

    public function testTheSenderGoesOnlyToTheInternetAlsoAfterDns(): void
    {
        $dns = ['hooks.example.com' => ['93.184.215.14'], 'inside.example.com' => ['93.184.215.14', '10.0.0.7'], 'gone.example.com' => []];
        $sender = new Sender($this->get(Config::class), new MockHttpClient($this->receive(...)), static fn(string $host): array => $dns[$host] ?? []);
        $secret = 'whsec_' . base64_encode('key');

        $refused = array_map(static fn(string $url): ?string => $sender->send($url, $secret, 'msg_1', '{}')->error, ['https://127.0.0.1/in', 'https://inside.example.com/in', 'https://gone.example.com/in']);
        self::assertSame(['The address 127.0.0.1 is not on the internet.', 'The name inside.example.com points to 10.0.0.7, which is not on the internet.', 'The name gone.example.com cannot be resolved.'], $refused);
        self::assertSame([], $this->sent, 'Nothing went out.');

        self::assertTrue($sender->send('https://hooks.example.com/in', $secret, 'msg_1', '{}')->succeeded());
        self::assertSame([['https://hooks.example.com/in', ['hooks.example.com' => '93.184.215.14']]], $this->sent, 'To the IP checked.');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function receive(string $method, string $url, array $options): MockResponse
    {
        $this->sent[] = [$url, $options['resolve'] ?? null];

        return new MockResponse('ok');
    }
}

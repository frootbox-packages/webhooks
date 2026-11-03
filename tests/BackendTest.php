<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Tests;

use Frootbox\Backend\Tests\Support\BackendTestCase;
use Frootbox\Core\Config\Config;
use Frootbox\Core\Extension\Extensions;
use Frootbox\Core\Queue\Worker;
use Frootbox\Webhooks\Backend\WebhookHandler;
use Frootbox\Webhooks\Backend\WebhooksHandler;
use Frootbox\Webhooks\Delivery\Deliveries;
use Frootbox\Webhooks\Delivery\Sender;
use Frootbox\Webhooks\Webhook\Webhooks;
use Frootbox\Webhooks\WebhooksExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(WebhooksHandler::class)]
#[CoversClass(WebhookHandler::class)]
#[CoversClass(WebhooksExtension::class)]
final class BackendTest extends BackendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->container->set(Sender::class, new Sender($this->get(Config::class), new MockHttpClient(new MockResponse('', ['http_code' => 410]))));
    }

    protected function extensions(): Extensions
    {
        return new Extensions([self::backend(), WebhooksTestCase::package()], 'webhooks-backend');
    }

    protected function config(): array
    {
        return ['languages' => ['de' => ['title' => 'Deutsch']]];
    }

    protected function tables(): array
    {
        return [Deliveries::TABLE, Webhooks::TABLE];
    }

    public function testAdminsSetUpWebhooksAndSeeTheirDeliveries(): void
    {
        $this->createUser('admin@example.org', ['admin']);
        $session = $this->signIn('admin@example.org');

        $html = $this->html($this->request('GET', '/cms/webhooks', session: $session));
        self::assertStringContainsString('href="/cms/webhooks" aria-current="page"', $html, 'The sidebar has the screen.');
        self::assertStringContainsString('Noch keine Webhooks', $html);

        $html = $this->html($this->request('GET', '/cms/webhooks/new', session: $session));
        self::assertStringContainsString('<code>page</code> · 0 von', $html, 'The actions of the core by area.');
        self::assertStringContainsString('name="actions[]" value="page.*"', $html, '"All" of a group.');
        self::assertStringContainsString('name="actions[]" value="page.publish"', $html);
        self::assertStringNotContainsString('value="frootbox/webhooks/', $html, 'Its own actions tell nothing.');
        self::assertStringContainsString('id="webhook-secret" hidden', $html);

        $created = $this->action('frootbox/webhooks/webhook.create', ['title' => 'Shop', 'url' => 'https://hooks.example.com/in', 'actions' => ['page.*', 'media.create']], $session);
        $data = $created['data'] ?? null;
        self::assertIsArray($data);
        self::assertIsString($data['secret'] ?? null);
        self::assertIsArray($data['webhook'] ?? null);
        $id = $data['webhook']['id'] ?? null;
        self::assertIsString($id);

        $html = $this->html($this->request('GET', '/cms/webhooks', session: $session));
        self::assertStringContainsString('>Shop</a>', $html);
        self::assertStringContainsString('Aktiv', $html);

        $html = $this->html($this->request('GET', '/cms/webhooks/' . $id, session: $session));
        self::assertStringNotContainsString($data['secret'], $html, 'The secret shows only once.');
        self::assertMatchesRegularExpression('~value="page\.\*"[^>]*checked~', $html);
        self::assertMatchesRegularExpression('~value="page\.publish"[^>]*checked~', $html, 'Covered by "All".');
        self::assertMatchesRegularExpression('~value="media\.create"[^>]*checked~', $html);
        self::assertDoesNotMatchRegularExpression('~value="media\.\*"[^>]*checked~', $html);
        self::assertStringContainsString('Noch keine Zustellungen', $html);

        $this->action('frootbox/webhooks/webhook.test', ['id' => $id], $session);
        $this->get(Worker::class)->run(5);
        $html = $this->html($this->request('GET', '/cms/webhooks/' . $id, session: $session));
        self::assertStringContainsString('<code class="fb-list__title">frootbox/webhooks/webhook.test</code>', $html);
        self::assertStringContainsString('Status 410', $html);
        self::assertStringContainsString('Gescheitert', $html);
        self::assertStringContainsString('Erneut senden', $html);

        $this->action('frootbox/webhooks/webhook.pause', ['id' => $id], $session);
        $html = $this->html($this->request('GET', '/cms/webhooks', session: $session));
        self::assertMatchesRegularExpression('~href="/cms/webhooks"[^>]*>.*?<span class="fb-nav__count"><span class="fb-visually-hidden">, </span>1<~s', $html, 'The sidebar counts the paused ones.');
        self::assertStringContainsString('Pausiert', $html);
    }

    public function testEditorsSeeNoWebhooks(): void
    {
        $this->createUser('editor@example.org', ['editor']);
        $session = $this->signIn('editor@example.org');

        self::assertStringNotContainsString('/cms/webhooks', $this->html($this->request('GET', '/cms', session: $session)));
        self::assertSame(403, $this->request('GET', '/cms/webhooks', session: $session)->getStatusCode());
        $this->action('frootbox/webhooks/webhook.create', ['title' => 'X', 'url' => 'https://example.com', 'actions' => ['page.*']], $session, 403);
    }
}

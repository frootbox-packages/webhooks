<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Tests;

use DateTimeImmutable;
use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Schedule\ScheduleRan;
use Frootbox\Core\Security\StandardWebhooks;
use Frootbox\Core\Tests\Fixtures\Notes\CreateNote;
use Frootbox\Core\Tests\Fixtures\Notes\CreateNotes;
use Frootbox\Core\Tests\Fixtures\Notes\DeleteNote;
use Frootbox\Core\Tests\Fixtures\Notes\Subscribe;
use Frootbox\Database\Values;
use Frootbox\Webhooks\Delivery\Deliveries;
use Frootbox\Webhooks\Delivery\Dispatcher;
use Frootbox\Webhooks\Delivery\ListDeliveries;
use Frootbox\Webhooks\Delivery\Payload;
use Frootbox\Webhooks\Delivery\PruneDeliveries;
use Frootbox\Webhooks\Delivery\RetryDelivery;
use Frootbox\Webhooks\Delivery\SendDelivery;
use Frootbox\Webhooks\Delivery\Sender;
use Frootbox\Webhooks\Webhook\CountPaused;
use Frootbox\Webhooks\Webhook\CreateWebhook;
use Frootbox\Webhooks\Webhook\DeleteWebhook;
use Frootbox\Webhooks\Webhook\ListActions;
use Frootbox\Webhooks\Webhook\ListWebhooks;
use Frootbox\Webhooks\Webhook\Patterns;
use Frootbox\Webhooks\Webhook\PauseWebhook;
use Frootbox\Webhooks\Webhook\ResumeWebhook;
use Frootbox\Webhooks\Webhook\RotateSecret;
use Frootbox\Webhooks\Webhook\TestWebhook;
use Frootbox\Webhooks\Webhook\UpdateWebhook;
use Frootbox\Webhooks\Webhook\WebhookEditor;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(WebhookEditor::class)]
#[CoversClass(Dispatcher::class)]
#[CoversClass(SendDelivery::class)]
#[CoversClass(Sender::class)]
#[CoversClass(Payload::class)]
#[CoversClass(Deliveries::class)]
#[CoversClass(Patterns::class)]
#[CoversClass(ListActions::class)]
#[CoversClass(PruneDeliveries::class)]
final class WebhooksTest extends WebhooksTestCase
{
    public function testSendsTheChosenActionsSigned(): void
    {
        $created = $this->runner()->run(new CreateWebhook('Shop', 'https://hooks.example.com/in', ['note.create', 'note.subscribe']));
        self::assertMatchesRegularExpression('~^whsec_~', $created->secret);
        $stored = $this->connection()->fetchOne('SELECT secret FROM frootbox_webhooks_webhooks');
        self::assertIsString($stored);
        self::assertStringNotContainsString($created->secret, $stored, 'The secret is encrypted.');
        self::assertStringNotContainsString('whsec_', json_encode($this->runner()->ask(new ListWebhooks()), \JSON_THROW_ON_ERROR), 'No query gives the secret.');

        $note = $this->runner()->run(new CreateNote('Hallo Welt'));
        $this->runner()->run(new DeleteNote($note->id));
        $this->runner()->run(new Subscribe('ada@example.org', 'geheim-123456'));
        self::assertSame([], $this->received, 'Sent in the background.');

        $this->work();

        self::assertCount(2, $this->received, 'Not for note.delete.');
        [$first, $second] = $this->received;
        self::assertSame(['POST', 'https://hooks.example.com/in'], [$first['method'], $first['url']]);
        self::assertSame('application/json', $first['headers']['content-type']);
        self::assertTrue(StandardWebhooks::verify($created->secret, $first['headers'], $first['body']), 'Signed after Standard Webhooks.');

        $data = $this->sent(0, 'data');
        self::assertIsArray($data);
        self::assertSame(['action', 'entry', 'actor', 'input', 'result'], array_keys($data));
        self::assertSame(['note.create', 'note.create', 'system', 'Hallo Welt', ['id' => $note->id]], [$this->sent(0, 'type'), $this->sent(0, 'data.action'), $this->sent(0, 'data.actor.type'), $this->sent(0, 'data.input.title'), $this->sent(0, 'data.result')]);
        self::assertIsInt($this->sent(0, 'data.entry'));
        self::assertSame('***', $this->sent(1, 'data.input.password'), 'Sensitive input stays out.');

        $deliveries = $this->runner()->ask(new ListDeliveries($created->webhook->id));
        self::assertSame(['note.subscribe', 'note.create'], array_map(static fn($delivery): string => $delivery->action, $deliveries), 'Newest first.');
        self::assertSame(['delivered', 1, 200], [$deliveries[1]->status, $deliveries[1]->attempts, $deliveries[1]->responseCode]);
        self::assertSame($deliveries[1]->id, $first['headers']['webhook-id']);
        self::assertSame('delivered', $this->runner()->ask(new ListWebhooks())[0]->lastDelivery?->status);
    }

    public function testNestedActionsAndPatterns(): void
    {
        $this->runner()->run(new CreateWebhook('Alle Notizen', 'https://hooks.example.com/all', ['note.*']));
        $this->runner()->run(new CreateNotes(['Eins', 'Zwei']));
        $this->work();

        $types = array_map(fn(int $request): mixed => $this->sent($request, 'type'), array_keys($this->received));
        sort($types);
        self::assertSame(['note.create', 'note.create', 'note.create-many'], $types, 'The inner actions as well.');

        self::assertTrue(Patterns::covers(['page.*'], 'page.publish'));
        self::assertFalse(Patterns::covers(['page.*'], 'pages.publish'));
        self::assertTrue(Patterns::covers(['acme/shop/*'], 'acme/shop/order.create'));
        self::assertFalse(Patterns::covers(['acme/shop/*'], 'acme/shop-extra/order.create'));
        self::assertTrue(Patterns::covers(['*'], 'acme/shop/order.create'));
        self::assertSame(['page', 'acme/shop', 'page.*', 'acme/shop/*'], [Patterns::group('page.publish'), Patterns::group('acme/shop/order.create'), Patterns::all('page'), Patterns::all('acme/shop')]);
        self::assertSame([true, true, true, false, false], array_map(Patterns::valid(...), ['page.publish', 'page.*', 'acme/shop/*', 'page*', 'Page.publish']));
    }

    public function testChecksAddressAndActions(): void
    {
        self::assertSame(['/actions/0', '/actions/1', '/actions/2'], $this->errors(new CreateWebhook('X', 'http://localhost:8080/in', ['note.nothing', 'Page.*', 'frootbox/webhooks/webhook.create'])), 'In development http and localhost work.');
        self::assertSame(['/url', '/actions'], $this->errors(new CreateWebhook('X', 'ftp://example.com', [])));
        self::assertSame(['/url'], $this->errors(new CreateWebhook('X', 'https://user:pass@example.com/in', ['note.create'])));
        self::assertSame(['/actions/0'], $this->errors(new CreateWebhook('X', 'https://example.com/in', ['note.list'])), 'Queries tell nothing.');

        $id = $this->runner()->run(new CreateWebhook('X', 'https://example.com/in', ['note.create', 'note.create']))->webhook->id;
        self::assertSame(['note.create'], $this->runner()->ask(new ListWebhooks())[0]->actions, 'Each once.');
        self::assertSame(['note.*'], $this->runner()->run(new UpdateWebhook($id, 'Y', 'https://example.com/other', ['note.*']))->actions);
    }

    public function testRetriesAndPausesAfterFiveDaysWithoutSuccess(): void
    {
        $webhook = $this->runner()->run(new CreateWebhook('Shop', 'https://hooks.example.com/in', ['note.create']))->webhook;
        $this->answer = 500;
        $this->runner()->run(new CreateNote('Eins'));
        $this->work();

        $delivery = $this->runner()->ask(new ListDeliveries($webhook->id))[0];
        self::assertSame(['retrying', 1, 500, 'The address answered with status 500.'], [$delivery->status, $delivery->attempts, $delivery->responseCode, $delivery->error]);
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM jobs WHERE failed_at IS NULL'), 'The queue tries again later.');

        // Five days later it still fails.
        $this->connection()->executeStatement('UPDATE frootbox_webhooks_webhooks SET active_since = ?', [new DateTimeImmutable('-7 days')->format(Values::DATE_FORMAT)]);
        $this->connection()->executeStatement('UPDATE frootbox_webhooks_deliveries SET first_failed_at = ?', [new DateTimeImmutable('-6 days')->format(Values::DATE_FORMAT)]);
        $this->connection()->executeStatement('UPDATE jobs SET available_at = ?', [new DateTimeImmutable('-1 minute')->format(Values::DATE_FORMAT)]);
        $this->answer = null;
        $this->work();

        $paused = $this->runner()->ask(new ListWebhooks())[0];
        self::assertSame([true, 'failing'], [$paused->paused, $paused->pausedReason]);
        self::assertSame(1, $this->runner()->ask(new CountPaused()));
        $delivery = $this->runner()->ask(new ListDeliveries($webhook->id))[0];
        self::assertSame(['failed', 2, null], [$delivery->status, $delivery->attempts, $delivery->responseCode]);
        self::assertSame('Could not resolve host', $delivery->error);
        self::assertSame(0, $this->rows('SELECT COUNT(*) FROM jobs'), 'No further attempts.');

        $this->runner()->run(new CreateNote('Zwei'));
        self::assertCount(1, $this->runner()->ask(new ListDeliveries($webhook->id)), 'Nothing while paused.');

        // Resumed, a new failure counts from now.
        $this->runner()->run(new ResumeWebhook($webhook->id));
        $this->runner()->run(new CreateNote('Drei'));
        $this->work();
        self::assertFalse($this->runner()->ask(new ListWebhooks())[0]->paused);
        self::assertSame('retrying', $this->runner()->ask(new ListDeliveries($webhook->id))[0]->status);

        $this->answer = 200;
        $this->connection()->executeStatement('UPDATE jobs SET available_at = ?', [new DateTimeImmutable('-1 minute')->format(Values::DATE_FORMAT)]);
        $this->work();
        self::assertSame(['delivered', 2], [$this->runner()->ask(new ListDeliveries($webhook->id))[0]->status, $this->runner()->ask(new ListDeliveries($webhook->id))[0]->attempts]);
    }

    public function testTestRetryRotateAndDelete(): void
    {
        $created = $this->runner()->run(new CreateWebhook('Shop', 'https://hooks.example.com/in', ['note.create']));
        $id = $created->webhook->id;
        $this->runner()->run(new PauseWebhook($id));
        self::assertSame('manual', $this->runner()->ask(new ListWebhooks())[0]->pausedReason);

        $this->answer = 503;
        $test = $this->runner()->run(new TestWebhook($id));
        $this->work();
        self::assertCount(1, $this->received, 'A test also while paused.');
        self::assertSame(['frootbox/webhooks/webhook.test', ['id' => $id]], [$this->sent(0, 'type'), $this->sent(0, 'data.input')]);
        self::assertSame(['failed', 1], [$this->runner()->ask(new ListDeliveries($id))[0]->status, $this->runner()->ask(new ListDeliveries($id))[0]->attempts], 'A test tries once.');

        $this->runner()->run(new ResumeWebhook($id));
        $this->answer = 200;
        self::assertSame('pending', $this->runner()->run(new RetryDelivery($test->id))->status);
        $this->work();
        self::assertSame('delivered', $this->runner()->ask(new ListDeliveries($id))[0]->status);

        try {
            $this->runner()->run(new RetryDelivery($test->id));
            self::fail('A delivered one goes only once.');
        } catch (ConflictException) {
        }

        $secret = $this->runner()->run(new RotateSecret($id))['secret'];
        self::assertNotSame($created->secret, $secret);
        $this->runner()->run(new CreateNote('Neu'));
        $this->work();
        $last = $this->received[\count($this->received) - 1];
        self::assertTrue(StandardWebhooks::verify($secret, $last['headers'], $last['body']));
        self::assertFalse(StandardWebhooks::verify($created->secret, $last['headers'], $last['body']), 'The old secret no longer fits.');

        $this->runner()->run(new DeleteWebhook($id));
        self::assertSame([], $this->runner()->ask(new ListWebhooks()));
        self::assertSame(0, $this->rows('SELECT COUNT(*) FROM frootbox_webhooks_deliveries'), 'Its deliveries go too.');
    }

    public function testListsTheActionsByGroupAndPrunesOldDeliveries(): void
    {
        $groups = $this->runner()->ask(new ListActions());
        $names = array_column($groups, 'group');
        self::assertContains('note', $names);
        self::assertNotContains('frootbox/webhooks', $names, 'Its own actions tell nothing.');
        $note = array_values(array_filter($groups, static fn(array $group): bool => 'note' === $group['group']))[0];
        self::assertSame('note.*', $note['pattern']);
        self::assertContains(['name' => 'note.create', 'description' => 'Creates a note'], $note['actions']);
        self::assertNotContains('note.list', array_column($note['actions'], 'name'), 'No queries.');

        $webhook = $this->runner()->run(new CreateWebhook('Shop', 'https://hooks.example.com/in', ['note.create']))->webhook;
        $this->runner()->run(new CreateNote('Alt'));
        $this->runner()->run(new CreateNote('Neu'));
        $this->connection()->executeStatement('UPDATE frootbox_webhooks_deliveries SET created_at = ? WHERE id = ?', [new DateTimeImmutable('-31 days')->format(Values::DATE_FORMAT), $this->runner()->ask(new ListDeliveries($webhook->id))[1]->id]);

        $this->get(EventDispatcherInterface::class)->dispatch(new ScheduleRan(new DateTimeImmutable()));

        self::assertCount(1, $this->runner()->ask(new ListDeliveries($webhook->id)));
    }

    public function testKeepsSecretsOfResultsAtHome(): void
    {
        self::assertSame([5, true, ['id' => 'abc'], null, null, null], array_map(Payload::result(...), [5, true, new class {
            public string $id = 'abc';

            public string $token = 'secret';
        }, 'fbx_token', ['token' => 'fbx_token', 'accessToken' => ['id' => 4]], null]));
    }

    /**
     * @param Action<mixed> $action
     *
     * @return list<string>
     */
    private function errors(Action $action): array
    {
        try {
            $this->runner()->run($action);
        } catch (InvalidInputException $error) {
            return array_keys($error->errors);
        }

        self::fail('The input is accepted.');
    }
}

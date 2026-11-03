<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Webhook;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\CurrentContext;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Webhooks\Delivery\DeliverySummary;
use Frootbox\Webhooks\Delivery\Dispatcher;
use Frootbox\Webhooks\Delivery\Payload;

/**
 * @implements Action<DeliverySummary>
 */
#[AsAction('frootbox/webhooks/webhook.test', 'Sends a test event "frootbox/webhooks/webhook.test" to a webhook, also while it is paused', undoable: false)]
final readonly class TestWebhook implements Action
{
    public const string TYPE = 'frootbox/webhooks/webhook.test';

    public function __construct(
        #[Length(26, 26)]
        public string $id,
    ) {}

    public function handle(Webhooks $webhooks, Dispatcher $dispatcher, CurrentContext $context): DeliverySummary
    {
        $webhook = $webhooks->getById($this->id);
        $body = Payload::body(self::TYPE, null, $context->get()->actor, ['id' => $webhook->id], null);

        return DeliverySummary::of($dispatcher->deliver($webhook, self::TYPE, null, $body, test: true));
    }
}

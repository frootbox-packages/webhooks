<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Webhook;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Webhooks\Delivery\Deliveries;

/**
 * @implements Query<list<WebhookSummary>>
 */
#[AsQuery('frootbox/webhooks/webhook.list', 'Lists the webhooks with their last delivery, without secrets')]
final readonly class ListWebhooks implements Query
{
    /**
     * @return list<WebhookSummary>
     */
    public function handle(Webhooks $webhooks, Deliveries $deliveries): array
    {
        return array_map(static fn(Webhook $webhook): WebhookSummary => WebhookSummary::of($webhook, $deliveries->last($webhook->id)), $webhooks->sorted());
    }
}

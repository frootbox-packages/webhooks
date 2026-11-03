<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Webhook;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Webhooks\Delivery\Deliveries;

/**
 * @implements Query<WebhookSummary>
 */
#[AsQuery('frootbox/webhooks/webhook.show', 'Shows a webhook without its secret', permission: 'frootbox/webhooks/webhook.list')]
final readonly class ShowWebhook implements Query
{
    public function __construct(
        #[Length(26, 26)]
        public string $id,
    ) {}

    public function handle(Webhooks $webhooks, Deliveries $deliveries): WebhookSummary
    {
        return WebhookSummary::of($webhooks->getById($this->id), $deliveries->last($this->id));
    }
}

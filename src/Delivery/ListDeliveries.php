<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Delivery;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\Range;
use Frootbox\Webhooks\Webhook\Webhooks;

/**
 * @implements Query<list<DeliverySummary>>
 */
#[AsQuery('frootbox/webhooks/delivery.list', 'Lists the latest deliveries of a webhook, newest first, from the last 30 days', permission: 'frootbox/webhooks/webhook.list')]
final readonly class ListDeliveries implements Query
{
    public function __construct(
        #[Length(26, 26)]
        public string $webhook,
        #[Range(1, 200)]
        public int $limit = 50,
    ) {}

    /**
     * @return list<DeliverySummary>
     */
    public function handle(Webhooks $webhooks, Deliveries $deliveries): array
    {
        $webhooks->getById($this->webhook);

        return array_map(DeliverySummary::of(...), $deliveries->latest($this->webhook, $this->limit));
    }
}

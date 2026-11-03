<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Delivery;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Queue\Queue;

/**
 * @implements Action<DeliverySummary>
 */
#[AsAction('frootbox/webhooks/delivery.retry', 'Sends a failed or skipped delivery again, with all its attempts', undoable: false)]
final readonly class RetryDelivery implements Action
{
    public function __construct(
        #[Length(26, 26)]
        public string $id,
    ) {}

    public function handle(Deliveries $deliveries, Queue $queue): DeliverySummary
    {
        $delivery = $deliveries->getById($this->id);

        if (!\in_array($delivery->status, [DeliveryStatus::Failed, DeliveryStatus::Skipped], true)) {
            throw new ConflictException('Only a failed or skipped delivery can be sent again.');
        }

        $delivery = $deliveries->reset($delivery);
        $queue->push(new SendDelivery($delivery->id));

        return DeliverySummary::of($delivery);
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Delivery;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Frootbox\Core\Schedule\ScheduleRan;

/**
 * Removes deliveries older than 30 days (decision 0253).
 */
final readonly class PruneDeliveries
{
    public function __construct(private Deliveries $deliveries) {}

    public function __invoke(ScheduleRan $event): void
    {
        try {
            $this->deliveries->prune($event->now->modify(\sprintf('-%d days', Deliveries::KEEP_DAYS)));
        } catch (TableNotFoundException) {
            // Installed, not migrated yet.
        }
    }
}

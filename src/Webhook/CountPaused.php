<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Webhook;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Query;

/**
 * For the number next to the entry in the sidebar.
 *
 * @implements Query<int>
 */
#[AsQuery('frootbox/webhooks/webhook.paused-count', 'Counts the paused webhooks', permission: 'frootbox/webhooks/webhook.list')]
final readonly class CountPaused implements Query
{
    public function handle(Webhooks $webhooks): int
    {
        return $webhooks->paused();
    }
}

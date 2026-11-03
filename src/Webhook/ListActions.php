<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Webhook;

use Frootbox\Core\Action\AsQuery;
use Frootbox\Core\Action\Catalog;
use Frootbox\Core\Action\Query;
use Frootbox\Webhooks\Delivery\Dispatcher;

/**
 * The actions a webhook can listen to, grouped by package or area of the
 * core, each group with its pattern for all (decision 0253).
 *
 * @implements Query<list<array{group: string, pattern: string, actions: list<array{name: string, description: string}>}>>
 */
#[AsQuery('frootbox/webhooks/webhook.actions', 'Lists the actions a webhook can listen to, grouped by package', permission: 'frootbox/webhooks/webhook.list')]
final readonly class ListActions implements Query
{
    /**
     * @return list<array{group: string, pattern: string, actions: list<array{name: string, description: string}>}>
     */
    public function handle(Catalog $catalog): array
    {
        $groups = [];

        foreach ($catalog->all() as $descriptor) {
            $group = Patterns::group($descriptor->name);

            if (!$descriptor->isAction() || Dispatcher::PACKAGE === $group) {
                continue;
            }

            $groups[$group] ??= ['group' => $group, 'pattern' => Patterns::all($group), 'actions' => []];
            $groups[$group]['actions'][] = ['name' => $descriptor->name, 'description' => $descriptor->description];
        }

        // The core first, then the packages.
        uksort($groups, static fn(string $a, string $b): int => [str_contains($a, '/'), $a] <=> [str_contains($b, '/'), $b]);

        return array_values($groups);
    }
}

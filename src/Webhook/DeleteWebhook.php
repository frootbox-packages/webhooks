<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Webhook;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\Length;

/**
 * @implements Action<null>
 */
#[AsAction('frootbox/webhooks/webhook.delete', 'Removes a webhook and its deliveries', undoable: false)]
final readonly class DeleteWebhook implements Action
{
    public function __construct(
        #[Length(26, 26)]
        public string $id,
    ) {}

    public function handle(WebhookEditor $editor): null
    {
        $editor->delete($this->id);

        return null;
    }
}

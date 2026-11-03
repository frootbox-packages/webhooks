<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Webhook;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Webhooks\Delivery\Deliveries;

/**
 * @implements Action<WebhookSummary>
 */
#[AsAction('frootbox/webhooks/webhook.pause', 'Pauses a webhook: nothing is sent until it is resumed')]
final readonly class PauseWebhook implements Action
{
    public function __construct(
        #[Length(26, 26)]
        public string $id,
        #[Description('Paused because it failed for days, set by the system')]
        public bool $failing = false,
    ) {}

    public function handle(WebhookEditor $editor, Deliveries $deliveries): WebhookSummary
    {
        return WebhookSummary::of($editor->pause($this->id, $this->failing), $deliveries->last($this->id));
    }
}

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
use Frootbox\Core\Action\Schema\ListOf;
use Frootbox\Webhooks\Delivery\Deliveries;

/**
 * @implements Action<WebhookSummary>
 */
#[AsAction('frootbox/webhooks/webhook.update', 'Changes title, address and actions of a webhook')]
final readonly class UpdateWebhook implements Action
{
    /**
     * @param list<string> $actions
     */
    public function __construct(
        #[Length(26, 26)]
        public string $id,
        #[Length(1, 100)]
        public string $title,
        #[Length(1, 2000)]
        public string $url,
        #[ListOf('string')]
        #[Description('Action names and patterns, e.g. ["page.publish", "frootbox/news/*"]')]
        public array $actions,
    ) {}

    public function handle(WebhookEditor $editor, Deliveries $deliveries): WebhookSummary
    {
        return WebhookSummary::of($editor->update('frootbox/webhooks/webhook.update', $this->id, $this->title, $this->url, $this->actions), $deliveries->last($this->id));
    }
}

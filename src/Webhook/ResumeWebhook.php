<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Webhook;

use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Webhooks\Delivery\Deliveries;

/**
 * @implements Action<WebhookSummary>
 */
#[AsAction('frootbox/webhooks/webhook.resume', 'Resumes a paused webhook for the actions from now on')]
final readonly class ResumeWebhook implements Action
{
    public function __construct(
        #[Length(26, 26)]
        public string $id,
    ) {}

    public function handle(WebhookEditor $editor, Deliveries $deliveries): WebhookSummary
    {
        return WebhookSummary::of($editor->resume($this->id), $deliveries->last($this->id));
    }
}

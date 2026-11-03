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

/**
 * @implements Action<CreatedWebhook>
 */
#[AsAction('frootbox/webhooks/webhook.create', 'Sets up a webhook: an address told about the chosen actions; returns its secret once', undoable: false)]
final readonly class CreateWebhook implements Action
{
    /**
     * @param list<string> $actions
     */
    public function __construct(
        #[Length(1, 100)]
        #[Description('What it is for, e.g. "Shop sync"')]
        public string $title,
        #[Length(1, 2000)]
        #[Description('The address, e.g. "https://example.com/hooks/frootbox"')]
        public string $url,
        #[ListOf('string')]
        #[Description('Action names and patterns, e.g. ["page.publish", "frootbox/news/*"]')]
        public array $actions,
    ) {}

    public function handle(WebhookEditor $editor): CreatedWebhook
    {
        [$webhook, $secret] = $editor->create('frootbox/webhooks/webhook.create', $this->title, $this->url, $this->actions);

        return new CreatedWebhook(WebhookSummary::of($webhook, null), $secret);
    }
}

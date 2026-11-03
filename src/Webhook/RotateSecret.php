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
 * @implements Action<array{secret: string}>
 */
#[AsAction('frootbox/webhooks/webhook.rotate-secret', 'Gives a webhook a new secret and returns it once; the old one stops working', undoable: false)]
final readonly class RotateSecret implements Action
{
    public function __construct(
        #[Length(26, 26)]
        public string $id,
    ) {}

    /**
     * @return array{secret: string}
     */
    public function handle(WebhookEditor $editor): array
    {
        return ['secret' => $editor->rotate($this->id)];
    }
}

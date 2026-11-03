<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Webhook;

/**
 * A new webhook with its secret, which is shown only this once.
 */
final readonly class CreatedWebhook
{
    public function __construct(
        public WebhookSummary $webhook,
        public string $secret,
    ) {}
}

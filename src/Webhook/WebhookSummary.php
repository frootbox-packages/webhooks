<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Webhook;

use DateTimeImmutable;
use Frootbox\Webhooks\Delivery\Delivery;
use Frootbox\Webhooks\Delivery\DeliverySummary;

/**
 * A webhook without its secret.
 */
final readonly class WebhookSummary
{
    /**
     * @param list<string> $actions
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public array $actions,
        public bool $paused,
        /** "manual" or "failing" */
        public ?string $pausedReason,
        public ?DateTimeImmutable $pausedAt,
        public ?DeliverySummary $lastDelivery,
    ) {}

    public static function of(Webhook $webhook, ?Delivery $last): self
    {
        return new self($webhook->id, $webhook->title, $webhook->url, $webhook->actions(), $webhook->paused(), $webhook->pausedReason, $webhook->pausedAt, null === $last ? null : DeliverySummary::of($last));
    }
}

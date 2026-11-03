<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Delivery;

use DateTimeImmutable;

/**
 * A delivery as the backend and the API show it.
 */
final readonly class DeliverySummary
{
    public function __construct(
        public string $id,
        public string $webhookId,
        public string $action,
        public ?int $entryId,
        public string $status,
        public int $attempts,
        public ?int $responseCode,
        public ?int $durationMs,
        public ?string $error,
        public string $body,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $attemptedAt,
        public ?DateTimeImmutable $deliveredAt,
    ) {}

    public static function of(Delivery $delivery): self
    {
        return new self($delivery->id, $delivery->webhookId, $delivery->action, $delivery->entryId, $delivery->status->value, $delivery->attempts, $delivery->responseCode, $delivery->durationMs, $delivery->error, $delivery->body, $delivery->createdAt, $delivery->attemptedAt, $delivery->deliveredAt);
    }
}

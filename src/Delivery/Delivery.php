<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Delivery;

use DateTimeImmutable;
use Frootbox\Database\Entity;

/**
 * One message to a webhook with its attempts (decision 0253). Its id is
 * the webhook-id, the same for every attempt.
 */
final readonly class Delivery extends Entity
{
    public function __construct(
        public string $id,
        public string $webhookId,
        public string $action,
        public ?int $entryId,
        /** The JSON sent, the same for every attempt. */
        public string $body,
        public DeliveryStatus $status,
        public int $attempts,
        public ?int $responseCode,
        public ?int $durationMs,
        public ?string $error,
        public ?DateTimeImmutable $firstFailedAt,
        public ?DateTimeImmutable $attemptedAt,
        public ?DateTimeImmutable $deliveredAt,
        public DateTimeImmutable $createdAt,
    ) {}
}

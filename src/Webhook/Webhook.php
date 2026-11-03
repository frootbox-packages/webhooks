<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Webhook;

use DateTimeImmutable;
use Frootbox\Database\Entity;

/**
 * An address told about actions (decision 0253). The secret is encrypted
 * with Secrets and never leaves through a query.
 */
final readonly class Webhook extends Entity
{
    /**
     * @param array<mixed> $actions names and patterns, e.g. ["page.publish", "frootbox/news/*"]
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public array $actions,
        public string $secret,
        public DateTimeImmutable $activeSince,
        public ?DateTimeImmutable $pausedAt = null,
        /** "manual" or "failing" */
        public ?string $pausedReason = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    /**
     * @return list<string>
     */
    public function actions(): array
    {
        return array_values(array_filter($this->actions, \is_string(...)));
    }

    public function paused(): bool
    {
        return null !== $this->pausedAt;
    }

    /**
     * Whether a pattern of the webhook covers the action.
     */
    public function covers(string $action): bool
    {
        return Patterns::covers($this->actions(), $action);
    }
}

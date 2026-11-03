<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Delivery;

/**
 * The outcome of one attempt to send.
 */
final readonly class Attempt
{
    public function __construct(
        /** null if no answer came */
        public ?int $code,
        public int $durationMs,
        /** null on success */
        public ?string $error,
    ) {}

    public function succeeded(): bool
    {
        return null === $this->error;
    }
}

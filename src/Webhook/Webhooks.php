<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Webhook;

use Frootbox\Database\Repository;

/**
 * @extends Repository<Webhook>
 */
final class Webhooks extends Repository
{
    public const string TABLE = 'frootbox_webhooks_webhooks';

    /**
     * @return list<Webhook>
     */
    public function sorted(): array
    {
        return $this->fetch(['order' => ['title', 'id']])->all();
    }

    /**
     * @return list<Webhook>
     */
    public function active(): array
    {
        return $this->fetch(['where' => ['pausedAt' => null]])->all();
    }

    public function paused(): int
    {
        return $this->count(['pausedAt !=' => null]);
    }

    protected function table(): string
    {
        return self::TABLE;
    }

    protected function entity(): string
    {
        return Webhook::class;
    }
}

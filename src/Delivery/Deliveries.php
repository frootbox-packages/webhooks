<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Delivery;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Frootbox\Core\Action\Journal;
use Frootbox\Database\DirectWriter;
use Frootbox\Database\Repository;
use Frootbox\Database\Values;

/**
 * The deliveries of the webhooks (decision 0253). Bookkeeping like the
 * queue: written directly, inside the transaction of a running action,
 * but not recorded for undo.
 *
 * @extends Repository<Delivery>
 */
final class Deliveries extends Repository
{
    public const string TABLE = 'frootbox_webhooks_deliveries';

    /** Attempts of one delivery, the default of the queue. */
    public const int ATTEMPTS = 8;

    /** Deliveries are removed after this many days. */
    public const int KEEP_DAYS = 30;

    public function __construct(
        Connection $connection,
        private readonly Journal $journal,
    ) {
        parent::__construct($connection, new DirectWriter($connection));
    }

    public function record(string $webhook, string $action, ?int $entry, string $body): Delivery
    {
        return $this->journal->trusted(fn(): Delivery => $this->insert([
            'webhookId' => $webhook,
            'action' => $action,
            'entryId' => $entry,
            'body' => $body,
            'status' => DeliveryStatus::Pending,
            'attempts' => 0,
            'createdAt' => new DateTimeImmutable(),
        ]));
    }

    public function delivered(Delivery $delivery, int $code, int $duration): Delivery
    {
        $now = new DateTimeImmutable();

        return $this->change($delivery->id, [
            'status' => DeliveryStatus::Delivered,
            'attempts' => $delivery->attempts + 1,
            'responseCode' => $code,
            'durationMs' => $duration,
            'error' => null,
            'attemptedAt' => $now,
            'deliveredAt' => $now,
        ]);
    }

    /**
     * A failed attempt; after the last one the delivery counts as failed.
     */
    public function failed(Delivery $delivery, ?int $code, int $duration, string $error): Delivery
    {
        $now = new DateTimeImmutable();
        $attempts = $delivery->attempts + 1;

        return $this->change($delivery->id, [
            'status' => $attempts >= self::ATTEMPTS ? DeliveryStatus::Failed : DeliveryStatus::Retrying,
            'attempts' => $attempts,
            'responseCode' => $code,
            'durationMs' => $duration,
            'error' => mb_substr($error, 0, 2000),
            'firstFailedAt' => $delivery->firstFailedAt ?? $now,
            'attemptedAt' => $now,
        ]);
    }

    /**
     * No more attempts, e.g. because the webhook was paused.
     */
    public function giveUp(Delivery $delivery): Delivery
    {
        return $this->change($delivery->id, ['status' => DeliveryStatus::Failed]);
    }

    public function skip(Delivery $delivery, string $reason): Delivery
    {
        return $this->change($delivery->id, ['status' => DeliveryStatus::Skipped, 'error' => $reason, 'attemptedAt' => new DateTimeImmutable()]);
    }

    /**
     * Back in the queue with fresh attempts.
     */
    public function reset(Delivery $delivery): Delivery
    {
        return $this->change($delivery->id, ['status' => DeliveryStatus::Pending, 'attempts' => 0, 'error' => null]);
    }

    /**
     * The first failure of a webhook since its last success or since it is
     * active, null if it has not failed since.
     */
    public function failingSince(string $webhook, DateTimeImmutable $activeSince): ?DateTimeImmutable
    {
        $success = $this->fetchOne(['where' => ['webhookId' => $webhook, 'status' => DeliveryStatus::Delivered->value], 'order' => ['deliveredAt desc']]);
        $since = max($activeSince, $success->deliveredAt ?? $activeSince);

        return $this->fetchOne(['where' => ['webhookId' => $webhook, 'firstFailedAt >=' => $since], 'order' => ['firstFailedAt']])?->firstFailedAt;
    }

    /**
     * The latest deliveries of a webhook.
     *
     * @return list<Delivery>
     */
    public function latest(string $webhook, int $limit = 50): array
    {
        return $this->fetch(['where' => ['webhookId' => $webhook], 'order' => ['createdAt desc', 'id desc'], 'limit' => $limit])->all();
    }

    public function last(string $webhook): ?Delivery
    {
        return $this->fetchOne(['where' => ['webhookId' => $webhook], 'order' => ['createdAt desc', 'id desc']]);
    }

    public function removeOf(string $webhook): void
    {
        $this->journal->trusted(fn(): int|string => $this->connection->createQueryBuilder()
            ->delete(self::TABLE)
            ->where('webhook_id = :webhook')
            ->setParameter('webhook', $webhook)
            ->executeStatement());
    }

    /**
     * Removes the deliveries made before the time.
     */
    public function prune(DateTimeImmutable $before): int
    {
        return $this->journal->trusted(fn(): int => (int) $this->connection->createQueryBuilder()
            ->delete(self::TABLE)
            ->where('created_at < :before')
            ->setParameter('before', $before->format(Values::DATE_FORMAT))
            ->executeStatement());
    }

    protected function table(): string
    {
        return self::TABLE;
    }

    protected function entity(): string
    {
        return Delivery::class;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function change(string $id, array $values): Delivery
    {
        return $this->journal->trusted(fn(): Delivery => $this->update($id, $values));
    }
}

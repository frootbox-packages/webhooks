<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Delivery;

use DateTimeImmutable;
use Frootbox\Core\Action\Context;
use Frootbox\Core\Action\Runner;
use Frootbox\Core\Queue\AsJob;
use Frootbox\Core\Queue\Job;
use Frootbox\Core\Security\Secrets;
use Frootbox\Webhooks\Webhook\PauseWebhook;
use Frootbox\Webhooks\Webhook\WebhookEditor;
use Frootbox\Webhooks\Webhook\Webhooks;
use RuntimeException;

/**
 * Sends a delivery (decision 0253). A failed attempt throws, so the queue
 * tries again after 1, 3, 9 … minutes. After 5 days without success the
 * webhook is paused.
 */
#[AsJob('frootbox/webhooks/delivery.send', 'Sends a webhook to its address', attempts: Deliveries::ATTEMPTS)]
final readonly class SendDelivery implements Job
{
    public const int PAUSE_DAYS = 5;

    public function __construct(
        public string $delivery,
        /** One attempt, also while the webhook is paused. */
        public bool $test = false,
    ) {}

    public function handle(Deliveries $deliveries, Webhooks $webhooks, Sender $sender, Secrets $secrets, Runner $runner): void
    {
        $delivery = $deliveries->fetchById($this->delivery);

        if (null === $delivery || $delivery->status->done()) {
            return;
        }

        $webhook = $webhooks->fetchById($delivery->webhookId);

        if (null === $webhook || ($webhook->paused() && !$this->test)) {
            $deliveries->skip($delivery, null === $webhook ? 'The webhook is gone.' : 'The webhook is paused.');

            return;
        }

        $secret = $secrets->decrypt(WebhookEditor::PURPOSE, $webhook->secret);
        $attempt = null === $secret
            ? new Attempt(null, 0, 'The secret cannot be read, e.g. because APP_SECRET changed. Make a new secret.')
            : $sender->send($webhook->url, $secret, $delivery->id, $delivery->body);

        if ($attempt->succeeded()) {
            $deliveries->delivered($delivery, $attempt->code ?? 200, $attempt->durationMs);

            return;
        }

        $failed = $deliveries->failed($delivery, $attempt->code, $attempt->durationMs, $attempt->error ?? '');

        if ($this->test) {
            $deliveries->giveUp($failed);

            return;
        }
        $since = $deliveries->failingSince($webhook->id, $webhook->activeSince);

        if (null !== $since && $since <= new DateTimeImmutable()->modify(\sprintf('-%d days', self::PAUSE_DAYS))) {
            $runner->run(new PauseWebhook($webhook->id, failing: true), Context::system());
            $deliveries->giveUp($failed);

            return;
        }

        throw new RuntimeException($attempt->error ?? 'The webhook could not be sent.');
    }
}

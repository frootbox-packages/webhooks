<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Delivery;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Frootbox\Core\Action\ActionExecuted;
use Frootbox\Core\Action\ActionName;
use Frootbox\Core\Queue\Queue;
use Frootbox\Webhooks\Webhook\Webhook;
use Frootbox\Webhooks\Webhook\Webhooks;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Listens to every action and puts a delivery in the queue for each
 * active webhook that covers it (decision 0253). In a nested action the
 * delivery is part of the outer transaction and goes if that fails. The
 * actions of this module tell no webhook.
 */
final readonly class Dispatcher
{
    public const string PACKAGE = 'frootbox/webhooks';

    public function __construct(
        private Webhooks $webhooks,
        private Deliveries $deliveries,
        private Queue $queue,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ActionExecuted $event): void
    {
        $name = $event->descriptor->name;

        if (!$event->descriptor->isAction() || self::PACKAGE === ActionName::package($name)) {
            return;
        }

        try {
            $webhooks = array_filter($this->webhooks->active(), static fn(Webhook $webhook): bool => $webhook->covers($name));
        } catch (TableNotFoundException) {
            // Installed, not migrated yet.
            return;
        }

        if ([] === $webhooks) {
            return;
        }

        try {
            $body = Payload::of($event);

            foreach ($webhooks as $webhook) {
                $this->deliver($webhook, $name, $event->entryId, $body);
            }
        } catch (Throwable $error) {
            // The action is done; a webhook must not make it look failed.
            $this->logger->error('Could not put the webhooks of {action} in the queue: {message}', ['action' => $name, 'message' => $error->getMessage(), 'exception' => $error]);
        }
    }

    /**
     * @param bool $test one attempt, also while the webhook is paused
     */
    public function deliver(Webhook $webhook, string $action, ?int $entry, string $body, bool $test = false): Delivery
    {
        $delivery = $this->deliveries->record($webhook->id, $action, $entry, $body);
        $this->queue->push(new SendDelivery($delivery->id, $test));

        return $delivery;
    }
}

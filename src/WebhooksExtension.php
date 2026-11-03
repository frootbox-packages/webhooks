<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks;

use Frootbox\Backend\Screen;
use Frootbox\Core\Action\ActionExecuted;
use Frootbox\Core\Extension\Extension;
use Frootbox\Core\Extension\Registry;
use Frootbox\Core\Schedule\ScheduleRan;
use Frootbox\Webhooks\Backend\WebhookHandler;
use Frootbox\Webhooks\Backend\WebhooksHandler;
use Frootbox\Webhooks\Delivery\Dispatcher;
use Frootbox\Webhooks\Delivery\ListDeliveries;
use Frootbox\Webhooks\Delivery\PruneDeliveries;
use Frootbox\Webhooks\Delivery\RetryDelivery;
use Frootbox\Webhooks\Delivery\SendDelivery;
use Frootbox\Webhooks\Webhook\CountPaused;
use Frootbox\Webhooks\Webhook\CreateWebhook;
use Frootbox\Webhooks\Webhook\DeleteWebhook;
use Frootbox\Webhooks\Webhook\ListActions;
use Frootbox\Webhooks\Webhook\ListWebhooks;
use Frootbox\Webhooks\Webhook\PauseWebhook;
use Frootbox\Webhooks\Webhook\ResumeWebhook;
use Frootbox\Webhooks\Webhook\RotateSecret;
use Frootbox\Webhooks\Webhook\ShowWebhook;
use Frootbox\Webhooks\Webhook\TestWebhook;
use Frootbox\Webhooks\Webhook\UpdateWebhook;
use Frootbox\Webhooks\Webhook\Webhooks;

/**
 * Webhooks (decisions 0180, 0253): tell other services about actions,
 * signed after Standard Webhooks and sent through the queue.
 */
final class WebhooksExtension implements Extension
{
    public function register(Registry $registry): void
    {
        $registry->query(ListWebhooks::class);
        $registry->query(ShowWebhook::class);
        $registry->query(ListActions::class);
        $registry->query(CountPaused::class);
        $registry->query(ListDeliveries::class);
        $registry->action(CreateWebhook::class);
        $registry->action(UpdateWebhook::class);
        $registry->action(DeleteWebhook::class);
        $registry->action(PauseWebhook::class);
        $registry->action(ResumeWebhook::class);
        $registry->action(RotateSecret::class);
        $registry->action(TestWebhook::class);
        $registry->action(RetryDelivery::class);
        $registry->job(SendDelivery::class);
        $registry->sensitive(Webhooks::TABLE, 'secret');
        $registry->listen(ActionExecuted::class, Dispatcher::class);
        $registry->listen(ScheduleRan::class, PruneDeliveries::class);

        if (class_exists(Screen::class)) {
            $registry->mount('backend.path', '/cms')
                ->get('/webhooks', WebhooksHandler::class, 'frootbox.webhooks.webhooks')
                ->get('/webhooks/new', WebhookHandler::class, 'frootbox.webhooks.webhooks.new')
                ->get('/webhooks/{id:[0-9A-Za-z]{26}}', WebhookHandler::class, 'frootbox.webhooks.webhooks.show');
            $registry->add(Screen::class, new Screen('Webhooks', 'frootbox.webhooks.webhooks', Screen::ADMINISTRATION, 'external', WebhooksHandler::PERMISSION, order: 125, count: 'frootbox/webhooks/webhook.paused-count'));
        }
    }
}

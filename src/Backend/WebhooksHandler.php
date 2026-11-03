<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Backend;

use Frootbox\Backend\View\BackendView;
use Frootbox\Core\Action\Runner;
use Frootbox\Webhooks\Webhook\ListWebhooks;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The screen "Webhooks" (decision 0253): every webhook with its state and
 * its last delivery.
 */
final readonly class WebhooksHandler implements RequestHandlerInterface
{
    public const string PERMISSION = 'frootbox/webhooks/webhook.list';

    public function __construct(
        private BackendView $view,
        private Runner $runner,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $this->view->user()) {
            return $this->view->requireUser($request) ?? $this->view->redirect('/');
        }

        return $this->view->render($request, '@frootbox/webhooks/backend/webhooks.twig', [
            'webhooks' => $this->runner->ask(new ListWebhooks()),
        ]);
    }
}

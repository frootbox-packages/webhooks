<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Backend;

use Frootbox\Backend\View\BackendView;
use Frootbox\Core\Action\Runner;
use Frootbox\Webhooks\Delivery\ListDeliveries;
use Frootbox\Webhooks\Webhook\ListActions;
use Frootbox\Webhooks\Webhook\ShowWebhook;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A new webhook or one to edit, with its deliveries (decision 0253).
 */
final readonly class WebhookHandler implements RequestHandlerInterface
{
    public function __construct(
        private BackendView $view,
        private Runner $runner,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $this->view->user()) {
            return $this->view->requireUser($request) ?? $this->view->redirect('/');
        }

        $id = $request->getAttribute('id');
        $webhook = \is_string($id) ? $this->runner->ask(new ShowWebhook($id)) : null;
        $chosen = $webhook->actions ?? [];
        $groups = [];

        foreach ($this->runner->ask(new ListActions()) as $group) {
            // "All" covers the actions to come as well; without it the checked ones stay.
            $all = \in_array($group['pattern'], $chosen, true) || \in_array('*', $chosen, true);
            $actions = array_map(static fn(array $action): array => $action + ['checked' => $all || \in_array($action['name'], $chosen, true)], $group['actions']);
            $groups[] = ['all' => $all, 'actions' => $actions, 'count' => \count(array_filter($actions, static fn(array $action): bool => $action['checked']))] + $group;
        }

        return $this->view->render($request, '@frootbox/webhooks/backend/webhook.twig', [
            'webhook' => $webhook,
            'groups' => $groups,
            'deliveries' => null === $webhook ? [] : $this->runner->ask(new ListDeliveries($webhook->id)),
        ]);
    }
}

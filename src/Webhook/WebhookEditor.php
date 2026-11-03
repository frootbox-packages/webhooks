<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Webhook;

use DateTimeImmutable;
use Frootbox\Core\Action\Catalog;
use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Config\Config;
use Frootbox\Core\Config\Environment;
use Frootbox\Core\Security\Secrets;
use Frootbox\Core\Security\StandardWebhooks;
use Frootbox\Core\Support\Message;
use Frootbox\Webhooks\Delivery\Deliveries;
use Frootbox\Webhooks\Delivery\Dispatcher;
use Frootbox\Webhooks\Delivery\PublicAddress;

/**
 * Sets up webhooks (decision 0253): checks address and actions and keeps
 * the secret encrypted.
 */
final readonly class WebhookEditor
{
    /** The purpose the secrets are encrypted for. */
    public const string PURPOSE = 'frootbox/webhooks';

    public function __construct(
        private Webhooks $webhooks,
        private Deliveries $deliveries,
        private Secrets $secrets,
        private Catalog $catalog,
        private Config $config,
    ) {}

    /**
     * @param list<string> $actions
     *
     * @return array{Webhook, string} the webhook and its secret
     */
    public function create(string $action, string $title, string $url, array $actions): array
    {
        $this->check($action, $url, $actions);
        $secret = StandardWebhooks::secret();
        $webhook = $this->webhooks->insert([
            'title' => trim($title),
            'url' => trim($url),
            'actions' => self::unique($actions),
            'secret' => $this->encrypt($secret),
            'activeSince' => new DateTimeImmutable(),
        ]);

        return [$webhook, $secret];
    }

    /**
     * @param list<string> $actions
     */
    public function update(string $action, string $id, string $title, string $url, array $actions): Webhook
    {
        $this->webhooks->getById($id);
        $this->check($action, $url, $actions);

        return $this->webhooks->update($id, ['title' => trim($title), 'url' => trim($url), 'actions' => self::unique($actions)]);
    }

    public function delete(string $id): void
    {
        $this->webhooks->getById($id);
        $this->deliveries->removeOf($id);
        $this->webhooks->delete($id);
    }

    public function pause(string $id, bool $failing): Webhook
    {
        $webhook = $this->webhooks->getById($id);

        return $webhook->paused() ? $webhook : $this->webhooks->update($id, ['pausedAt' => new DateTimeImmutable(), 'pausedReason' => $failing ? 'failing' : 'manual']);
    }

    public function resume(string $id): Webhook
    {
        $webhook = $this->webhooks->getById($id);

        return $webhook->paused() ? $this->webhooks->update($id, ['pausedAt' => null, 'pausedReason' => null, 'activeSince' => new DateTimeImmutable()]) : $webhook;
    }

    /**
     * @return string the new secret
     */
    public function rotate(string $id): string
    {
        $this->webhooks->getById($id);
        $secret = StandardWebhooks::secret();
        $this->webhooks->update($id, ['secret' => $this->encrypt($secret)]);

        return $secret;
    }

    private function encrypt(string $secret): string
    {
        if (!$this->secrets->available()) {
            throw new ConflictException('Webhooks need APP_SECRET in .env to keep their secret. Add it and try again.');
        }

        return $this->secrets->encrypt(self::PURPOSE, $secret);
    }

    /**
     * @param list<string> $actions
     */
    private function check(string $action, string $url, array $actions): void
    {
        $errors = [];
        $problem = $this->address(trim($url));

        if (null !== $problem) {
            $errors['/url'] = $problem;
        }

        if ([] === $actions) {
            $errors['/actions'] = 'Choose at least one action.';
        }

        foreach ($actions as $index => $pattern) {
            if (!Patterns::valid($pattern) || (!str_ends_with($pattern, '*') && !$this->sendable($pattern))) {
                $errors['/actions/' . $index] = new Message('There is no action "{name}".', ['name' => $pattern]);
            }
        }

        if ([] !== $errors) {
            throw new InvalidInputException($action, $errors);
        }
    }

    /**
     * What is wrong with the address, null if nothing. Outside of
     * development only https and no local or private address; the Sender
     * checks the address again after DNS.
     */
    private function address(string $url): ?string
    {
        $parts = false === filter_var($url, \FILTER_VALIDATE_URL) ? false : parse_url($url);
        $development = Environment::Development === $this->config->environment;

        if (false === $parts || !\in_array($parts['scheme'] ?? '', $development ? ['https', 'http'] : ['https'], true) || '' === ($parts['host'] ?? '')) {
            return $development ? 'Enter an address with https:// or http://.' : 'Enter an address with https://.';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'An address with a user name or password is not allowed. Check the signature instead.';
        }

        if ($development) {
            return null;
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if ('localhost' === $host || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return 'The address must be reachable from the internet.';
        }

        if (false !== filter_var($host, \FILTER_VALIDATE_IP) && !PublicAddress::global($host)) {
            return 'The address must be reachable from the internet.';
        }

        return null;
    }

    private function sendable(string $name): bool
    {
        return $this->catalog->has($name) && $this->catalog->get($name)->isAction() && Dispatcher::PACKAGE !== Patterns::group($name);
    }

    /**
     * @param list<string> $actions
     *
     * @return list<string>
     */
    private static function unique(array $actions): array
    {
        return array_values(array_unique($actions));
    }
}

<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Delivery;

use DateTimeImmutable;
use DateTimeInterface;
use Frootbox\Core\Action\ActionExecuted;
use Frootbox\Core\Action\Normalizer;
use Frootbox\Core\Action\Schema\InputMapper;
use Frootbox\Core\Auth\Actor;
use stdClass;

/**
 * The JSON of a webhook after Standard Webhooks (decision 0253):
 * {"type", "timestamp", "data": {"action", "entry", "actor", "input", "result"}}.
 * Sensitive input is "***"; of the result only a number, a boolean or the
 * id of an object goes out, a whole result may hold a secret.
 */
final class Payload
{
    public static function of(ActionExecuted $event): string
    {
        $input = InputMapper::mask($event->descriptor->class, InputMapper::fromObject($event->action));

        return self::body($event->descriptor->name, $event->entryId, $event->context->actor, Normalizer::normalize($input), self::result($event->result));
    }

    public static function body(string $action, ?int $entry, Actor $actor, mixed $input, mixed $result): string
    {
        return json_encode([
            'type' => $action,
            'timestamp' => new DateTimeImmutable()->format(DateTimeInterface::ATOM),
            'data' => [
                'action' => $action,
                'entry' => $entry,
                'actor' => ['type' => $actor->type->value, 'id' => $actor->id, 'name' => $actor->name],
                'input' => [] === $input ? new stdClass() : $input,
                'result' => $result,
            ],
        ], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    /**
     * @return int|bool|array{id: int|string}|null
     */
    public static function result(mixed $result): int|bool|array|null
    {
        $plain = Normalizer::normalize($result);

        return match (true) {
            \is_int($plain), \is_bool($plain) => $plain,
            \is_array($plain) && (\is_string($plain['id'] ?? null) || \is_int($plain['id'] ?? null)) => ['id' => $plain['id']],
            default => null,
        };
    }
}

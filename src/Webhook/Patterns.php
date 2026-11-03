<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Webhooks\Webhook;

use Frootbox\Core\Action\ActionName;

/**
 * The names and patterns a webhook listens to (decision 0253): a name like
 * "page.publish", a pattern like "page.*" or "acme/shop/*", or "*".
 */
final class Patterns
{
    /**
     * @param list<string> $patterns
     */
    public static function covers(array $patterns, string $action): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $action || '*' === $pattern || (str_ends_with($pattern, '*') && str_starts_with($action, substr($pattern, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the text is a name or one of the patterns.
     */
    public static function valid(string $pattern): bool
    {
        if ('*' === $pattern) {
            return true;
        }

        if (str_ends_with($pattern, '.*')) {
            return 1 === preg_match('#^' . ActionName::SEGMENT . '$#', substr($pattern, 0, -2));
        }

        if (str_ends_with($pattern, '/*')) {
            return 1 === preg_match('#^' . ActionName::PACKAGE . '$#', substr($pattern, 0, -2));
        }

        return ActionName::isValid($pattern);
    }

    /**
     * The group of an action: its package, e.g. "acme/shop", or the area
     * of the core before the dot, e.g. "page".
     */
    public static function group(string $action): string
    {
        return ActionName::package($action) ?? explode('.', $action, 2)[0];
    }

    /**
     * The pattern for all actions of a group, e.g. "page.*" or "acme/shop/*".
     */
    public static function all(string $group): string
    {
        return str_contains($group, '/') ? $group . '/*' : $group . '.*';
    }
}

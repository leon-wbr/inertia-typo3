<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Support;

/**
 * Keys given as a string or an enum, as flash() and once props accept them:
 * a backed enum is its value, a pure enum its name.
 */
final class Key
{
    public static function from(\UnitEnum|string $key): string
    {
        return match (true) {
            $key instanceof \BackedEnum => (string)$key->value,
            $key instanceof \UnitEnum => $key->name,
            default => $key,
        };
    }
}

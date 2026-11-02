<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

/**
 * A prop that can be left out of the first load and fetched by the client
 * right after it, in a named group.
 */
interface Deferrable
{
    public function shouldDefer(): bool;

    public function group(): string;
}

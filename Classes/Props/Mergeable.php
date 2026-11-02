<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

/**
 * A prop whose value the client merges into the value it already has,
 * instead of replacing it.
 */
interface Mergeable
{
    public function shouldMerge(): bool;

    public function shouldDeepMerge(): bool;

    public function appendsAtRoot(): bool;

    public function prependsAtRoot(): bool;

    /** @return list<string> */
    public function appendsAtPaths(): array;

    /** @return list<string> */
    public function prependsAtPaths(): array;

    /** @return list<string> */
    public function matchesOn(): array;
}

<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

/**
 * A prop the client keeps once it has it: later visits leave it out while
 * the client lists it in X-Inertia-Except-Once-Props. Follows
 * Inertia\Onceable from inertia-laravel 3.x.
 */
interface Onceable
{
    public function once(bool $value = true): static;

    public function shouldResolveOnce(): bool;

    /**
     * Send the prop even if the client already has it.
     */
    public function shouldBeRefreshed(): bool;

    /**
     * The key the client remembers the prop by; null means its path.
     */
    public function getKey(): ?string;

    public function as(\BackedEnum|\UnitEnum|string $key): static;

    /**
     * Expire the prop on the client after a number of seconds, an interval
     * or at a point in time.
     */
    public function until(\DateTimeInterface|\DateInterval|int $delay): static;

    /**
     * The expiry as a Unix timestamp in milliseconds, or null.
     */
    public function expiresAt(): ?int;
}

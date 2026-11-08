<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

/**
 * The once behaviour of OnceProp, DeferProp, OptionalProp and MergeProp,
 * following inertia-laravel 3.x (Inertia\ResolvesOnce).
 */
trait ResolvesOnce
{
    protected bool $once = false;

    protected bool $refresh = false;

    /** Seconds until the prop expires on the client. */
    protected ?int $ttl = null;

    protected ?string $key = null;

    public function once(bool $value = true, ?string $as = null, \DateTimeInterface|\DateInterval|int|null $until = null): static
    {
        $this->once = $value;

        if ($as !== null) {
            $this->as($as);
        }

        if ($until !== null) {
            $this->until($until);
        }

        return $this;
    }

    public function shouldResolveOnce(): bool
    {
        return $this->once;
    }

    public function shouldBeRefreshed(): bool
    {
        return $this->refresh;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function as(\BackedEnum|\UnitEnum|string $key): static
    {
        $this->key = match (true) {
            $key instanceof \BackedEnum => (string)$key->value,
            $key instanceof \UnitEnum => $key->name,
            default => $key,
        };

        return $this;
    }

    /**
     * Sends the prop even though the client already has it.
     */
    public function fresh(bool $value = true): static
    {
        $this->refresh = $value;

        return $this;
    }

    public function until(\DateTimeInterface|\DateInterval|int $delay): static
    {
        $now = (new \DateTimeImmutable())->setTimestamp($this->now());
        $this->ttl = match (true) {
            $delay instanceof \DateTimeInterface => max(0, $delay->getTimestamp() - $now->getTimestamp()),
            $delay instanceof \DateInterval => max(0, $now->add($delay)->getTimestamp() - $now->getTimestamp()),
            default => $delay,
        };

        return $this;
    }

    public function expiresAt(): ?int
    {
        if ($this->ttl === null) {
            return null;
        }

        return ($this->now() + $this->ttl) * 1000;
    }

    /**
     * The current Unix time; the one clock the expiry is computed with.
     */
    protected function now(): int
    {
        return time();
    }
}

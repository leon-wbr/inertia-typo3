<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

/**
 * Merge behaviour shared by MergeProp and DeferProp, following
 * inertia-laravel 3.x (Inertia\MergesProps).
 */
trait MergesProps
{
    protected bool $merge = false;

    protected bool $deepMerge = false;

    protected bool $append = true;

    /** @var list<string> */
    protected array $matchOn = [];

    /** @var list<string> */
    protected array $appendsAtPaths = [];

    /** @var list<string> */
    protected array $prependsAtPaths = [];

    public function merge(): static
    {
        $this->merge = true;

        return $this;
    }

    public function deepMerge(): static
    {
        $this->deepMerge = true;

        return $this->merge();
    }

    /**
     * @param string|list<string> $matchOn
     */
    public function matchOn(string|array $matchOn): static
    {
        $this->matchOn = is_string($matchOn) ? [$matchOn] : $matchOn;

        return $this;
    }

    /**
     * Append new items at the root (true), or at a nested path.
     */
    public function append(bool|string $path = true, ?string $matchOn = null): static
    {
        if (is_bool($path)) {
            $this->append = $path;
        } else {
            $this->appendsAtPaths[] = $path;
            if ($matchOn !== null) {
                $this->matchOn[] = $path . '.' . $matchOn;
            }
        }

        return $this;
    }

    /**
     * Prepend new items at the root (true), or at a nested path.
     */
    public function prepend(bool|string $path = true, ?string $matchOn = null): static
    {
        if (is_bool($path)) {
            $this->append = !$path;
        } else {
            $this->prependsAtPaths[] = $path;
            if ($matchOn !== null) {
                $this->matchOn[] = $path . '.' . $matchOn;
            }
        }

        return $this;
    }

    public function shouldMerge(): bool
    {
        return $this->merge;
    }

    public function shouldDeepMerge(): bool
    {
        return $this->deepMerge;
    }

    public function appendsAtRoot(): bool
    {
        return $this->append && $this->mergesAtRoot();
    }

    public function prependsAtRoot(): bool
    {
        return !$this->append && $this->mergesAtRoot();
    }

    /** @return list<string> */
    public function appendsAtPaths(): array
    {
        return $this->appendsAtPaths;
    }

    /** @return list<string> */
    public function prependsAtPaths(): array
    {
        return $this->prependsAtPaths;
    }

    /** @return list<string> */
    public function matchesOn(): array
    {
        return $this->matchOn;
    }

    protected function mergesAtRoot(): bool
    {
        return $this->appendsAtPaths === [] && $this->prependsAtPaths === [];
    }
}

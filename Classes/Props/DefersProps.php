<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

/**
 * Opt-in deferral for props that are sent with the page by default, such as
 * ScrollProp (Inertia\DefersProps in inertia-laravel 3.x).
 */
trait DefersProps
{
    protected bool $deferred = false;

    protected ?string $deferGroup = null;

    public function defer(?string $group = null): static
    {
        $this->deferred = true;
        $this->deferGroup = $group;

        return $this;
    }

    public function shouldDefer(): bool
    {
        return $this->deferred;
    }

    public function group(): string
    {
        return $this->deferGroup ?? 'default';
    }
}

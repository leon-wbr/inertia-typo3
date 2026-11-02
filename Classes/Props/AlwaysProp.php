<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

use Psr\Http\Message\ServerRequestInterface;

/**
 * A prop that is always included, even in partial reloads that did not ask
 * for it.
 */
final class AlwaysProp
{
    public function __construct(
        private readonly mixed $value,
    ) {}

    public function __invoke(?ServerRequestInterface $request = null): mixed
    {
        return $this->value instanceof \Closure ? ($this->value)($request) : $this->value;
    }
}

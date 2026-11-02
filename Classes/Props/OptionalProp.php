<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

use Psr\Http\Message\ServerRequestInterface;

/**
 * A prop that is only resolved when a partial reload asks for it.
 */
final class OptionalProp implements IgnoreFirstLoad
{
    /** @var callable */
    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function __invoke(?ServerRequestInterface $request = null): mixed
    {
        return ($this->callback)($request);
    }
}

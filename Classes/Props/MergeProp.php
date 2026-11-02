<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

use Psr\Http\Message\ServerRequestInterface;

/**
 * A prop whose value the client merges with the value it already has, for
 * example to append the next page of a list.
 */
final class MergeProp implements Mergeable
{
    use MergesProps;

    public function __construct(
        private readonly mixed $value,
    ) {
        $this->merge = true;
    }

    public function __invoke(?ServerRequestInterface $request = null): mixed
    {
        return $this->value instanceof \Closure ? ($this->value)($request) : $this->value;
    }
}

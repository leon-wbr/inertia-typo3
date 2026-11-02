<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

use Psr\Http\Message\ServerRequestInterface;

/**
 * A prop that is left out of the first page load and announced in
 * `deferredProps`, so the client fetches it right after rendering.
 */
final class DeferProp implements IgnoreFirstLoad, Deferrable, Mergeable, Onceable
{
    use MergesProps;
    use ResolvesOnce;

    /** @var callable */
    private $callback;

    public function __construct(
        callable $callback,
        private readonly string $group = 'default',
    ) {
        $this->callback = $callback;
    }

    public function shouldDefer(): bool
    {
        return true;
    }

    public function group(): string
    {
        return $this->group;
    }

    public function __invoke(?ServerRequestInterface $request = null): mixed
    {
        return ($this->callback)($request);
    }
}

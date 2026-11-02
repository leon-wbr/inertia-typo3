<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

use Psr\Http\Message\ServerRequestInterface;

/**
 * A prop that is resolved once and then kept by the client: later visits
 * skip it while the client still has it (see Onceable).
 */
final class OnceProp implements Onceable
{
    use ResolvesOnce;

    /** @var callable */
    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
        $this->once = true;
    }

    public function __invoke(?ServerRequestInterface $request = null): mixed
    {
        return ($this->callback)($request);
    }
}

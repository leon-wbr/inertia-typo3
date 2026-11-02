<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Unit\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A request handler that answers with a closure and remembers the request.
 */
final class RecordingHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $request = null;

    /**
     * @param \Closure(ServerRequestInterface): ResponseInterface $handle
     */
    public function __construct(private readonly \Closure $handle) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return ($this->handle)($request);
    }
}

<?php

declare(strict_types=1);

namespace LeonWbr\InertiaConformance\Middleware;

use LeonWbr\Inertia\Middleware\InertiaMiddleware as BaseInertiaMiddleware;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The adapter's middleware with shared props, as a sitepackage would
 * extend it.
 */
final class InertiaMiddleware extends BaseInertiaMiddleware
{
    public function share(ServerRequestInterface $request): array
    {
        return [
            'app' => ['name' => 'conformance'],
        ];
    }
}

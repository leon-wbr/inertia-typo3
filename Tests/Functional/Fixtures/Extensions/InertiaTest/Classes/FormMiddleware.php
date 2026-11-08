<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Functional\Fixtures\Extensions\InertiaTest\Classes;

use LeonWbr\Inertia\Service\InertiaService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST to /visible/child: a name is required. Errors and a status message
 * are flashed, and the client is redirected back.
 */
final class FormMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly InertiaService $inertia,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'POST' || $request->getUri()->getPath() !== '/visible/child') {
            return $handler->handle($request);
        }

        $body = $request->getParsedBody();
        $name = \is_array($body) ? trim((string)($body['name'] ?? '')) : '';

        if ($name === '') {
            $this->inertia->withErrors(['name' => ['Enter your name.', 'Names have letters.']]);
        } else {
            $this->inertia->flash('status', 'Thanks, ' . $name . '!');
        }

        return $this->inertia->redirect('/visible/child');
    }
}

<?php

declare(strict_types=1);

namespace LeonWbr\InertiaSitepackage\Middleware;

use LeonWbr\Inertia\Service\InertiaService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Handles the example contact form on pages with the frontend layout
 * "Contact" (layout 2), the Inertia way: validate, then redirect back.
 *
 * POST answers with 303 See Other. PUT answers with a plain 302, which the
 * Inertia middleware turns into a 303 so the client follows up with GET.
 */
final class ContactFormMiddleware implements MiddlewareInterface
{
    public const LAYOUT = 2;

    public function __construct(
        private readonly InertiaService $inertia,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        $pageInformation = $request->getAttribute('frontend.page.information');
        if (
            !in_array($method, ['POST', 'PUT'], true)
            || !$pageInformation instanceof PageInformation
            || (int)($pageInformation->getPageRecord()['layout'] ?? 0) !== self::LAYOUT
        ) {
            return $handler->handle($request);
        }

        $body = $request->getParsedBody();
        $name = trim(is_array($body) ? (string)($body['name'] ?? '') : '');
        $query = $name === '' ? ['error' => 'name'] : ['sent' => mb_substr($name, 0, 100)];
        $target = $request->getUri()->getPath() . '?' . http_build_query($query);

        return $method === 'POST'
            ? $this->inertia->redirect($target)
            : new RedirectResponse($target, 302);
    }
}

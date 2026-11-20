<?php

declare(strict_types=1);

namespace LeonWbr\InertiaConformance\Middleware;

use LeonWbr\Inertia\Service\InertiaService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;

/**
 * Endpoints of the conformance cases under /_c/: form handlers, redirects
 * and control responses. It runs inside the adapter's middleware, so every
 * response gets the protocol handling (303, 409, Vary) a real TYPO3
 * handler would get.
 */
final class CaseEndpoints implements MiddlewareInterface
{
    public const PREFIX = '/_c/';

    public function __construct(
        private readonly InertiaService $inertia,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (!str_starts_with($path, self::PREFIX)) {
            return $handler->handle($request);
        }

        $method = $request->getMethod();
        $body = $request->getParsedBody();
        $body = \is_array($body) ? $body : [];

        return match (substr($path, \strlen(self::PREFIX))) {
            // A form: the name is required. Errors (in the bag the client
            // asked for) or a flash message, then back to the form.
            'form' => $this->form($body),

            // Any verb: a plain 302, which the adapter must turn into a 303
            // for PUT, PATCH, DELETE and QUERY.
            'method' => new RedirectResponse('/dump?from=' . strtolower($method), 302),

            // A 200 without a body: the adapter redirects back.
            'empty' => new Response(),

            // A redirect to a URL with a fragment.
            'fragment' => new RedirectResponse('/dump/other#section', 302),

            // An external redirect: another origin.
            'external' => $this->inertia->location('http://127.0.0.1:8090/_c/plain', $request),

            // A page outside Inertia, for the external redirect.
            'plain' => new HtmlResponse('<!doctype html><title>Plain</title><h1 id="plain">Plain HTML page</h1>'),

            // Flash data for the next page.
            'flash' => $this->flashAndRedirect(),

            // A logout: clears the client's history on the next page.
            'logout' => $this->logout(),

            // A file upload, PUT as multipart or POST with _method=put.
            'upload' => $this->upload($request, $body),

            default => new HtmlResponse('Unknown conformance endpoint', 404),
        };
    }

    /**
     * @param array<string, mixed> $body
     */
    private function form(array $body): ResponseInterface
    {
        $name = trim((string)($body['name'] ?? ''));
        if ($name === '') {
            $this->inertia->withErrors(['name' => ['The name is required.', 'The name needs letters.']]);
        } else {
            $this->inertia->flash('saved', $name);
        }

        return $this->inertia->redirect('/form');
    }

    private function flashAndRedirect(): ResponseInterface
    {
        $this->inertia->flash('message', 'Flashed once');

        return $this->inertia->redirect('/dump');
    }

    private function logout(): ResponseInterface
    {
        $this->inertia->clearHistory();

        return $this->inertia->redirect('/dump');
    }

    /**
     * @param array<string, mixed> $body
     */
    private function upload(ServerRequestInterface $request, array $body): ResponseInterface
    {
        $file = $request->getUploadedFiles()['avatar'] ?? null;
        $this->inertia->flash('upload', [
            'method' => $request->getMethod(),
            'name' => (string)($body['name'] ?? ''),
            'file' => $file instanceof UploadedFileInterface ? $file->getClientFilename() : null,
        ]);

        return $this->inertia->redirect('/dump');
    }
}

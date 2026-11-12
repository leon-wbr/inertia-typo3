<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Middleware;

use LeonWbr\Inertia\Props\OnceProp;
use LeonWbr\Inertia\Service\InertiaService;
use LeonWbr\Inertia\Support\Header;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Server side of the Inertia protocol, after Inertia\Middleware in
 * inertia-laravel 3.x. Extend it in your sitepackage to share props
 * (share()) or to change how the asset version is computed (version()).
 */
class InertiaMiddleware implements MiddlewareInterface
{
    public const DEFAULT_MANIFEST_PATH = '_assets/vite/.vite/manifest.json';

    protected string $rootView = 'App';

    /**
     * Send every message per field instead of the first one.
     */
    protected bool $withAllErrors = false;

    public function __construct(
        protected readonly InertiaService $inertia,
        protected readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $this->parseJsonBody($request);

        $this->inertia->setRequest($request);
        $this->inertia->setVersion($this->version($request));
        $this->inertia->setRootView($this->rootView);
        $this->inertia->share([
            'errors' => $this->inertia->always(fn(ServerRequestInterface $request): object => $this->resolveValidationErrors($request)),
        ]);
        $this->inertia->share($this->share($request));
        foreach ($this->shareOnce($request) as $key => $value) {
            $this->inertia->shareOnce($key, $value);
        }

        $isInertia = $request->hasHeader(Header::INERTIA);

        // An outdated client gets a full page load before any work is done.
        if (
            $isInertia
            && $request->getMethod() === 'GET'
            && $request->getHeaderLine(Header::VERSION) !== $this->inertia->getVersion()
        ) {
            return $this->withVary($this->onVersionChange($request));
        }

        $request = $request->withAttribute('inertia', $this->inertia);

        try {
            $response = $handler->handle($request);
        } catch (PropagateResponseException $e) {
            // Extbase redirects and our own JSON responses leave page
            // rendering this way; they still need the protocol handling below.
            $response = $e->getResponse();
        }

        $response = $this->withVary($response);

        if (!$isInertia) {
            return $response;
        }

        if ($response->getStatusCode() === 200 && (string)$response->getBody() === '') {
            $response = $this->onEmptyResponse($request);
        }

        if ($response->getStatusCode() === 302 && \in_array($request->getMethod(), ['PUT', 'PATCH', 'DELETE'], true)) {
            $response = $response->withStatus(303);
        }

        if ($this->isRedirect($response) && str_contains($response->getHeaderLine('Location'), '#')) {
            $response = $this->onRedirectWithFragment($response);
        }

        return $response;
    }

    /**
     * The asset version: a hash of the Vite manifest, so a new build makes
     * outdated clients reload.
     */
    public function version(ServerRequestInterface $request): ?string
    {
        try {
            $manifestPath = (string)$this->extensionConfiguration->get('inertia', 'manifestPath');
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            // Not configured yet (a fresh install, before extension:setup):
            // use the Vite default. Anything else is a real fault.
            $manifestPath = self::DEFAULT_MANIFEST_PATH;
        }
        if ($manifestPath === '') {
            return null;
        }

        $manifestPath = str_starts_with($manifestPath, 'EXT:')
            ? GeneralUtility::getFileAbsFileName($manifestPath)
            : Environment::getPublicPath() . '/' . ltrim($manifestPath, '/');

        if ($manifestPath === '' || !is_file($manifestPath)) {
            return null;
        }

        $hash = hash_file('xxh128', $manifestPath);

        return $hash === false ? null : $hash;
    }

    /**
     * Props shared with every page. Use closures for anything that costs
     * work: they are only resolved when a page is actually rendered.
     *
     * @return array<string, mixed>
     */
    public function share(ServerRequestInterface $request): array
    {
        return [];
    }

    /**
     * Props shared once: the client keeps them across visits, and the
     * server skips them while it does.
     *
     * @return array<string, callable|OnceProp>
     */
    public function shareOnce(ServerRequestInterface $request): array
    {
        return [];
    }

    /**
     * The `errors` prop: validation errors flashed with
     * InertiaService::withErrors(), cleared once they are read. With an
     * X-Inertia-Error-Bag header, the default bag is sent under that name,
     * as inertia-laravel does.
     */
    public function resolveValidationErrors(ServerRequestInterface $request): object
    {
        $bags = array_map(
            fn(array $fields): object => (object)array_map(
                fn(array $messages): string|array => $this->withAllErrors ? $messages : (string)reset($messages),
                $fields,
            ),
            $this->inertia->pullErrors($request),
        );

        $errorBag = $request->getHeaderLine(Header::ERROR_BAG);
        if (isset($bags['default']) && $errorBag !== '') {
            return (object)[$errorBag => $bags['default']];
        }

        return $bags['default'] ?? (object)$bags;
    }

    /**
     * A 200 response without a body: send the client back where it came from.
     */
    public function onEmptyResponse(ServerRequestInterface $request): ResponseInterface
    {
        $referer = $request->getHeaderLine('Referer');

        return new RedirectResponse($referer !== '' ? $referer : '/', 303);
    }

    public function onVersionChange(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->inertia->location((string)$request->getUri(), $request);

        return $response->withHeader(Header::VERSION, $this->inertia->getVersion());
    }

    /**
     * Browsers drop the fragment of a redirect followed by XHR, so the client
     * gets the target in X-Inertia-Redirect and navigates itself.
     */
    public function onRedirectWithFragment(ResponseInterface $response): ResponseInterface
    {
        return new Response(null, 409, [Header::REDIRECT => $response->getHeaderLine('Location')]);
    }

    /**
     * Inertia sends JSON bodies. TYPO3 only parses form bodies, and for PUT,
     * PATCH and DELETE it runs parse_str() over any body, which turns JSON
     * into a meaningless array, so JSON always replaces the parsed body.
     */
    protected function parseJsonBody(ServerRequestInterface $request): ServerRequestInterface
    {
        if (!str_contains(strtolower($request->getHeaderLine('Content-Type')), 'json')) {
            return $request;
        }

        $body = (string)$request->getBody();
        if ($body === '') {
            return $request;
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $request;
        }

        return \is_array($data) ? $request->withParsedBody($data) : $request;
    }

    protected function isRedirect(ResponseInterface $response): bool
    {
        return \in_array($response->getStatusCode(), [301, 302, 303, 307, 308], true);
    }

    /**
     * PSR-7 headers are immutable and, since TYPO3 14 (#107324), repeated
     * values are sent as separate lines, so the list is merged explicitly.
     */
    protected function withVary(ResponseInterface $response): ResponseInterface
    {
        $values = [];
        foreach ($response->getHeader('Vary') as $line) {
            foreach (explode(',', $line) as $value) {
                $value = trim($value);
                if ($value !== '') {
                    $values[strtolower($value)] = $value;
                }
            }
        }
        $values[strtolower(Header::INERTIA)] = Header::INERTIA;

        return $response->withHeader('Vary', implode(', ', $values));
    }
}

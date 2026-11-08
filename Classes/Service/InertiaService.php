<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Service;

use LeonWbr\Inertia\Props\AlwaysProp;
use LeonWbr\Inertia\Props\DeferProp;
use LeonWbr\Inertia\Props\MergeProp;
use LeonWbr\Inertia\Props\OnceProp;
use LeonWbr\Inertia\Props\OptionalProp;
use LeonWbr\Inertia\Props\ProvidesScrollMetadata;
use LeonWbr\Inertia\Props\ScrollMetadata;
use LeonWbr\Inertia\Props\ScrollProp;
use LeonWbr\Inertia\Support\Header;
use LeonWbr\Inertia\Support\SessionKey;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Builds Inertia page objects and responses. One instance is shared per
 * request: the middleware configures it (version, shared props, root view)
 * and controllers call render().
 */
class InertiaService
{
    public const DEFAULT_TEMPLATE_ROOT_PATH = 'EXT:inertia/Resources/Private/Templates/';

    protected string $rootView = 'App';

    /** @var array<string, mixed> */
    protected array $sharedProps = [];

    protected ?string $version = null;

    protected bool $encryptHistory = false;

    protected bool $clearHistory = false;

    protected ?ServerRequestInterface $request = null;

    /**
     * Session data for requests without a frontend user session (CLI,
     * tests): it then only lives as long as this service.
     *
     * @var array<string, mixed>
     */
    protected array $sessionFallback = [];

    public function __construct(
        protected readonly ViewFactoryInterface $viewFactory,
    ) {}

    public function setRequest(?ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    public function isInertiaRequest(?ServerRequestInterface $request = null): bool
    {
        $request ??= $this->request;

        return $request !== null && $request->hasHeader(Header::INERTIA);
    }

    public function setRootView(string $view): void
    {
        $this->rootView = $view;
    }

    public function getRootView(): string
    {
        return $this->rootView;
    }

    /**
     * Shares props with every page. Values can be closures, which are only
     * resolved when a page is rendered (and only if the prop is requested).
     *
     * @param string|array<string, mixed> $key
     */
    public function share(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            $this->sharedProps = array_merge($this->sharedProps, $key);
        } else {
            $this->sharedProps[$key] = $value;
        }
    }

    public function getShared(?string $key = null, mixed $default = null): mixed
    {
        if ($key !== null) {
            return $this->sharedProps[$key] ?? $default;
        }

        return $this->sharedProps;
    }

    public function flushShared(): void
    {
        $this->sharedProps = [];
    }

    public function setVersion(?string $version): void
    {
        $this->version = $version;
    }

    public function getVersion(): string
    {
        return $this->version ?? '';
    }

    public function encryptHistory(bool $encrypt = true): void
    {
        $this->encryptHistory = $encrypt;
    }

    /**
     * Asks the client to clear its history state with the next page object
     * this service renders.
     */
    public function clearHistory(bool $clear = true): void
    {
        $this->clearHistory = $clear;
    }

    public function always(mixed $value): AlwaysProp
    {
        return new AlwaysProp($value);
    }

    public function optional(callable $callback): OptionalProp
    {
        return new OptionalProp($callback);
    }

    public function defer(callable $callback, string $group = 'default'): DeferProp
    {
        return new DeferProp($callback, $group);
    }

    /**
     * A prop the client keeps: later visits leave it out while the client
     * still has it. Chain ->as(), ->until() or ->fresh() to change that.
     */
    public function once(callable $callback): OnceProp
    {
        return new OnceProp($callback);
    }

    /**
     * Shares a once prop with every page.
     */
    public function shareOnce(string $key, callable $callback): OnceProp
    {
        $prop = $callback instanceof OnceProp ? $callback : new OnceProp($callback);
        $this->share($key, $prop);

        return $prop;
    }

    /**
     * A paginated prop for `<InfiniteScroll>`. See ScrollProp for the values
     * it accepts.
     *
     * @param ProvidesScrollMetadata|(callable(mixed): ProvidesScrollMetadata)|array{pageName?: string, previousPage?: int|string|null, nextPage?: int|string|null, currentPage?: int|string|null}|null $metadata
     */
    public function scroll(
        mixed $value,
        string $wrapper = 'data',
        ProvidesScrollMetadata|callable|array|null $metadata = null,
        string $pageName = ScrollMetadata::DEFAULT_PAGE_NAME,
    ): ScrollProp {
        return new ScrollProp($value, $wrapper, $metadata, $pageName);
    }

    public function merge(mixed $value): MergeProp
    {
        return new MergeProp($value);
    }

    public function deepMerge(mixed $value): MergeProp
    {
        return (new MergeProp($value))->deepMerge();
    }

    /**
     * Flashes validation errors to the session, for the page the client is
     * redirected to: they become its `errors` prop, and are cleared once a
     * page has been rendered with them. Messages can be a string or a list
     * of strings per field.
     *
     * @param array<string, string|list<string>> $errors
     */
    public function withErrors(array $errors, string $bag = 'default', ?ServerRequestInterface $request = null): static
    {
        $normalized = [];
        foreach ($errors as $field => $messages) {
            $messages = array_map('strval', (array)$messages);
            if ($messages !== []) {
                $normalized[(string)$field] = $messages;
            }
        }

        $stored = $this->getErrors($request);
        $stored[$bag] = array_merge($stored[$bag] ?? [], $normalized);
        $this->setSessionData(SessionKey::ERRORS, $stored, $request);

        return $this;
    }

    /**
     * Flashed validation errors, by error bag, without clearing them.
     *
     * @return array<string, array<string, list<string>>>
     */
    public function getErrors(?ServerRequestInterface $request = null): array
    {
        $errors = $this->getSessionData(SessionKey::ERRORS, $request);

        return is_array($errors) ? $errors : [];
    }

    /**
     * Flashed validation errors, by error bag; clears them.
     *
     * @return array<string, array<string, list<string>>>
     */
    public function pullErrors(?ServerRequestInterface $request = null): array
    {
        /** @var array<string, array<string, list<string>>> */
        return $this->pullSessionArray(SessionKey::ERRORS, $request);
    }

    /**
     * Flashes data to the session for the next page the client renders: it
     * is sent as the page object's `flash` (not as a prop), as in
     * inertia-laravel 3.x, and read with usePage().flash or router.on('flash').
     *
     * @param \BackedEnum|\UnitEnum|string|array<string, mixed> $key
     */
    public function flash(\BackedEnum|\UnitEnum|string|array $key, mixed $value = null, ?ServerRequestInterface $request = null): static
    {
        if (!is_array($key)) {
            $key = match (true) {
                $key instanceof \BackedEnum => (string)$key->value,
                $key instanceof \UnitEnum => $key->name,
                default => $key,
            };
            $key = [$key => $value];
        }

        $this->setSessionData(SessionKey::FLASH_DATA, array_merge($this->getFlashed($request), $key), $request);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFlashed(?ServerRequestInterface $request = null): array
    {
        $flash = $this->getSessionData(SessionKey::FLASH_DATA, $request);

        return is_array($flash) ? $flash : [];
    }

    /**
     * Flashed data; clears it.
     *
     * @return array<string, mixed>
     */
    public function pullFlashed(?ServerRequestInterface $request = null): array
    {
        return $this->pullSessionArray(SessionKey::FLASH_DATA, $request);
    }

    /**
     * Builds the page object for a component.
     *
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    public function page(string $component, array $props = [], ?ServerRequestInterface $request = null): array
    {
        $request ??= $this->request ?? throw new \LogicException('InertiaService has no request; call setRequest() or pass one.', 1762160001);

        [$resolvedProps, $metadata] = (new PropsResolver($request, $component))->resolve($this->sharedProps, $props);

        return array_merge(
            [
                'component' => $component,
                'props' => $resolvedProps === [] ? new \stdClass() : $resolvedProps,
                'url' => self::url($request),
                'version' => $this->getVersion(),
            ],
            $metadata,
            $this->clearHistory ? ['clearHistory' => true] : [],
            $this->encryptHistory ? ['encryptHistory' => true] : [],
            ($flash = $this->pullFlashed($request)) !== [] ? ['flash' => $flash] : [],
        );
    }

    /**
     * Renders a component: a JSON page object for Inertia requests, the root
     * view (Fluid template) with the page object for full page loads.
     *
     * @param array<string, mixed> $props
     */
    public function render(string $component, array $props = [], ?ServerRequestInterface $request = null): ResponseInterface
    {
        $request ??= $this->request ?? throw new \LogicException('InertiaService has no request; call setRequest() or pass one.', 1762160002);
        $page = $this->page($component, $props, $request);

        if ($this->isInertiaRequest($request)) {
            return new JsonResponse($page, 200, [
                'Vary' => Header::INERTIA,
                Header::INERTIA => 'true',
            ]);
        }

        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: $this->templateRootPaths($request),
            request: $request,
        ));
        $view->assignMultiple([
            'page' => $page,
            'component' => $component,
        ]);

        return new HtmlResponse($view->render($this->rootView));
    }

    /**
     * Redirects to an external URL or forces a full page load: a 409 with
     * X-Inertia-Location for Inertia requests, a normal redirect otherwise.
     */
    public function location(string|RedirectResponse $url, ?ServerRequestInterface $request = null): ResponseInterface
    {
        $target = $url instanceof RedirectResponse ? $url->getHeaderLine('Location') : $url;

        if ($this->isInertiaRequest($request)) {
            return new Response(null, 409, [Header::LOCATION => $target]);
        }

        return $url instanceof RedirectResponse ? $url : new RedirectResponse($target);
    }

    /**
     * Redirects after a form submission. 303 makes the client follow up with GET.
     */
    public function redirect(string $url, int $status = 303): ResponseInterface
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * The URL of the request, relative to the host, with its query string.
     */
    public static function url(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $path = $uri->getPath() === '' ? '/' : $uri->getPath();
        $query = $uri->getQuery();

        return $query === '' ? $path : $path . '?' . $query;
    }

    /**
     * The frontend user session works for anonymous visitors too: TYPO3
     * creates the session (and its cookie) once data is stored in it.
     */
    protected function frontendUser(?ServerRequestInterface $request = null): ?FrontendUserAuthentication
    {
        $user = ($request ?? $this->request)?->getAttribute('frontend.user');

        return $user instanceof FrontendUserAuthentication ? $user : null;
    }

    protected function getSessionData(string $key, ?ServerRequestInterface $request = null): mixed
    {
        $user = $this->frontendUser($request);

        return $user !== null ? $user->getSessionData($key) : ($this->sessionFallback[$key] ?? null);
    }

    /**
     * An array from the session, which is then cleared.
     *
     * @return array<string, mixed>
     */
    protected function pullSessionArray(string $key, ?ServerRequestInterface $request = null): array
    {
        $value = $this->getSessionData($key, $request);
        if (!is_array($value) || $value === []) {
            return [];
        }
        $this->setSessionData($key, null, $request);

        return $value;
    }

    protected function setSessionData(string $key, mixed $value, ?ServerRequestInterface $request = null): void
    {
        $user = $this->frontendUser($request);
        if ($user !== null) {
            $user->setSessionData($key, $value);
        } elseif ($value === null) {
            unset($this->sessionFallback[$key]);
        } else {
            $this->sessionFallback[$key] = $value;
        }
    }

    /**
     * Template paths from plugin.tx_inertia.view.templateRootPaths, falling
     * back to the extension's own App.html.
     *
     * @return array<int|string, string>
     */
    protected function templateRootPaths(ServerRequestInterface $request): array
    {
        $paths = [0 => self::DEFAULT_TEMPLATE_ROOT_PATH];
        $typoScript = $request->getAttribute('frontend.typoscript');

        if ($typoScript instanceof FrontendTypoScript && $typoScript->hasSetup()) {
            $configured = $typoScript->getSetupArray()['plugin.']['tx_inertia.']['view.']['templateRootPaths.'] ?? [];
            if (is_array($configured)) {
                $paths = array_replace($paths, array_filter($configured, 'is_string'));
                ksort($paths);
            }
        }

        return $paths;
    }
}

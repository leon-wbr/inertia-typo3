<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Unit\Middleware;

use LeonWbr\Inertia\Middleware\InertiaMiddleware;
use LeonWbr\Inertia\Service\InertiaService;
use LeonWbr\Inertia\Support\Header;
use LeonWbr\Inertia\Tests\Unit\Fixtures\RecordingHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class InertiaMiddlewareTest extends UnitTestCase
{
    private InertiaService $inertia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inertia = new InertiaService($this->createStub(ViewFactoryInterface::class));
    }

    private function middleware(?string $version = null): InertiaMiddleware
    {
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn('');

        return new class ($this->inertia, $extensionConfiguration, $version) extends InertiaMiddleware {
            public function __construct(
                InertiaService $inertia,
                ExtensionConfiguration $extensionConfiguration,
                private readonly ?string $fixedVersion,
            ) {
                parent::__construct($inertia, $extensionConfiguration);
            }

            public function version(ServerRequestInterface $request): ?string
            {
                return $this->fixedVersion;
            }
        };
    }

    /**
     * @param \Closure(ServerRequestInterface): ResponseInterface $handle
     */
    private function handler(\Closure $handle): RecordingHandler
    {
        return new RecordingHandler($handle);
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $method = 'GET', array $headers = [], string $uri = 'https://example.org/page'): ServerRequest
    {
        return new ServerRequest($uri, $method, 'php://memory', $headers);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function methodsThatNeedSeeOther(): array
    {
        return ['PUT' => ['PUT'], 'PATCH' => ['PATCH'], 'DELETE' => ['DELETE'], 'QUERY' => ['QUERY']];
    }

    #[Test]
    #[DataProvider('methodsThatNeedSeeOther')]
    public function redirectsAfterPutPatchAndDeleteBecomeSeeOther(string $method): void
    {
        $response = $this->middleware()->process(
            $this->request($method, [Header::INERTIA => 'true']),
            $this->handler(static fn(): ResponseInterface => new RedirectResponse('/target', 302)),
        );

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/target', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function redirectsAfterPostKeepTheirStatus(): void
    {
        $response = $this->middleware()->process(
            $this->request('POST', [Header::INERTIA => 'true']),
            $this->handler(static fn(): ResponseInterface => new RedirectResponse('/target', 302)),
        );

        self::assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function redirectsOfNonInertiaRequestsAreLeftAlone(): void
    {
        $response = $this->middleware()->process(
            $this->request('PUT'),
            $this->handler(static fn(): ResponseInterface => new RedirectResponse('/target', 302)),
        );

        self::assertSame(302, $response->getStatusCode());
    }

    #[Test]
    public function propagatedResponsesAreHandledToo(): void
    {
        $response = $this->middleware()->process(
            $this->request('DELETE', [Header::INERTIA => 'true']),
            $this->handler(static fn(): never => throw new PropagateResponseException(new RedirectResponse('/target', 302), 1)),
        );

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(Header::INERTIA, $response->getHeaderLine('Vary'));
    }

    #[Test]
    public function outdatedAssetVersionForcesAFullReload(): void
    {
        $handler = $this->handler(static fn(): never => throw new \LogicException('must not be called', 1));
        $response = $this->middleware('new')->process(
            $this->request('GET', [Header::INERTIA => 'true', Header::VERSION => 'old'], 'https://example.org/page?x=1'),
            $handler,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('https://example.org/page?x=1', $response->getHeaderLine(Header::LOCATION));
        self::assertSame('new', $response->getHeaderLine(Header::VERSION));
    }

    #[Test]
    public function matchingAssetVersionIsHandledNormally(): void
    {
        $response = $this->middleware('v1')->process(
            $this->request('GET', [Header::INERTIA => 'true', Header::VERSION => 'v1']),
            $this->handler(static fn(): ResponseInterface => new HtmlResponse('ok')),
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function versionIsOnlyCheckedForGetRequests(): void
    {
        $response = $this->middleware('new')->process(
            $this->request('POST', [Header::INERTIA => 'true', Header::VERSION => 'old']),
            $this->handler(static fn(): ResponseInterface => new HtmlResponse('ok')),
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function varyHeaderIsAddedToExistingValues(): void
    {
        $response = $this->middleware()->process(
            $this->request(),
            $this->handler(static fn(): ResponseInterface => (new HtmlResponse('ok'))
                ->withHeader('Vary', 'Accept-Encoding')
                ->withAddedHeader('Vary', 'Cookie, x-inertia')),
        );

        self::assertSame(['Accept-Encoding, Cookie, X-Inertia'], $response->getHeader('Vary'));
    }

    #[Test]
    public function emptyResponsesRedirectBack(): void
    {
        $response = $this->middleware()->process(
            $this->request('POST', [Header::INERTIA => 'true', 'Referer' => 'https://example.org/form']),
            $this->handler(static fn(): ResponseInterface => new Response()),
        );

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('https://example.org/form', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function responsesTheMiddlewareReplacesVaryOnInertiaToo(): void
    {
        $empty = $this->middleware()->process(
            $this->request('POST', [Header::INERTIA => 'true']),
            $this->handler(static fn(): ResponseInterface => new Response()),
        );
        $fragment = $this->middleware()->process(
            $this->request('POST', [Header::INERTIA => 'true']),
            $this->handler(static fn(): ResponseInterface => new RedirectResponse('/page#section', 302)),
        );

        self::assertSame(Header::INERTIA, $empty->getHeaderLine('Vary'));
        self::assertSame(Header::INERTIA, $fragment->getHeaderLine('Vary'));
    }

    #[Test]
    public function prefetchRequestsGetFragmentRedirectsUnchanged(): void
    {
        $response = $this->middleware()->process(
            $this->request('GET', [Header::INERTIA => 'true', Header::PURPOSE => 'prefetch']),
            $this->handler(static fn(): ResponseInterface => new RedirectResponse('/page#section', 302)),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/page#section', $response->getHeaderLine('Location'));
        self::assertSame(Header::INERTIA, $response->getHeaderLine('Vary'));
    }

    #[Test]
    public function redirectsWithAFragmentAreHandedToTheClient(): void
    {
        $response = $this->middleware()->process(
            $this->request('POST', [Header::INERTIA => 'true']),
            $this->handler(static fn(): ResponseInterface => new RedirectResponse('/page#section', 302)),
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('/page#section', $response->getHeaderLine(Header::REDIRECT));
    }

    #[Test]
    public function jsonBodiesAreParsed(): void
    {
        $body = new Stream('php://temp', 'rw');
        $body->write('{"name":"Ada"}');
        $request = $this->request('PUT', [Header::INERTIA => 'true', 'Content-Type' => 'application/json'])
            ->withBody($body)
            // What TYPO3's ServerRequestFactory makes of a JSON PUT body.
            ->withParsedBody(['{"name":"Ada"}' => '']);
        $handler = $this->handler(static fn(): ResponseInterface => new HtmlResponse('ok'));

        $this->middleware()->process($request, $handler);

        self::assertSame(['name' => 'Ada'], $handler->request?->getParsedBody());
    }

    #[Test]
    public function serviceIsConfiguredForTheRequest(): void
    {
        $handler = $this->handler(static fn(): ResponseInterface => new HtmlResponse('ok'));
        $this->middleware('v2')->process($this->request('GET', [Header::INERTIA => 'true', Header::VERSION => 'v2']), $handler);

        self::assertSame('v2', $this->inertia->getVersion());
        self::assertSame('App', $this->inertia->getRootView());
        self::assertSame($this->inertia, $handler->request?->getAttribute('inertia'));
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function sharedErrors(array $headers = []): array
    {
        $handler = $this->handler(fn(ServerRequestInterface $request): ResponseInterface => $this->inertia->render('Page', [], $request));
        $response = $this->middleware()->process($this->request('GET', [Header::INERTIA => 'true', Header::VERSION => ''] + $headers), $handler);
        $page = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $page['props']['errors'];
    }

    #[Test]
    public function errorsAreAlwaysSharedAsAnObject(): void
    {
        $handler = $this->handler(fn(ServerRequestInterface $request): ResponseInterface => $this->inertia->render('Page', [], $request));
        $response = $this->middleware()->process($this->request('GET', [Header::INERTIA => 'true', Header::VERSION => '']), $handler);

        self::assertStringContainsString('"errors":{}', (string)$response->getBody());
    }

    #[Test]
    public function errorsAreSharedWithTheFirstMessagePerFieldAndThenCleared(): void
    {
        $this->inertia->withErrors(['name' => ['Required.', 'Too short.']]);

        self::assertSame(['name' => 'Required.'], $this->sharedErrors());
        self::assertSame([], $this->sharedErrors());
    }

    #[Test]
    public function errorsAreSharedInPartialReloadsToo(): void
    {
        $this->inertia->withErrors(['name' => 'Required.']);

        self::assertSame(['name' => 'Required.'], $this->sharedErrors([
            Header::PARTIAL_COMPONENT => 'Page',
            Header::PARTIAL_ONLY => 'other',
        ]));
    }

    #[Test]
    public function theErrorBagHeaderScopesTheDefaultBag(): void
    {
        $this->inertia->withErrors(['name' => 'Required.']);

        self::assertSame(['contact' => ['name' => 'Required.']], $this->sharedErrors([Header::ERROR_BAG => 'contact']));
    }

    #[Test]
    public function namedBagsAreSentByName(): void
    {
        $this->inertia->withErrors(['email' => 'Invalid.'], 'newsletter');
        $this->inertia->withErrors(['code' => 'Wrong.'], 'login');

        self::assertSame(
            ['newsletter' => ['email' => 'Invalid.'], 'login' => ['code' => 'Wrong.']],
            $this->sharedErrors(),
        );
    }

    #[Test]
    public function oncePropsCanBeShared(): void
    {
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn('');
        $middleware = new class ($this->inertia, $extensionConfiguration) extends InertiaMiddleware {
            public function shareOnce(ServerRequestInterface $request): array
            {
                return ['countries' => static fn(): array => ['DE', 'FR']];
            }
        };
        $handler = $this->handler(fn(ServerRequestInterface $request): ResponseInterface => $this->inertia->render('Page', [], $request));

        $response = $middleware->process($this->request('GET', [Header::INERTIA => 'true', Header::VERSION => '', Header::EXCEPT_ONCE_PROPS => 'countries']), $handler);
        $page = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('countries', $page['props']);
        self::assertSame(['countries' => ['prop' => 'countries', 'expiresAt' => null]], $page['onceProps']);
    }
}

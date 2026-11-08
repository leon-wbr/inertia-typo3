<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Unit\Service;

use LeonWbr\Inertia\Service\InertiaService;
use LeonWbr\Inertia\Support\Header;
use LeonWbr\Inertia\Support\SessionKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class InertiaServiceTest extends UnitTestCase
{
    private function service(?ViewFactoryInterface $viewFactory = null): InertiaService
    {
        return new InertiaService($viewFactory ?? $this->createStub(ViewFactoryInterface::class));
    }

    private function inertiaRequest(string $uri = 'https://example.org/page'): ServerRequest
    {
        return new ServerRequest($uri, 'GET', 'php://memory', [Header::INERTIA => 'true']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function urls(): array
    {
        return [
            'path' => ['https://example.org/page', '/page'],
            'query string' => ['https://example.org/search?q=typo3&page=2', '/search?q=typo3&page=2'],
            'trailing slash' => ['https://example.org/de/', '/de/'],
            'trailing slash and query' => ['https://example.org/de/?page=2', '/de/?page=2'],
            'root' => ['https://example.org', '/'],
        ];
    }

    #[Test]
    #[DataProvider('urls')]
    public function pageUrlKeepsTheQueryString(string $uri, string $expected): void
    {
        $page = $this->service()->page('Page', [], $this->inertiaRequest($uri));

        self::assertSame($expected, $page['url']);
    }

    #[Test]
    public function pageObjectHasNoRootView(): void
    {
        $service = $this->service();
        $service->setRootView('CustomApp');
        $page = $service->page('Page', ['title' => 'Home'], $this->inertiaRequest());

        self::assertArrayNotHasKey('rootView', $page);
        self::assertSame(['component', 'props', 'url', 'version'], array_keys($page));
    }

    #[Test]
    public function pageObjectCarriesVersionAndSharedProps(): void
    {
        $service = $this->service();
        $service->setVersion('abc');
        $service->share('site', ['title' => 'Site']);
        $page = $service->page('Page', ['title' => 'Home'], $this->inertiaRequest());

        self::assertSame('abc', $page['version']);
        self::assertSame(['site' => ['title' => 'Site'], 'title' => 'Home'], $page['props']);
        self::assertSame(['site'], $page['sharedProps']);
    }

    #[Test]
    public function missingVersionIsAnEmptyString(): void
    {
        self::assertSame('', $this->service()->page('Page', [], $this->inertiaRequest())['version']);
    }

    #[Test]
    public function emptyPropsAreEncodedAsAnObject(): void
    {
        $page = $this->service()->page('Page', [], $this->inertiaRequest());

        self::assertSame('{}', json_encode($page['props']));
    }

    #[Test]
    public function historyFlagsAreOnlySentWhenSet(): void
    {
        $service = $this->service();
        $page = $service->page('Page', [], $this->inertiaRequest());
        self::assertArrayNotHasKey('encryptHistory', $page);
        self::assertArrayNotHasKey('clearHistory', $page);

        $service->encryptHistory();
        $service->clearHistory();
        $page = $service->page('Page', [], $this->inertiaRequest());
        self::assertTrue($page['encryptHistory']);
        self::assertTrue($page['clearHistory']);
    }

    #[Test]
    public function clearHistoryOnlyAppliesToTheNextPageObject(): void
    {
        $service = $this->service();
        $service->clearHistory();

        self::assertTrue($service->page('Page', [], $this->inertiaRequest())['clearHistory']);
        self::assertArrayNotHasKey('clearHistory', $service->page('Page', [], $this->inertiaRequest()));
    }

    #[Test]
    public function beginForgetsWhatThePreviousRequestConfigured(): void
    {
        $service = $this->service();
        $service->begin($this->inertiaRequest('https://example.org/first'), 'v1', 'First');
        $service->share('user', 'Ada');
        $service->encryptHistory();
        $service->clearHistory();
        $service->flash('status', 'Saved');

        $second = $this->inertiaRequest('https://example.org/second');
        $service->begin($second, 'v2', 'App');
        $page = $service->page('Page');

        self::assertSame($second, $service->getRequest());
        self::assertSame('v2', $page['version']);
        self::assertSame('App', $service->getRootView());
        self::assertSame('{}', json_encode($page['props']));
        self::assertArrayNotHasKey('encryptHistory', $page);
        self::assertArrayNotHasKey('clearHistory', $page);
        // Flash data lives in the session, which outlasts a request.
        self::assertSame(['status' => 'Saved'], $page['flash']);
    }

    #[Test]
    public function inertiaRequestsGetJson(): void
    {
        $response = $this->service()->render('Page', ['title' => 'Home'], $this->inertiaRequest('https://example.org/page?x=1'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('true', $response->getHeaderLine(Header::INERTIA));
        self::assertSame(Header::INERTIA, $response->getHeaderLine('Vary'));
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $page = json_decode((string)$response->getBody(), true);
        self::assertSame('Page', $page['component']);
        self::assertSame(['title' => 'Home'], $page['props']);
        self::assertSame('/page?x=1', $page['url']);
    }

    #[Test]
    public function fullPageLoadsRenderTheRootView(): void
    {
        $view = $this->createMock(ViewInterface::class);
        $view->expects(self::once())->method('assignMultiple')->with(self::callback(
            static fn(array $variables): bool => $variables['page']['component'] === 'Page' && $variables['component'] === 'Page',
        ))->willReturnSelf();
        $view->expects(self::once())->method('render')->with('App')->willReturn('<div id="app"></div>');

        $viewFactory = $this->createMock(ViewFactoryInterface::class);
        $viewFactory->expects(self::once())->method('create')->with(self::callback(
            static fn(ViewFactoryData $data): bool => $data->templateRootPaths === [InertiaService::DEFAULT_TEMPLATE_ROOT_PATH],
        ))->willReturn($view);

        $response = $this->service($viewFactory)->render('Page', [], new ServerRequest('https://example.org/', 'GET'));

        self::assertSame('<div id="app"></div>', (string)$response->getBody());
        self::assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function locationIsA409ForInertiaRequests(): void
    {
        $response = $this->service()->location('https://elsewhere.example/', $this->inertiaRequest());

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('https://elsewhere.example/', $response->getHeaderLine(Header::LOCATION));
    }

    #[Test]
    public function locationIsARedirectForNormalRequests(): void
    {
        $service = $this->service();
        $response = $service->location('https://elsewhere.example/', new ServerRequest('https://example.org/', 'GET'));
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://elsewhere.example/', $response->getHeaderLine('Location'));

        $redirect = new RedirectResponse('/target', 301);
        self::assertSame($redirect, $service->location($redirect, new ServerRequest('https://example.org/', 'GET')));
        self::assertSame('/target', $service->location($redirect, $this->inertiaRequest())->getHeaderLine(Header::LOCATION));
    }

    #[Test]
    public function redirectIsASeeOther(): void
    {
        $response = $this->service()->redirect('/thanks');

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/thanks', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function errorsAreKeptPerBagUntilTheyArePulled(): void
    {
        $inertia = $this->service();
        $inertia->withErrors(['name' => 'Required.', 'email' => ['Invalid.', 'Taken.']]);
        $inertia->withErrors(['code' => 'Wrong.'], 'login');

        self::assertSame(
            ['default' => ['name' => ['Required.'], 'email' => ['Invalid.', 'Taken.']], 'login' => ['code' => ['Wrong.']]],
            $inertia->pullErrors(),
        );
        self::assertSame([], $inertia->getErrors());
    }

    #[Test]
    public function errorsAndFlashLiveInTheFrontendUserSession(): void
    {
        $user = $this->createStub(FrontendUserAuthentication::class);
        /** @var \ArrayObject<string, mixed> $session */
        $session = new \ArrayObject();
        $user->method('getSessionData')->willReturnCallback(static fn(string $key): mixed => $session[$key] ?? null);
        $user->method('setSessionData')->willReturnCallback(static function (string $key, mixed $value) use ($session): void {
            if ($value === null) {
                unset($session[$key]);
            } else {
                $session[$key] = $value;
            }
        });

        $inertia = $this->service();
        $inertia->setRequest($this->inertiaRequest()->withAttribute('frontend.user', $user));
        $inertia->withErrors(['name' => 'Required.']);
        $inertia->flash('status', 'Saved');

        self::assertSame(
            [SessionKey::ERRORS => ['default' => ['name' => ['Required.']]], SessionKey::FLASH_DATA => ['status' => 'Saved']],
            $session->getArrayCopy(),
        );
    }

    #[Test]
    public function flashDataIsSentOnceWithThePageObject(): void
    {
        $inertia = $this->service();
        $inertia->flash('status', 'Saved');
        $inertia->flash(['level' => 'success']);

        $page = $inertia->page('Page', [], $this->inertiaRequest());
        self::assertSame(['status' => 'Saved', 'level' => 'success'], $page['flash']);
        self::assertArrayNotHasKey('flash', $inertia->page('Page', [], $this->inertiaRequest()));
    }
}

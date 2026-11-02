<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Functional\Frontend;

use LeonWbr\Inertia\Support\Header;
use LeonWbr\Inertia\Testing\AssertableInertia;
use LeonWbr\Inertia\Testing\InteractsWithInertia;
use LeonWbr\Inertia\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Full frontend requests through TYPO3: middleware, page plugin, Fluid
 * root view and content rendering.
 */
final class InertiaFrontendTest extends AbstractFunctionalTestCase
{
    use InteractsWithInertia;

    protected function setUp(): void
    {
        // A database page cache, so the test can see what TYPO3 cached.
        $this->configurationToUseInTestInstance['SYS']['caching']['cacheConfigurations']['pages']['backend']
            = \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class;
        parent::setUp();
    }

    /**
     * @param array<string, string> $headers
     */
    private function inertiaVisit(string $uri, array $headers = []): AssertableInertia
    {
        $response = $this->executeFrontendSubRequest($this->inertiaRequest($uri, '', $headers));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('true', $response->getHeaderLine(Header::INERTIA));

        return $this->assertInertia($response);
    }

    #[Test]
    public function firstVisitRendersThePageObjectAsJsonScript(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('https://website.local/visible'));
        $html = (string)$response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(Header::INERTIA, $response->getHeaderLine('Vary'));
        self::assertMatchesRegularExpression(
            '#<script data-page="app" type="application/json">(.+?)</script><div id="app"></div>#',
            $html,
        );

        $this->assertInertia($response)
            ->component('Default')
            ->url('/visible')
            ->version('')
            ->where('title', 'Visible')
            ->hasNoErrors();
        self::assertArrayNotHasKey('rootView', AssertableInertia::fromResponse($response)->toArray());
    }

    #[Test]
    public function inertiaVisitsGetJsonWithContentElements(): void
    {
        $page = $this->inertiaVisit('https://website.local/visible')
            ->component('Default')
            ->has('contentElements', 2)
            ->where('contentElements.0.CType', 'header')
            ->where('contentElements.1.CType', 'text')
            ->where('contentElements.1.data.header', 'Text')
            ->missing('contentElements.1.data.piFlexform');

        self::assertStringContainsString('<p>Body</p>', (string)$page->prop('contentElements.1.html'));
    }

    #[Test]
    public function urlKeepsTheQueryString(): void
    {
        $this->inertiaVisit('https://website.local/visible?q=typo3&page=2')->url('/visible?q=typo3&page=2');
    }

    #[Test]
    public function partialReloadsOnlyReturnTheRequestedPropsAndTheErrors(): void
    {
        $response = $this->executeFrontendSubRequest(
            $this->inertiaReloadRequest('https://website.local/visible', 'Default', ['title']),
        );

        self::assertSame(['errors' => [], 'title' => 'Visible'], $this->assertInertia($response)->prop());
    }

    #[Test]
    public function translatedPagesRenderTheirTranslation(): void
    {
        $this->inertiaVisit('https://website.local/de/sichtbar')
            ->url('/de/sichtbar')
            ->where('title', 'Sichtbar')
            ->has('contentElements', 1)
            ->where('contentElements.0.data.header', 'Hallo');
    }

    #[Test]
    public function outdatedVersionGetsA409(): void
    {
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('https://website.local/visible'))
                ->withHeader(Header::INERTIA, 'true')
                ->withHeader(Header::VERSION, 'outdated'),
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('https://website.local/visible', $response->getHeaderLine(Header::LOCATION));
    }

    #[Test]
    public function pagesStayInThePageCache(): void
    {
        $this->executeFrontendSubRequest(new InternalRequest('https://website.local/visible'));

        $cached = $this->getConnectionPool()->getConnectionForTable('cache_pages')->count('*', 'cache_pages', []);
        self::assertGreaterThan(0, $cached, 'config.no_cache must not be set');
    }
}

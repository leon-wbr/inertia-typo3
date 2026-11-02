<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Functional\Frontend;

use LeonWbr\Inertia\Support\Header;
use LeonWbr\Inertia\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Full frontend requests through TYPO3: middleware, page plugin, Fluid
 * root view and content rendering.
 */
final class InertiaFrontendTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        // A database page cache, so the test can see what TYPO3 cached.
        $this->configurationToUseInTestInstance['SYS']['caching']['cacheConfigurations']['pages']['backend']
            = \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class;
        parent::setUp();
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function inertiaVisit(string $uri, array $headers = []): array
    {
        $request = (new InternalRequest($uri))->withHeader(Header::INERTIA, 'true')->withHeader(Header::VERSION, '');
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('true', $response->getHeaderLine(Header::INERTIA));
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));

        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
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
        if (preg_match('#<script data-page="app" type="application/json">(.+?)</script>#', $html, $matches) !== 1) {
            self::fail('No page script in the HTML');
        }
        $page = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('Default', $page['component']);
        self::assertSame('/visible', $page['url']);
        self::assertSame('', $page['version']);
        self::assertArrayNotHasKey('rootView', $page);
        self::assertSame('Visible', $page['props']['title']);
    }

    #[Test]
    public function inertiaVisitsGetJsonWithContentElements(): void
    {
        $page = $this->inertiaVisit('https://website.local/visible');

        self::assertSame('Default', $page['component']);
        self::assertSame(['header', 'text'], array_column($page['props']['contentElements'], 'CType'));
        $text = $page['props']['contentElements'][1];
        self::assertSame('Text', $text['data']['header']);
        self::assertArrayNotHasKey('piFlexform', $text['data'], 'Only the allowed properties are sent');
        self::assertStringContainsString('<p>Body</p>', $text['html']);
    }

    #[Test]
    public function urlKeepsTheQueryString(): void
    {
        self::assertSame('/visible?q=typo3&page=2', $this->inertiaVisit('https://website.local/visible?q=typo3&page=2')['url']);
    }

    #[Test]
    public function partialReloadsOnlyReturnTheRequestedProps(): void
    {
        $page = $this->inertiaVisit('https://website.local/visible', [
            Header::PARTIAL_COMPONENT => 'Default',
            Header::PARTIAL_ONLY => 'title',
        ]);

        self::assertSame(['title' => 'Visible'], $page['props']);
    }

    #[Test]
    public function translatedPagesRenderTheirTranslation(): void
    {
        $page = $this->inertiaVisit('https://website.local/de/sichtbar');

        self::assertSame('Sichtbar', $page['props']['title']);
        self::assertSame('/de/sichtbar', $page['url']);
        self::assertSame(['Hallo'], array_column(array_column($page['props']['contentElements'], 'data'), 'header'));
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

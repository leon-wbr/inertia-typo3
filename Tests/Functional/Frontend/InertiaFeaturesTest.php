<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Functional\Frontend;

use LeonWbr\Inertia\Support\Header;
use LeonWbr\Inertia\Testing\AssertableInertia;
use LeonWbr\Inertia\Testing\InteractsWithInertia;
use LeonWbr\Inertia\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Validation errors, flash data, once and scroll props through the whole
 * frontend stack, with the fixture extension's form and props on
 * /visible/child.
 */
final class InertiaFeaturesTest extends AbstractFunctionalTestCase
{
    use InteractsWithInertia;

    private const URL = 'https://website.local/visible/child';

    protected function setUp(): void
    {
        $this->testExtensionsToLoad[] = __DIR__ . '/../Fixtures/Extensions/InertiaTest';
        parent::setUp();
    }

    /**
     * @param array<string, string> $body
     */
    private function post(array $body, string $errorBag = ''): ResponseInterface
    {
        $request = (new InternalRequest(self::URL))
            ->withMethod('POST')
            ->withHeader(Header::INERTIA, 'true')
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($body);
        if ($errorBag !== '') {
            $request = $request->withHeader(Header::ERROR_BAG, $errorBag);
        }

        $response = $this->executeFrontendSubRequest($request);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/visible/child', $response->getHeaderLine('Location'));

        return $response;
    }

    /**
     * The frontend session cookie TYPO3 set on a response.
     *
     * @return array<string, string>
     */
    private static function sessionCookie(ResponseInterface $response): array
    {
        foreach ($response->getHeader('Set-Cookie') as $cookie) {
            if (preg_match('/^(fe_typo_user)=([^;]+)/', $cookie, $matches) === 1) {
                return [$matches[1] => $matches[2]];
            }
        }
        self::fail('No frontend session cookie was set.');
    }

    /**
     * @param array<string, string> $cookies
     * @param array<string, string> $headers
     */
    private function visit(array $cookies = [], array $headers = []): AssertableInertia
    {
        $response = $this->executeFrontendSubRequest(
            $this->inertiaRequest(self::URL, '', $headers)->withCookieParams($cookies),
        );
        self::assertSame(200, $response->getStatusCode());

        return $this->assertInertia($response);
    }

    #[Test]
    public function errorsArriveAfterTheRedirectAndAreThenCleared(): void
    {
        $cookie = self::sessionCookie($this->post(['name' => '']));

        $this->visit($cookie)->component('Default')->hasError('name', 'Enter your name.');
        $this->visit($cookie)->hasNoErrors();
    }

    #[Test]
    public function errorsAreScopedToTheErrorBagOfTheRequest(): void
    {
        $cookie = self::sessionCookie($this->post(['name' => ''], 'contact'));

        $this->visit($cookie, [Header::ERROR_BAG => 'contact'])
            ->where('errors', ['contact' => ['name' => 'Enter your name.']]);
    }

    #[Test]
    public function errorsAreAnEmptyObjectWithoutASession(): void
    {
        $response = $this->executeFrontendSubRequest($this->inertiaRequest(self::URL));

        self::assertStringContainsString('"errors":{}', (string)$response->getBody());
        $this->assertInertia($response)->hasNoErrors();
    }

    #[Test]
    public function flashDataArrivesOnceWithThePageObject(): void
    {
        $cookie = self::sessionCookie($this->post(['name' => 'Ada']));

        $this->visit($cookie)->hasFlash('status', 'Thanks, Ada!')->hasNoErrors();
        $this->visit($cookie)->missingFlash('status');
    }

    #[Test]
    public function oncePropsAreSkippedWhenTheClientHasThem(): void
    {
        $this->visit()->where('plans', ['basic', 'pro'])->hasOnceProp('plans', 'plans');

        $this->visit([], [Header::EXCEPT_ONCE_PROPS => 'plans'])
            ->missing('plans')
            ->hasOnceProp('plans', 'plans');
    }

    #[Test]
    public function oncePropsAreSentOnTheFirstVisit(): void
    {
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest(self::URL))->withHeader(Header::EXCEPT_ONCE_PROPS, 'plans'),
        );

        $this->assertInertia($response)->where('plans', ['basic', 'pro']);
    }

    #[Test]
    public function scrollPropsPageThroughATypo3Paginator(): void
    {
        $this->visit()
            ->where('items.data', [1, 2, 3])
            ->hasScrollProp('items', ['pageName' => 'page', 'previousPage' => null, 'nextPage' => 2, 'currentPage' => 1])
            ->hasMergeProps(['items.data']);

        $response = $this->executeFrontendSubRequest(
            $this->inertiaReloadRequest(self::URL . '?page=4', 'Default', ['items']),
        );
        $this->assertInertia($response)
            ->where('items.data', [10])
            ->missing('plans')
            ->hasScrollProp('items', ['previousPage' => 3, 'nextPage' => null, 'currentPage' => 4]);
    }

    #[Test]
    public function scrollPropsPrependWhenScrollingUp(): void
    {
        $response = $this->executeFrontendSubRequest($this->inertiaReloadRequest(
            self::URL . '?page=2',
            'Default',
            ['items'],
            headers: [Header::INFINITE_SCROLL_MERGE_INTENT => 'prepend'],
        ));

        $this->assertInertia($response)->hasPrependProps(['items.data'])->hasMergeProps([]);
    }
}

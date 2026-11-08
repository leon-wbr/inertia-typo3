<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Unit\Testing;

use LeonWbr\Inertia\Support\Header;
use LeonWbr\Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AssertableInertiaTest extends UnitTestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function page(): array
    {
        return [
            'component' => 'Products',
            'props' => [
                'errors' => ['name' => 'Required.'],
                'products' => ['data' => [['id' => 1], ['id' => 2]]],
                'filters' => ['q' => 'typo3 & inertia'],
            ],
            'url' => '/products?q=typo3',
            'version' => 'abc',
            'deferredProps' => ['default' => ['stats', 'chart']],
            'mergeProps' => ['products.data'],
            'onceProps' => ['plans' => ['prop' => 'plans', 'expiresAt' => null]],
            'scrollProps' => ['products' => ['pageName' => 'page', 'previousPage' => null, 'nextPage' => 2, 'currentPage' => 1, 'reset' => false]],
            'flash' => ['status' => 'Saved'],
        ];
    }

    #[Test]
    public function assertsOnJsonResponses(): void
    {
        AssertableInertia::fromResponse(new JsonResponse(self::page(), 200, [Header::INERTIA => 'true']))
            ->component('Products')
            ->url('/products?q=typo3')
            ->version('abc')
            ->has('products.data', 2)
            ->hasAll(['products', 'filters.q'])
            ->where('filters.q', 'typo3 & inertia')
            ->where('products.data.0.id', static fn(mixed $id): bool => $id === 1)
            ->missing('products.meta')
            ->hasError('name', 'Required.')
            ->hasDeferredProps('default', ['chart', 'stats'])
            ->hasMergeProps(['products.data'])
            ->hasOnceProp('plans', 'plans')
            ->hasScrollProp('products', ['nextPage' => 2, 'currentPage' => 1])
            ->hasFlash('status', 'Saved')
            ->missingFlash('error');

        self::assertSame(['id' => 2], AssertableInertia::fromArray(self::page())->prop('products.data.1'));
    }

    #[Test]
    public function readsThePageScriptOfAFirstVisit(): void
    {
        $json = json_encode(self::page(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $html = '<html><body><script data-page="app" type="application/json">' . $json . '</script><div id="app"></div></body></html>';

        AssertableInertia::fromResponse(new HtmlResponse($html))
            ->component('Products')
            ->where('filters.q', 'typo3 & inertia');
    }

    #[Test]
    public function failsForAnotherComponent(): void
    {
        $this->expectException(AssertionFailedError::class);

        AssertableInertia::fromArray(self::page())->component('Other');
    }

    #[Test]
    public function failsForAMissingProp(): void
    {
        $this->expectException(AssertionFailedError::class);

        AssertableInertia::fromArray(self::page())->has('products.meta');
    }

    #[Test]
    public function aNullPropExists(): void
    {
        $page = self::page();
        $page['props']['coupon'] = null;

        AssertableInertia::fromArray($page)
            ->has('coupon')
            ->where('coupon', null)
            ->missing('coupon.code');
    }

    #[Test]
    public function whereFailsForAMissingProp(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Inertia prop [coupon] does not exist.');

        AssertableInertia::fromArray(self::page())->where('coupon', null);
    }

    #[Test]
    public function failsForAWrongCount(): void
    {
        $this->expectException(AssertionFailedError::class);

        AssertableInertia::fromArray(self::page())->has('products.data', 3);
    }

    #[Test]
    public function failsForResponsesWithoutAPage(): void
    {
        $this->expectException(AssertionFailedError::class);

        AssertableInertia::fromResponse(new HtmlResponse('<p>Not Inertia</p>'));
    }
}

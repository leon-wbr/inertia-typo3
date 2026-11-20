<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Unit\Service;

use LeonWbr\Inertia\Props\AlwaysProp;
use LeonWbr\Inertia\Props\DeferProp;
use LeonWbr\Inertia\Props\MergeProp;
use LeonWbr\Inertia\Props\OnceProp;
use LeonWbr\Inertia\Props\OptionalProp;
use LeonWbr\Inertia\Props\ScrollMetadata;
use LeonWbr\Inertia\Props\ScrollProp;
use LeonWbr\Inertia\Service\PropsResolver;
use LeonWbr\Inertia\Support\Header;
use LeonWbr\Inertia\Tests\Unit\Fixtures\FixedClockOnceProp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class PropsResolverTest extends UnitTestCase
{
    /**
     * @param array<string, string> $headers
     */
    private function request(array $headers = []): ServerRequest
    {
        return new ServerRequest('https://example.org/page', 'GET', 'php://memory', ['X-Inertia' => 'true'] + $headers);
    }

    /**
     * @param list<string> $only
     * @param list<string> $except
     */
    private function partialRequest(string $component, array $only = [], array $except = []): ServerRequest
    {
        $headers = [Header::PARTIAL_COMPONENT => $component];
        if ($only !== []) {
            $headers[Header::PARTIAL_ONLY] = implode(',', $only);
        }
        if ($except !== []) {
            $headers[Header::PARTIAL_EXCEPT] = implode(',', $except);
        }

        return $this->request($headers);
    }

    #[Test]
    public function resolvesClosuresWithTheRequest(): void
    {
        $request = $this->request();
        [$props] = (new PropsResolver($request, 'Page'))->resolve([], [
            'path' => static fn(ServerRequestInterface $request): string => $request->getUri()->getPath(),
            'plain' => 'value',
        ]);

        self::assertSame(['path' => '/page', 'plain' => 'value'], $props);
    }

    #[Test]
    public function mergesSharedPropsAndListsTheirKeys(): void
    {
        [$props, $metadata] = (new PropsResolver($this->request(), 'Page'))->resolve(
            ['site' => ['title' => 'Site'], 'user.name' => 'Ada'],
            ['title' => 'Home'],
        );

        self::assertSame(['site' => ['title' => 'Site'], 'title' => 'Home', 'user' => ['name' => 'Ada']], $props);
        self::assertSame(['site', 'user'], $metadata['sharedProps']);
    }

    #[Test]
    public function partialReloadReturnsOnlyTheRequestedProps(): void
    {
        [$props] = (new PropsResolver($this->partialRequest('Page', ['title']), 'Page'))->resolve(
            ['site' => 'shared'],
            ['title' => 'Home', 'content' => static fn(): never => throw new \RuntimeException('must not be resolved', 1)],
        );

        self::assertSame(['title' => 'Home'], $props);
    }

    #[Test]
    public function partialReloadSupportsNestedPaths(): void
    {
        [$props] = (new PropsResolver($this->partialRequest('Page', ['user.name']), 'Page'))->resolve([], [
            'user' => ['name' => 'Ada', 'email' => 'ada@example.org'],
            'other' => 'x',
        ]);

        self::assertSame(['user' => ['name' => 'Ada']], $props);
    }

    #[Test]
    public function partialReloadHonoursExcept(): void
    {
        [$props] = (new PropsResolver($this->partialRequest('Page', [], ['content']), 'Page'))->resolve([], [
            'title' => 'Home',
            'content' => 'long',
        ]);

        self::assertSame(['title' => 'Home'], $props);
    }

    #[Test]
    public function partialReloadForAnotherComponentReturnsAllProps(): void
    {
        [$props] = (new PropsResolver($this->partialRequest('Other', ['title']), 'Page'))->resolve([], [
            'title' => 'Home',
            'content' => 'text',
        ]);

        self::assertSame(['title' => 'Home', 'content' => 'text'], $props);
    }

    #[Test]
    public function alwaysPropsAreIncludedInEveryPartialReload(): void
    {
        [$props] = (new PropsResolver($this->partialRequest('Page', ['title']), 'Page'))->resolve(
            ['errors' => new AlwaysProp(static fn(): array => ['name' => 'required'])],
            ['title' => 'Home', 'content' => 'text'],
        );

        self::assertSame(['errors' => ['name' => 'required'], 'title' => 'Home'], $props);
    }

    #[Test]
    public function optionalPropsAreOnlyResolvedWhenRequested(): void
    {
        $called = 0;
        $optional = new OptionalProp(static function () use (&$called): string {
            $called++;
            return 'details';
        });

        [$initial, $initialMetadata] = (new PropsResolver($this->request(), 'Page'))->resolve([], ['details' => $optional]);
        self::assertSame([], $initial);
        self::assertArrayNotHasKey('deferredProps', $initialMetadata);
        self::assertSame(0, $called);

        [$partial] = (new PropsResolver($this->partialRequest('Page', ['details']), 'Page'))->resolve([], ['details' => $optional]);
        self::assertSame(['details' => 'details'], $partial);
        self::assertSame(1, $called);
    }

    #[Test]
    public function deferredPropsAreAnnouncedByGroupAndLoadedLater(): void
    {
        $props = [
            'stats' => new DeferProp(static fn(): array => ['count' => 3]),
            'comments' => new DeferProp(static fn(): array => [], 'sidebar'),
            'more' => new DeferProp(static fn(): array => [], 'sidebar'),
            'title' => 'Home',
        ];

        [$initial, $metadata] = (new PropsResolver($this->request(), 'Page'))->resolve([], $props);
        self::assertSame(['title' => 'Home'], $initial);
        self::assertSame(['default' => ['stats'], 'sidebar' => ['comments', 'more']], $metadata['deferredProps']);

        [$partial, $partialMetadata] = (new PropsResolver($this->partialRequest('Page', ['stats']), 'Page'))->resolve([], $props);
        self::assertSame(['stats' => ['count' => 3]], $partial);
        self::assertArrayNotHasKey('deferredProps', $partialMetadata);
    }

    #[Test]
    public function mergePropsAreListedForTheClient(): void
    {
        [$props, $metadata] = (new PropsResolver($this->request(), 'Page'))->resolve([], [
            'items' => (new MergeProp([['id' => 1]]))->matchOn('id'),
            'feed' => (new MergeProp([2]))->prepend(),
            'settings' => (new MergeProp(['a' => 1]))->deepMerge(),
            'nested' => (new MergeProp(['data' => [1]]))->append('data', 'id'),
            'deferred' => (new DeferProp(static fn(): array => [1]))->merge(),
        ]);

        self::assertSame([['id' => 1]], $props['items']);
        self::assertArrayNotHasKey('deferred', $props);
        self::assertSame(['items', 'nested.data', 'deferred'], $metadata['mergeProps']);
        self::assertSame(['feed'], $metadata['prependProps']);
        self::assertSame(['settings'], $metadata['deepMergeProps']);
        self::assertSame(['items.id', 'nested.data.id'], $metadata['matchPropsOn']);
        self::assertSame(['default' => ['deferred']], $metadata['deferredProps']);
    }

    #[Test]
    public function resetPropsAreNotMerged(): void
    {
        $request = $this->partialRequest('Page', ['items'])->withHeader(Header::RESET, 'items');
        [$props, $metadata] = (new PropsResolver($request, 'Page'))->resolve([], [
            'items' => new MergeProp([1, 2]),
        ]);

        self::assertSame(['items' => [1, 2]], $props);
        self::assertArrayNotHasKey('mergeProps', $metadata);
    }

    #[Test]
    public function aClosureMayReturnAPropType(): void
    {
        [$props, $metadata] = (new PropsResolver($this->request(), 'Page'))->resolve([], [
            'lazy' => static fn(): DeferProp => new DeferProp(static fn(): string => 'later'),
        ]);

        self::assertSame([], $props);
        self::assertSame(['default' => ['lazy']], $metadata['deferredProps']);
    }

    #[Test]
    public function jsonSerializableValuesAreSerialized(): void
    {
        $value = new class implements \JsonSerializable {
            /** @return array<string, int> */
            public function jsonSerialize(): array
            {
                return ['a' => 1];
            }
        };

        [$props] = (new PropsResolver($this->request(), 'Page'))->resolve([], ['value' => $value]);

        self::assertSame(['value' => ['a' => 1]], $props);
    }

    #[Test]
    public function oncePropsAreSentWithTheirMetadata(): void
    {
        [$props, $metadata] = (new PropsResolver($this->request(), 'Page'))->resolve([], [
            'plans' => new OnceProp(static fn(): array => ['basic']),
        ]);

        self::assertSame(['plans' => ['basic']], $props);
        self::assertSame(['plans' => ['prop' => 'plans', 'expiresAt' => null]], $metadata['onceProps']);
    }

    #[Test]
    public function oncePropsTheClientHasAreLeftOutButKeepTheirMetadata(): void
    {
        $request = $this->request([Header::EXCEPT_ONCE_PROPS => 'plans']);
        [$props, $metadata] = (new PropsResolver($request, 'Page'))->resolve([], [
            'plans' => new OnceProp(static fn(): never => throw new \RuntimeException('must not be resolved', 1)),
            'title' => 'Pricing',
        ]);

        self::assertSame(['title' => 'Pricing'], $props);
        self::assertSame(['plans' => ['prop' => 'plans', 'expiresAt' => null]], $metadata['onceProps']);
    }

    #[Test]
    public function freshOncePropsAreSentAnyway(): void
    {
        $request = $this->request([Header::EXCEPT_ONCE_PROPS => 'plans']);
        [$props] = (new PropsResolver($request, 'Page'))->resolve([], [
            'plans' => (new OnceProp(static fn(): array => ['basic']))->fresh(),
        ]);

        self::assertSame(['plans' => ['basic']], $props);
    }

    #[Test]
    public function oncePropsAreSentOnFullPageLoadsEvenIfListed(): void
    {
        $request = new ServerRequest('https://example.org/page', 'GET', 'php://memory', [Header::EXCEPT_ONCE_PROPS => 'plans']);
        [$props] = (new PropsResolver($request, 'Page'))->resolve([], [
            'plans' => new OnceProp(static fn(): array => ['basic']),
        ]);

        self::assertSame(['plans' => ['basic']], $props);
    }

    /**
     * @return array<string, array{\DateTimeInterface|\DateInterval|int}>
     */
    public static function expiries(): array
    {
        return [
            'seconds' => [90],
            'a point in time' => [new \DateTimeImmutable('@1800000090')],
            'an interval' => [new \DateInterval('PT90S')],
        ];
    }

    #[Test]
    #[DataProvider('expiries')]
    public function onceExpiryIsCountedFromNow(\DateTimeInterface|\DateInterval|int $until): void
    {
        $prop = (new FixedClockOnceProp(1_800_000_000))->until($until);

        self::assertSame(1_800_000_090_000, $prop->expiresAt());
    }

    #[Test]
    public function anExpiryInThePastIsNow(): void
    {
        $prop = (new FixedClockOnceProp(1_800_000_000))->until(new \DateTimeImmutable('@1700000000'));

        self::assertSame(1_800_000_000_000, $prop->expiresAt());
    }

    #[Test]
    public function oncePropsUseTheirCustomKeyAndExpiry(): void
    {
        [, $metadata] = (new PropsResolver($this->request(), 'Page'))->resolve([], [
            'plans' => (new FixedClockOnceProp(1_800_000_000))->as('pricing-plans')->until(60),
        ]);

        self::assertSame(['prop' => 'plans', 'expiresAt' => 1_800_000_060_000], $metadata['onceProps']['pricing-plans']);

        $request = $this->request([Header::EXCEPT_ONCE_PROPS => 'pricing-plans']);
        [$props] = (new PropsResolver($request, 'Page'))->resolve([], [
            'plans' => (new OnceProp(static fn(): array => []))->as('pricing-plans'),
        ]);
        self::assertSame([], $props);
    }

    #[Test]
    public function deferredOncePropsTheClientHasAreNotDeferredAgain(): void
    {
        $request = $this->request([Header::EXCEPT_ONCE_PROPS => 'stats']);
        [, $metadata] = (new PropsResolver($request, 'Page'))->resolve([], [
            'stats' => (new DeferProp(static fn(): array => []))->once(),
        ]);

        self::assertArrayNotHasKey('deferredProps', $metadata);
        self::assertSame(['stats' => ['prop' => 'stats', 'expiresAt' => null]], $metadata['onceProps']);
    }

    #[Test]
    public function scrollPropsFromATypo3PaginatorAppendTheirData(): void
    {
        $paginator = new ArrayPaginator(range(1, 10), 2, 3);
        [$props, $metadata] = (new PropsResolver($this->request(), 'Page'))->resolve([], [
            'items' => new ScrollProp($paginator),
        ]);

        self::assertSame(['data' => [4, 5, 6], 'meta' => ['currentPage' => 2, 'lastPage' => 4]], $props['items']);
        self::assertSame(['items.data'], $metadata['mergeProps']);
        self::assertSame(
            ['items' => ['pageName' => 'page', 'previousPage' => 1, 'nextPage' => 3, 'currentPage' => 2, 'reset' => false]],
            $metadata['scrollProps'],
        );
    }

    #[Test]
    public function scrollPropsPrependWhenTheClientAsks(): void
    {
        $request = $this->partialRequest('Page', ['items']);
        $request = $request->withHeader(Header::INFINITE_SCROLL_MERGE_INTENT, 'prepend');
        [, $metadata] = (new PropsResolver($request, 'Page'))->resolve([], [
            'items' => new ScrollProp(new ArrayPaginator(range(1, 10), 1, 3)),
        ]);

        self::assertSame(['items.data'], $metadata['prependProps']);
        self::assertArrayNotHasKey('mergeProps', $metadata);
    }

    #[Test]
    public function scrollPropsTakeAListWithArrayMetadata(): void
    {
        [$props, $metadata] = (new PropsResolver($this->request(), 'Page'))->resolve([], [
            'posts' => new ScrollProp(
                static fn(): array => [['id' => 7]],
                'data',
                ['pageName' => 'p', 'currentPage' => 3, 'previousPage' => 2, 'nextPage' => null],
            ),
        ]);

        self::assertSame(['data' => [['id' => 7]]], $props['posts']);
        self::assertSame(
            ['pageName' => 'p', 'previousPage' => 2, 'nextPage' => null, 'currentPage' => 3, 'reset' => false],
            $metadata['scrollProps']['posts'],
        );
    }

    #[Test]
    public function scrollPropsTakeAMetadataCallbackAndACustomWrapper(): void
    {
        [$props, $metadata] = (new PropsResolver($this->request(), 'Page'))->resolve([], [
            'posts' => new ScrollProp(
                ['items' => ['a'], 'total' => 1],
                'items',
                static fn(array $value): ScrollMetadata => new ScrollMetadata('page', null, null, 1),
            ),
        ]);

        self::assertSame(['items' => ['a'], 'total' => 1], $props['posts']);
        self::assertSame(['posts.items'], $metadata['mergeProps']);
        self::assertSame(1, $metadata['scrollProps']['posts']['currentPage']);
    }

    #[Test]
    public function resetScrollPropsAreMarkedAndNotMerged(): void
    {
        $request = $this->request([Header::RESET => 'items']);
        [, $metadata] = (new PropsResolver($request, 'Page'))->resolve([], [
            'items' => new ScrollProp(new ArrayPaginator(range(1, 10), 1, 3)),
        ]);

        self::assertTrue($metadata['scrollProps']['items']['reset']);
        self::assertArrayNotHasKey('mergeProps', $metadata);
    }

    #[Test]
    public function deferredScrollPropsAreLoadedAfterThePage(): void
    {
        [$props, $metadata] = (new PropsResolver($this->request(), 'Page'))->resolve([], [
            'items' => (new ScrollProp(static fn(): never => throw new \RuntimeException('must not be resolved', 1)))->defer('feed'),
        ]);

        self::assertSame([], $props);
        self::assertSame(['feed' => ['items']], $metadata['deferredProps']);
        self::assertSame(['items.data'], $metadata['mergeProps']);
    }

    #[Test]
    public function scrollPropsWithoutMetadataNeedAPaginator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1762300001);

        (new PropsResolver($this->request(), 'Page'))->resolve([], ['items' => new ScrollProp([1, 2])]);
    }
}

<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Unit\Service;

use LeonWbr\Inertia\Props\AlwaysProp;
use LeonWbr\Inertia\Props\DeferProp;
use LeonWbr\Inertia\Props\MergeProp;
use LeonWbr\Inertia\Props\OptionalProp;
use LeonWbr\Inertia\Service\PropsResolver;
use LeonWbr\Inertia\Support\Header;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
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
}

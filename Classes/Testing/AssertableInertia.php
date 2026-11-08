<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Testing;

use LeonWbr\Inertia\Support\Header;
use PHPUnit\Framework\Assert as PHPUnit;
use Psr\Http\Message\ResponseInterface;

/**
 * Assertions on an Inertia page object, after inertia-laravel's
 * assertInertia(): the component, props by dot path, and the metadata
 * (deferred, merge, once and scroll props, flash data, version, URL).
 *
 * Works on a JSON response to an Inertia request and on the HTML of a
 * first visit (the `<script data-page>` element). Every method returns
 * $this, so assertions chain:
 *
 *     AssertableInertia::fromResponse($response)
 *         ->component('Products')
 *         ->has('products.data', 3)
 *         ->where('filters.q', 'typo3')
 *         ->hasDeferredProps('default', ['stats'])
 *         ->missing('internal');
 */
final class AssertableInertia
{
    /**
     * @param array<string, mixed> $page
     */
    private function __construct(
        private readonly array $page,
    ) {}

    public static function fromResponse(ResponseInterface $response): self
    {
        $body = (string)$response->getBody();

        if ($response->getHeaderLine(Header::INERTIA) === 'true') {
            PHPUnit::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'), 'Not an Inertia JSON response.');

            return self::fromJson($body);
        }

        if (preg_match('#<script data-page="[^"]*" type="application/json">(.*?)</script>#s', $body, $matches) !== 1) {
            PHPUnit::fail('Not an Inertia response: no JSON response and no page script in the HTML.');
        }

        // The JSON is not HTML-escaped (JSON_HEX_TAG keeps </script> out).
        return self::fromJson($matches[1]);
    }

    /**
     * @param array<string, mixed> $page
     */
    public static function fromArray(array $page): self
    {
        PHPUnit::assertArrayHasKey('component', $page, 'The page object has no component.');
        PHPUnit::assertArrayHasKey('props', $page, 'The page object has no props.');

        return new self($page);
    }

    private static function fromJson(string $json): self
    {
        try {
            $page = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            PHPUnit::fail('The page object is not valid JSON: ' . $e->getMessage());
        }
        PHPUnit::assertIsArray($page, 'The page object is not a JSON object.');

        return self::fromArray($page);
    }

    public function component(string $expected): self
    {
        PHPUnit::assertSame($expected, $this->page['component'], 'Unexpected Inertia page component.');

        return $this;
    }

    public function url(string $expected): self
    {
        PHPUnit::assertSame($expected, $this->page['url'] ?? null, 'Unexpected Inertia page URL.');

        return $this;
    }

    public function version(string $expected): self
    {
        PHPUnit::assertSame($expected, $this->page['version'] ?? null, 'Unexpected Inertia asset version.');

        return $this;
    }

    /**
     * The prop exists; with $count, it is an array with that many items.
     */
    public function has(string $path, ?int $count = null): self
    {
        $value = $this->existingProp($path);

        if ($count !== null) {
            PHPUnit::assertIsArray($value, sprintf('Inertia prop [%s] is not an array.', $path));
            PHPUnit::assertCount($count, $value, sprintf('Inertia prop [%s] does not have %d items.', $path, $count));
        }

        return $this;
    }

    /**
     * @param list<string> $paths
     */
    public function hasAll(array $paths): self
    {
        foreach ($paths as $path) {
            $this->has($path);
        }

        return $this;
    }

    public function missing(string $path): self
    {
        PHPUnit::assertFalse($this->lookup($path)[0], sprintf('Inertia prop [%s] exists, but should be missing.', $path));

        return $this;
    }

    /**
     * The prop equals $expected; a closure gets the value and must return true.
     */
    public function where(string $path, mixed $expected): self
    {
        $actual = $this->existingProp($path);

        if ($expected instanceof \Closure) {
            PHPUnit::assertTrue($expected($actual) === true, sprintf('Inertia prop [%s] was marked as invalid by the closure.', $path));
        } else {
            PHPUnit::assertSame($expected, $actual, sprintf('Inertia prop [%s] does not match the expected value.', $path));
        }

        return $this;
    }

    /**
     * The props, or one prop by dot path.
     */
    public function prop(?string $path = null): mixed
    {
        return $path === null ? $this->page['props'] : $this->lookup($path)[1];
    }

    /**
     * The validation error for a field in the `errors` prop.
     */
    public function hasError(string $field, ?string $message = null): self
    {
        $this->has('errors.' . $field);
        if ($message !== null) {
            $this->where('errors.' . $field, $message);
        }

        return $this;
    }

    public function hasNoErrors(): self
    {
        PHPUnit::assertSame([], $this->prop('errors') ?? [], 'The page has validation errors.');

        return $this;
    }

    /**
     * The props deferred in a group, in any order.
     *
     * @param list<string> $props
     */
    public function hasDeferredProps(string $group, array $props): self
    {
        $deferred = $this->page['deferredProps'][$group] ?? [];
        PHPUnit::assertEqualsCanonicalizing($props, $deferred, sprintf('Unexpected deferred props in group [%s].', $group));

        return $this;
    }

    /**
     * The props the client appends (mergeProps), in any order.
     *
     * @param list<string> $props
     */
    public function hasMergeProps(array $props): self
    {
        PHPUnit::assertEqualsCanonicalizing($props, $this->page['mergeProps'] ?? [], 'Unexpected merge props.');

        return $this;
    }

    /**
     * @param list<string> $props
     */
    public function hasPrependProps(array $props): self
    {
        PHPUnit::assertEqualsCanonicalizing($props, $this->page['prependProps'] ?? [], 'Unexpected prepend props.');

        return $this;
    }

    /**
     * @param list<string> $props
     */
    public function hasDeepMergeProps(array $props): self
    {
        PHPUnit::assertEqualsCanonicalizing($props, $this->page['deepMergeProps'] ?? [], 'Unexpected deep merge props.');

        return $this;
    }

    /**
     * A once prop by its key; $prop is the path it is stored at.
     */
    public function hasOnceProp(string $key, ?string $prop = null): self
    {
        PHPUnit::assertArrayHasKey($key, $this->page['onceProps'] ?? [], sprintf('[%s] is not a once prop.', $key));
        if ($prop !== null) {
            PHPUnit::assertSame($prop, $this->page['onceProps'][$key]['prop'], sprintf('Once prop [%s] points to another prop.', $key));
        }

        return $this;
    }

    /**
     * The scroll metadata of a prop; only the given keys are compared.
     *
     * @param array<string, mixed> $metadata
     */
    public function hasScrollProp(string $path, array $metadata = []): self
    {
        PHPUnit::assertArrayHasKey($path, $this->page['scrollProps'] ?? [], sprintf('[%s] is not a scroll prop.', $path));
        foreach ($metadata as $key => $value) {
            PHPUnit::assertSame($value, $this->page['scrollProps'][$path][$key] ?? null, sprintf('Scroll prop [%s] has another %s.', $path, $key));
        }

        return $this;
    }

    public function hasFlash(string $key, mixed $expected = null): self
    {
        PHPUnit::assertArrayHasKey($key, $this->page['flash'] ?? [], sprintf('No flash data [%s].', $key));
        if (func_num_args() > 1) {
            PHPUnit::assertSame($expected, $this->page['flash'][$key], sprintf('Flash data [%s] does not match.', $key));
        }

        return $this;
    }

    public function missingFlash(string $key): self
    {
        PHPUnit::assertArrayNotHasKey($key, $this->page['flash'] ?? [], sprintf('Flash data [%s] exists, but should be missing.', $key));

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->page;
    }

    /**
     * The value of a prop that must exist: a null prop passes, a missing
     * one fails.
     */
    private function existingProp(string $path): mixed
    {
        [$found, $value] = $this->lookup($path);
        PHPUnit::assertTrue($found, sprintf('Inertia prop [%s] does not exist.', $path));

        return $value;
    }

    /**
     * Resolves a dot path in the props, telling a missing prop apart from a
     * null one.
     *
     * @return array{0: bool, 1: mixed} [found, value]
     */
    private function lookup(string $path): array
    {
        $value = $this->page['props'];
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return [false, null];
            }
            $value = $value[$segment];
        }

        return [true, $value];
    }
}

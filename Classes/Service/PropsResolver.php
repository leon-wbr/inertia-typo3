<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Service;

use LeonWbr\Inertia\Props\AlwaysProp;
use LeonWbr\Inertia\Props\Deferrable;
use LeonWbr\Inertia\Props\IgnoreFirstLoad;
use LeonWbr\Inertia\Props\Mergeable;
use LeonWbr\Inertia\Support\Header;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves page props for one request: filters partial reloads, leaves out
 * optional and deferred props on the first load, resolves closures and
 * collects the metadata the client needs (deferredProps, mergeProps, …).
 *
 * Follows Inertia\PropsResolver from inertia-laravel 3.x, without the
 * Laravel-only prop types (once, scroll, live and rescued props).
 */
final class PropsResolver
{
    private bool $isPartial;

    /** @var list<string>|null */
    private ?array $only;

    /** @var list<string>|null */
    private ?array $except;

    /** @var list<string> */
    private array $resetProps;

    /** @var array<string, list<string>> */
    private array $deferredProps = [];

    /** @var list<string> */
    private array $mergeProps = [];

    /** @var list<string> */
    private array $prependProps = [];

    /** @var list<string> */
    private array $deepMergeProps = [];

    /** @var list<string> */
    private array $matchPropsOn = [];

    /** @var list<string> */
    private array $sharedPropKeys = [];

    public function __construct(
        private readonly ServerRequestInterface $request,
        string $component,
    ) {
        $this->isPartial = $request->getHeaderLine(Header::PARTIAL_COMPONENT) === $component;
        $this->only = self::parseHeader($request, Header::PARTIAL_ONLY);
        $this->except = self::parseHeader($request, Header::PARTIAL_EXCEPT);
        $this->resetProps = self::parseHeader($request, Header::RESET) ?? [];
    }

    /**
     * @param array<string, mixed> $shared
     * @param array<string, mixed> $props
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function resolve(array $shared, array $props): array
    {
        $keys = [];
        foreach (array_keys($shared) as $key) {
            $keys[explode('.', (string)$key, 2)[0]] = true;
        }
        $this->sharedPropKeys = array_map('strval', array_keys($keys));

        $props = self::unpackDotProps(array_merge($shared, $props), $this->request);

        return [$this->resolveProps($props), $this->buildMetadata()];
    }

    /**
     * @param array<array-key, mixed> $props
     * @return array<array-key, mixed>
     */
    private function resolveProps(array $props, string $prefix = '', bool $parentWasResolved = false): array
    {
        $result = [];

        foreach ($props as $key => $prop) {
            $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;

            if (!$this->shouldIncludeInPartialResponse($prop, $path, $parentWasResolved)) {
                continue;
            }

            if (!$this->isPartial && $this->excludeFromInitialResponse($prop, $path)) {
                continue;
            }

            $value = $this->resolveValue($prop);

            // A closure may return a prop type; unwrap it one more level.
            if ($value !== $prop && self::isPropType($value)) {
                $prop = $value;
                if (!$this->isPartial && $this->excludeFromInitialResponse($prop, $path)) {
                    continue;
                }
                $value = $this->resolveValue($prop);
            }

            if ($prop instanceof Mergeable && $prop->shouldMerge()) {
                $this->collectMergeableMetadata($path, $prop);
            }

            $result[$key] = is_array($value)
                ? $this->resolveProps($value, $path, $parentWasResolved || !is_array($prop))
                : $value;
        }

        return $result;
    }

    private function shouldIncludeInPartialResponse(mixed $prop, string $path, bool $parentWasResolved): bool
    {
        if (!$this->isPartial || $prop instanceof AlwaysProp || $parentWasResolved) {
            return true;
        }

        if ($this->only !== null && !$this->matches($path, $this->only) && !$this->leadsToOnly($path)) {
            return false;
        }

        return !($this->except !== null && $this->matches($path, $this->except));
    }

    private function excludeFromInitialResponse(mixed $prop, string $path): bool
    {
        if (!$prop instanceof IgnoreFirstLoad) {
            return false;
        }

        if ($prop instanceof Deferrable) {
            $this->deferredProps[$prop->group()][] = $path;
        }

        if ($prop instanceof Mergeable && $prop->shouldMerge()) {
            $this->collectMergeableMetadata($path, $prop);
        }

        return true;
    }

    private function collectMergeableMetadata(string $path, Mergeable $prop): void
    {
        if (in_array($path, $this->resetProps, true)) {
            return;
        }

        if ($this->isPartial && !$this->isIncludedInPartialMetadata($path)) {
            return;
        }

        if ($prop->shouldDeepMerge()) {
            $this->deepMergeProps[] = $path;
        } elseif ($prop->appendsAtRoot()) {
            $this->mergeProps[] = $path;
        } elseif ($prop->prependsAtRoot()) {
            $this->prependProps[] = $path;
        } else {
            foreach ($prop->appendsAtPaths() as $appendPath) {
                $this->mergeProps[] = $path . '.' . $appendPath;
            }
            foreach ($prop->prependsAtPaths() as $prependPath) {
                $this->prependProps[] = $path . '.' . $prependPath;
            }
        }

        foreach ($prop->matchesOn() as $strategy) {
            $this->matchPropsOn[] = $path . '.' . $strategy;
        }
    }

    private function isIncludedInPartialMetadata(string $path): bool
    {
        if ($this->only !== null && !$this->matches($path, $this->only)) {
            return false;
        }

        return !($this->except !== null && $this->matches($path, $this->except));
    }

    /**
     * @param list<string> $filters
     */
    private function matches(string $path, array $filters): bool
    {
        foreach ($filters as $filter) {
            if ($path === $filter || str_starts_with($path, $filter . '.')) {
                return true;
            }
        }

        return false;
    }

    private function leadsToOnly(string $path): bool
    {
        foreach ($this->only ?? [] as $onlyPath) {
            if (str_starts_with($onlyPath, $path . '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMetadata(): array
    {
        return array_filter([
            'sharedProps' => $this->sharedPropKeys,
            'mergeProps' => $this->mergeProps,
            'prependProps' => $this->prependProps,
            'deepMergeProps' => $this->deepMergeProps,
            'matchPropsOn' => $this->matchPropsOn,
            'deferredProps' => $this->deferredProps,
        ], static fn(array $value): bool => $value !== []);
    }

    /**
     * Closures and prop objects are called with the request being rendered.
     */
    private function resolveValue(mixed $value): mixed
    {
        if ($value instanceof \Closure || (is_object($value) && is_callable($value))) {
            $value = $value($this->request);
        }

        if ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        return $value;
    }

    private static function isPropType(mixed $value): bool
    {
        return $value instanceof AlwaysProp
            || $value instanceof IgnoreFirstLoad
            || $value instanceof Mergeable;
    }

    /**
     * Turns top-level keys like "user.name" into nested arrays.
     *
     * @param array<array-key, mixed> $props
     * @return array<array-key, mixed>
     */
    private static function unpackDotProps(array $props, ServerRequestInterface $request): array
    {
        foreach ($props as $key => $value) {
            if (!is_string($key) || !str_contains($key, '.')) {
                continue;
            }

            unset($props[$key]);
            $segments = explode('.', $key);
            $last = array_pop($segments);
            $current = &$props;
            foreach ($segments as $segment) {
                if (isset($current[$segment]) && $current[$segment] instanceof \Closure) {
                    $current[$segment] = $current[$segment]($request);
                }
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
            $current[$last] = $value;
            unset($current);
        }

        return $props;
    }

    /**
     * @return list<string>|null
     */
    private static function parseHeader(ServerRequestInterface $request, string $name): ?array
    {
        $values = array_values(array_filter(
            array_map('trim', explode(',', $request->getHeaderLine($name))),
            static fn(string $value): bool => $value !== '',
        ));

        return $values === [] ? null : $values;
    }
}

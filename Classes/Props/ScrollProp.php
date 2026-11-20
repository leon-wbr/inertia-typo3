<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

use LeonWbr\Inertia\Support\Header;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Pagination\PaginatorInterface;

/**
 * A paginated prop for `<InfiniteScroll>`, like Inertia::scroll() in
 * inertia-laravel 3.x: the page announces it in `scrollProps`, and each
 * page the client loads is appended (or prepended, by the
 * X-Inertia-Infinite-Scroll-Merge-Intent header) to the items under the
 * wrapper key.
 *
 * The value can be:
 * - a TYPO3 PaginatorInterface: sent as `{data: items, meta: {…}}`, with
 *   the metadata taken from it;
 * - a list of items: sent as `{data: items}`; pass the metadata;
 * - an array that already contains the wrapper key; pass the metadata;
 * - a closure returning any of these.
 */
final class ScrollProp implements Deferrable, Mergeable
{
    use DefersProps;
    use MergesProps;

    private mixed $resolved = null;

    private bool $isResolved = false;

    /** @var ProvidesScrollMetadata|\Closure|array<string, mixed>|null */
    private ProvidesScrollMetadata|\Closure|array|null $metadata;

    /**
     * @param ProvidesScrollMetadata|(callable(mixed): ProvidesScrollMetadata)|array{pageName?: string, previousPage?: int|string|null, nextPage?: int|string|null, currentPage?: int|string|null}|null $metadata
     */
    public function __construct(
        private readonly mixed $value,
        private readonly string $wrapper = 'data',
        ProvidesScrollMetadata|callable|array|null $metadata = null,
        private readonly string $pageName = ScrollMetadata::DEFAULT_PAGE_NAME,
    ) {
        $this->merge = true;
        // Until the request's merge intent is known (a deferred scroll prop
        // is announced before it is resolved), new items are appended.
        $this->append($this->wrapper);
        $this->metadata = \is_callable($metadata) && !$metadata instanceof ProvidesScrollMetadata
            ? \Closure::fromCallable($metadata)
            : $metadata;
    }

    /**
     * Appends the next page, or prepends the previous one, as the client asks.
     */
    public function configureMergeIntent(ServerRequestInterface $request): static
    {
        $this->appendsAtPaths = [];
        $this->prependsAtPaths = [];

        return $request->getHeaderLine(Header::INFINITE_SCROLL_MERGE_INTENT) === 'prepend'
            ? $this->prepend($this->wrapper)
            : $this->append($this->wrapper);
    }

    /**
     * @return array{pageName: string, previousPage: int|string|null, nextPage: int|string|null, currentPage: int|string|null}
     */
    public function metadata(?ServerRequestInterface $request = null): array
    {
        $raw = $this->raw($request);
        $metadata = match (true) {
            $this->metadata instanceof ProvidesScrollMetadata => $this->metadata,
            $this->metadata instanceof \Closure => ($this->metadata)($raw),
            \is_array($this->metadata) => ScrollMetadata::fromArray($this->metadata + ['pageName' => $this->pageName]),
            $raw instanceof PaginatorInterface => ScrollMetadata::fromPaginator($raw, $this->pageName),
            default => throw new \InvalidArgumentException(
                'A scroll prop needs a TYPO3 paginator as value, or metadata (an array, a ProvidesScrollMetadata or a callback).',
                1762300001,
            ),
        };
        if (!$metadata instanceof ProvidesScrollMetadata) {
            throw new \UnexpectedValueException('The scroll metadata callback must return a ProvidesScrollMetadata.', 1762300002);
        }

        return [
            'pageName' => $metadata->getPageName(),
            'previousPage' => $metadata->getPreviousPage(),
            'nextPage' => $metadata->getNextPage(),
            'currentPage' => $metadata->getCurrentPage(),
        ];
    }

    public function __invoke(?ServerRequestInterface $request = null): mixed
    {
        $raw = $this->raw($request);

        if ($raw instanceof PaginatorInterface) {
            return [
                $this->wrapper => self::listOf($raw->getPaginatedItems()),
                'meta' => [
                    'currentPage' => $raw->getCurrentPageNumber(),
                    'lastPage' => $raw->getNumberOfPages(),
                ],
            ];
        }

        if (\is_array($raw) && array_is_list($raw)) {
            return [$this->wrapper => $raw];
        }

        return $raw;
    }

    /**
     * The value as given, with a closure called once.
     */
    private function raw(?ServerRequestInterface $request): mixed
    {
        if (!$this->isResolved) {
            $this->resolved = $this->value instanceof \Closure ? ($this->value)($request) : $this->value;
            $this->isResolved = true;
        }

        return $this->resolved;
    }

    /**
     * @param iterable<mixed> $items
     * @return list<mixed>
     */
    private static function listOf(iterable $items): array
    {
        return array_values(\is_array($items) ? $items : iterator_to_array($items, false));
    }
}

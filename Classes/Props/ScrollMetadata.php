<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

use TYPO3\CMS\Core\Pagination\PaginatorInterface;

/**
 * Scroll metadata from a TYPO3 core paginator or from plain values.
 */
final class ScrollMetadata implements ProvidesScrollMetadata, \JsonSerializable
{
    public const DEFAULT_PAGE_NAME = 'page';

    public function __construct(
        private readonly string $pageName = self::DEFAULT_PAGE_NAME,
        private readonly int|string|null $previousPage = null,
        private readonly int|string|null $nextPage = null,
        private readonly int|string|null $currentPage = null,
    ) {}

    /**
     * From TYPO3's PaginatorInterface (ArrayPaginator, QueryBuilderPaginator,
     * Extbase's QueryResultPaginator, …). TYPO3 paginators don't know the
     * query parameter they were built from, so pass it if it isn't "page".
     */
    public static function fromPaginator(PaginatorInterface $paginator, string $pageName = self::DEFAULT_PAGE_NAME): self
    {
        $current = $paginator->getCurrentPageNumber();

        return new self(
            $pageName,
            $current > 1 ? $current - 1 : null,
            $current < $paginator->getNumberOfPages() ? $current + 1 : null,
            $current,
        );
    }

    /**
     * From an array with the keys pageName, previousPage, nextPage and
     * currentPage (all optional).
     *
     * @param array{pageName?: string, previousPage?: int|string|null, nextPage?: int|string|null, currentPage?: int|string|null} $metadata
     */
    public static function fromArray(array $metadata): self
    {
        return new self(
            $metadata['pageName'] ?? self::DEFAULT_PAGE_NAME,
            $metadata['previousPage'] ?? null,
            $metadata['nextPage'] ?? null,
            $metadata['currentPage'] ?? null,
        );
    }

    public function getPageName(): string
    {
        return $this->pageName;
    }

    public function getPreviousPage(): int|string|null
    {
        return $this->previousPage;
    }

    public function getNextPage(): int|string|null
    {
        return $this->nextPage;
    }

    public function getCurrentPage(): int|string|null
    {
        return $this->currentPage;
    }

    /**
     * @return array{pageName: string, previousPage: int|string|null, nextPage: int|string|null, currentPage: int|string|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'pageName' => $this->pageName,
            'previousPage' => $this->previousPage,
            'nextPage' => $this->nextPage,
            'currentPage' => $this->currentPage,
        ];
    }
}

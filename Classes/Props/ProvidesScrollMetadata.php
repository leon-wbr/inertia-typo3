<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

/**
 * Pagination data for `<InfiniteScroll>`: the query parameter the client
 * sends the page in, and the previous, next and current page.
 */
interface ProvidesScrollMetadata
{
    public function getPageName(): string;

    public function getPreviousPage(): int|string|null;

    public function getNextPage(): int|string|null;

    public function getCurrentPage(): int|string|null;
}

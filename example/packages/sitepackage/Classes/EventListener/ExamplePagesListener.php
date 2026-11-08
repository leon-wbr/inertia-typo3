<?php

declare(strict_types=1);

namespace LeonWbr\InertiaSitepackage\EventListener;

use LeonWbr\Inertia\Event\BeforeInertiaPageRenderedEvent;
use LeonWbr\Inertia\Service\InertiaService;
use LeonWbr\InertiaSitepackage\Middleware\ContactFormMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;

/**
 * Picks the page component by the page's frontend layout and adds the
 * props the example pages need.
 *
 * - Layout 1, "Features": deferred, optional and merge props.
 * - Layout 2, "Contact": the contact form; its errors and success message
 *   come from the session (ContactFormMiddleware), not from props here.
 * - Layout 3, "Articles": a scroll prop for <InfiniteScroll>, paginated
 *   with TYPO3's ArrayPaginator.
 */
#[AsEventListener('leon-wbr/inertia-sitepackage/example-pages')]
final class ExamplePagesListener
{
    public const FEATURES_LAYOUT = 1;
    public const ARTICLES_LAYOUT = 3;

    private const ARTICLES_PER_PAGE = 10;
    private const ARTICLES_TOTAL = 35;

    private const ITEMS_PER_PAGE = 3;
    private const ITEMS_TOTAL = 9;

    public function __construct(
        private readonly InertiaService $inertia,
    ) {}

    public function __invoke(BeforeInertiaPageRenderedEvent $event): void
    {
        $layout = (int)($event->getPage()['layout'] ?? 0);
        $query = $event->getRequest()->getQueryParams();

        if ($layout === self::FEATURES_LAYOUT) {
            $page = max(1, (int)($query['page'] ?? 1));
            $event->setComponent('Features');
            $event->addProp('stats', $this->inertia->defer(static fn(): array => [
                'contentElements' => \count($event->getProps()['contentElements'] ?? []),
                'generatedAt' => gmdate('c'),
            ]));
            $event->addProp('details', $this->inertia->optional(static fn(?ServerRequestInterface $request): array => [
                'requestedWith' => $request?->getHeaderLine('X-Inertia-Partial-Data') ?? '',
            ]));
            $event->addProp('items', $this->inertia->merge($this->items($page))->matchOn('id'));
            $event->addProp('pagination', [
                'page' => $page,
                'hasMore' => $page * self::ITEMS_PER_PAGE < self::ITEMS_TOTAL,
            ]);
        }

        if ($layout === ContactFormMiddleware::LAYOUT) {
            $event->setComponent('Contact');
        }

        if ($layout === self::ARTICLES_LAYOUT) {
            $page = max(1, (int)($query['page'] ?? 1));
            $event->setComponent('Articles');
            $event->addProp('articles', $this->inertia->scroll(
                static fn(): ArrayPaginator => new ArrayPaginator(self::articles(), $page, self::ARTICLES_PER_PAGE),
            ));
        }
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private static function articles(): array
    {
        return array_map(
            static fn(int $id): array => ['id' => $id, 'title' => 'Article ' . $id],
            range(1, self::ARTICLES_TOTAL),
        );
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function items(int $page): array
    {
        $items = [];
        $first = ($page - 1) * self::ITEMS_PER_PAGE + 1;
        $last = min($first + self::ITEMS_PER_PAGE - 1, self::ITEMS_TOTAL);
        for ($id = $first; $id <= $last; $id++) {
            $items[] = ['id' => $id, 'title' => 'Item ' . $id];
        }

        return $items;
    }
}

<?php

declare(strict_types=1);

namespace LeonWbr\InertiaConformance\EventListener;

use LeonWbr\Inertia\Event\BeforeInertiaPageRenderedEvent;
use LeonWbr\Inertia\Service\InertiaService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;

/**
 * The conformance cases: every case is a TYPO3 page (fixtures/pages.sql),
 * rendered by the adapter's page plugin. This listener picks the component
 * and the props of each page by its slug, using the adapter's prop types.
 *
 * Components (one per client, same name and DOM in clients/<client>/Pages):
 * - Dump: shows component, url, props and flash as JSON;
 * - Deferred, WhenVisible, Scroll, Poll, Form, Remember: one client API each.
 */
#[AsEventListener('leon-wbr/inertia-conformance/case-pages')]
final class CasePages
{
    public const ITEMS_PER_PAGE = 3;
    public const SCROLL_TOTAL = 30;
    public const SCROLL_PER_PAGE = 10;

    public function __construct(
        private readonly InertiaService $inertia,
    ) {}

    public function __invoke(BeforeInertiaPageRenderedEvent $event): void
    {
        $request = $event->getRequest();
        $slug = (string)($event->getPage()['slug'] ?? '/');
        $query = $request->getQueryParams();
        $page = max(1, (int)($query['page'] ?? 1));

        // TYPO3's own props (title, contentElements) aren't part of the cases.
        $props = ['title' => (string)($event->getPage()['title'] ?? '')];
        $component = 'Dump';

        switch ($slug) {
            case '/':
            case '/dump':
            case '/dump/other':
                $props += [
                    'script' => '</script><script>window.__conformanceXss = true</script>',
                    'nested' => ['a' => ['b' => 1, 'c' => 2], 'd' => 3],
                    'requestedAt' => static fn(): string => (string)hrtime(true),
                    'lazy' => $this->inertia->optional(static fn(): string => 'lazy value'),
                    'always' => $this->inertia->always(static fn(): string => 'always value'),
                    'expensive' => static fn(): string => 'expensive value',
                ];
                break;

            case '/merge':
                $props += [
                    'items' => $this->inertia->merge(self::items($page))->matchOn('id'),
                    'feed' => $this->inertia->merge(['entry ' . $page])->prepend(),
                    'settings' => $this->inertia->deepMerge(['pages' => [(string)$page => true], 'last' => $page]),
                    'page' => $page,
                ];
                break;

            case '/once':
            case '/once/other':
                $props += [
                    'plans' => $this->inertia->once(static fn(): string => 'plans@' . hrtime(true)),
                    'fresh' => $this->inertia->once(static fn(): string => 'fresh@' . hrtime(true))->fresh(),
                    'short' => $this->inertia->once(static fn(): string => 'short@' . hrtime(true))->until(new \DateTimeImmutable('-1 second')),
                ];
                break;

            case '/deferred':
                $component = 'Deferred';
                $props += [
                    'fast' => 'not deferred',
                    'slow' => $this->inertia->defer(static fn(): string => 'slow value'),
                    'other' => $this->inertia->defer(static fn(): string => 'other value', 'other'),
                    'query' => $this->inertia->defer(static fn(?ServerRequestInterface $request): string => (string)($request?->getUri()->getQuery() ?? '')),
                ];
                break;

            case '/deferred/rescue':
                $component = 'Deferred';
                $props += [
                    'fast' => 'not deferred',
                    'slow' => $this->inertia->defer(static fn(): string => throw new \RuntimeException('deferred prop failed', 1762330001)),
                    'other' => $this->inertia->defer(static fn(): string => 'other value', 'other'),
                    'query' => '',
                ];
                break;

            case '/when-visible':
                $component = 'WhenVisible';
                $props += [
                    'lazy' => $this->inertia->optional(static fn(): string => 'visible value'),
                ];
                break;

            case '/scroll':
                $component = 'Scroll';
                $props += [
                    'items' => $this->inertia->scroll(
                        static fn(): ArrayPaginator => new ArrayPaginator(self::scrollItems(), $page, self::SCROLL_PER_PAGE),
                    ),
                ];
                break;

            case '/poll':
                $component = 'Poll';
                $props += [
                    'tick' => static fn(): string => (string)hrtime(true),
                ];
                break;

            case '/form':
                $component = 'Form';
                break;

            case '/remember':
                $component = 'Remember';
                break;

            case '/history/encrypted':
                $this->inertia->encryptHistory();
                $props += ['secret' => 'encrypted value'];
                break;

            case '/history/clear':
                $this->inertia->clearHistory();
                break;
        }

        $event->setComponent($component);
        $event->setProps($props);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private static function items(int $page): array
    {
        $first = ($page - 1) * self::ITEMS_PER_PAGE + 1;

        return array_map(
            static fn(int $id): array => ['id' => $id, 'name' => 'Item ' . $id],
            range($first, $first + self::ITEMS_PER_PAGE - 1),
        );
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private static function scrollItems(): array
    {
        return array_map(
            static fn(int $id): array => ['id' => $id, 'name' => 'Entry ' . $id],
            range(1, self::SCROLL_TOTAL),
        );
    }
}

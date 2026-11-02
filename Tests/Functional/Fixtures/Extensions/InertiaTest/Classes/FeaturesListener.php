<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Functional\Fixtures\Extensions\InertiaTest\Classes;

use LeonWbr\Inertia\Event\BeforeInertiaPageRenderedEvent;
use LeonWbr\Inertia\Service\InertiaService;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;

/**
 * Once and scroll props on /visible/child (page 7).
 */
#[AsEventListener('leon-wbr/inertia-test/features')]
final class FeaturesListener
{
    public function __construct(
        private readonly InertiaService $inertia,
    ) {}

    public function __invoke(BeforeInertiaPageRenderedEvent $event): void
    {
        if ((int)($event->getPage()['uid'] ?? 0) !== 7) {
            return;
        }

        $page = max(1, (int)($event->getRequest()->getQueryParams()['page'] ?? 1));
        $event->addProp('plans', $this->inertia->once(static fn(): array => ['basic', 'pro']));
        $event->addProp('items', $this->inertia->scroll(new ArrayPaginator(range(1, 10), $page, 3)));
    }
}

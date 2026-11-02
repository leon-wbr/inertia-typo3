<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Builds a navigation tree from the page tree for use as an Inertia prop.
 *
 * PageRepository::getMenu() applies the same rules as TYPO3's own menus:
 * language overlays, hidden, start/end time, fe_group access and the
 * current workspace. Pages hidden in menus are left out.
 */
class NavigationService
{
    public function __construct(
        protected readonly Context $context,
    ) {}

    /**
     * @return list<array{uid: int, title: string, link: string, active: bool, current: bool, children: list<array<string, mixed>>}>
     */
    public function build(ServerRequestInterface $request, ?int $rootPageId = null, int $levels = 2): array
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return [];
        }

        $context = clone $this->context;
        $language = $request->getAttribute('language');
        if ($language instanceof SiteLanguage) {
            $context->setAspect('language', LanguageAspectFactory::createFromSiteLanguage($language));
        }

        $pageRepository = GeneralUtility::makeInstance(PageRepository::class, $context);
        $routing = $request->getAttribute('routing');
        $currentPageId = $routing instanceof PageArguments ? $routing->getPageId() : 0;
        $rootLine = $currentPageId > 0 ? $this->rootLineIds($pageRepository, $currentPageId) : [];

        return $this->buildLevel(
            $context,
            $pageRepository,
            $site,
            $language instanceof SiteLanguage ? $language : null,
            $rootPageId ?? $site->getRootPageId(),
            $levels,
            $currentPageId,
            $rootLine,
        );
    }

    /**
     * @param list<int> $rootLine
     * @return list<array{uid: int, title: string, link: string, active: bool, current: bool, children: list<array<string, mixed>>}>
     */
    protected function buildLevel(
        Context $context,
        PageRepository $pageRepository,
        Site $site,
        ?SiteLanguage $language,
        int $parentId,
        int $levels,
        int $currentPageId,
        array $rootLine,
    ): array {
        if ($levels < 1) {
            return [];
        }

        /** @var LanguageAspect $languageAspect */
        $languageAspect = $context->getAspect('language');
        $items = [];
        foreach ($pageRepository->getMenu($parentId) as $page) {
            // Same rules as core menus: hidden in menu, or not available in
            // this language (no translation with a strict fallback).
            if (!empty($page['nav_hide']) || !$pageRepository->isPageSuitableForLanguage($page, $languageAspect)) {
                continue;
            }

            $pageUid = (int)$page['uid'];
            $parameters = $language instanceof SiteLanguage ? ['_language' => $language] : [];

            $items[] = [
                'uid' => $pageUid,
                'title' => (string)(($page['nav_title'] ?? '') !== '' ? $page['nav_title'] : $page['title']),
                'link' => (string)$site->getRouter()->generateUri($pageUid, $parameters)->getPath(),
                'active' => in_array($pageUid, $rootLine, true),
                'current' => $pageUid === $currentPageId,
                'children' => $this->buildLevel($context, $pageRepository, $site, $language, $pageUid, $levels - 1, $currentPageId, $rootLine),
            ];
        }

        return $items;
    }

    /**
     * @return list<int>
     */
    protected function rootLineIds(PageRepository $pageRepository, int $pageId): array
    {
        $ids = [];
        $page = $pageRepository->getPage($pageId);
        while ($page !== [] && count($ids) < 99) {
            $ids[] = (int)$page['uid'];
            $pid = (int)($page['pid'] ?? 0);
            if ($pid === 0) {
                break;
            }
            $page = $pageRepository->getPage($pid);
        }

        return $ids;
    }
}

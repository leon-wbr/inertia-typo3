<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Navigation;

use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * The parts of a NavigationService menu build that stay the same at every
 * level of the tree.
 */
final readonly class MenuScope
{
    /**
     * @param list<int> $rootLine Uids from the current page up to the root
     */
    public function __construct(
        public PageRepository $pages,
        public LanguageAspect $languageAspect,
        public Site $site,
        public ?SiteLanguage $language,
        public int $currentPageId,
        public array $rootLine,
    ) {}
}

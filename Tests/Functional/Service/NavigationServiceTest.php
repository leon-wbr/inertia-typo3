<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Functional\Service;

use LeonWbr\Inertia\Service\NavigationService;
use LeonWbr\Inertia\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\SiteFinder;

final class NavigationServiceTest extends AbstractFunctionalTestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function navigation(int $languageId = 0, int $currentPage = 7): array
    {
        $site = $this->get(SiteFinder::class)->getSiteByPageId(1);
        $language = $site->getLanguageById($languageId);
        $this->get(Context::class)->setAspect('language', LanguageAspectFactory::createFromSiteLanguage($language));

        $request = (new ServerRequest('https://website.local/', 'GET'))
            ->withAttribute('site', $site)
            ->withAttribute('language', $language)
            ->withAttribute('routing', new PageArguments($currentPage, '0', []));

        return $this->get(NavigationService::class)->build($request);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<string>
     */
    private static function titles(array $items): array
    {
        return array_map(static fn(array $item): string => $item['title'], $items);
    }

    #[Test]
    public function leavesOutHiddenRestrictedScheduledAndNavHidePages(): void
    {
        self::assertSame(['Visible'], self::titles($this->navigation()));
    }

    #[Test]
    public function buildsNestedLevelsWithLinksAndActiveState(): void
    {
        $navigation = $this->navigation();

        self::assertSame('/visible', $navigation[0]['link']);
        self::assertTrue($navigation[0]['active']);
        self::assertFalse($navigation[0]['current']);
        self::assertSame(['Child nav'], self::titles($navigation[0]['children']));
        self::assertSame('/visible/child', $navigation[0]['children'][0]['link']);
        self::assertTrue($navigation[0]['children'][0]['current']);
    }

    #[Test]
    public function usesTheTranslationAndLeavesOutUntranslatedPages(): void
    {
        $navigation = $this->navigation(1, 2);

        self::assertSame(['Sichtbar'], self::titles($navigation));
        self::assertSame('/de/sichtbar', $navigation[0]['link']);
        self::assertSame([], $navigation[0]['children']);
    }
}

<?php

declare(strict_types=1);

namespace LeonWbr\InertiaSitepackage\Middleware;

use LeonWbr\Inertia\Middleware\InertiaMiddleware as BaseInertiaMiddleware;
use LeonWbr\Inertia\Service\InertiaService;
use LeonWbr\Inertia\Service\NavigationService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Replaces the adapter's middleware to share site data with every page.
 *
 * Shared props are closures: they run when a page is rendered, with the
 * fully routed request, and not at all for redirects or partial reloads
 * that don't ask for them.
 */
class InertiaMiddleware extends BaseInertiaMiddleware
{
    public function __construct(
        InertiaService $inertia,
        ExtensionConfiguration $extensionConfiguration,
        protected readonly NavigationService $navigation,
    ) {
        parent::__construct($inertia, $extensionConfiguration);
    }

    public function share(ServerRequestInterface $request): array
    {
        return [
            'site' => fn(ServerRequestInterface $request): array => $this->site($request),
            'navigation' => fn(ServerRequestInterface $request): array => $this->navigation->build($request),
        ];
    }

    /**
     * @return array{title: string, home: string, language: string, languages: list<array{id: int, title: string, code: string, link: string, active: bool}>}
     */
    protected function site(ServerRequestInterface $request): array
    {
        $site = $request->getAttribute('site');
        $current = $request->getAttribute('language');
        if (!$site instanceof Site) {
            return ['title' => '', 'home' => '/', 'language' => '', 'languages' => []];
        }

        $routing = $request->getAttribute('routing');
        $pageId = $routing instanceof PageArguments ? $routing->getPageId() : $site->getRootPageId();

        $languages = [];
        foreach ($site->getLanguages() as $language) {
            $languages[] = [
                'id' => $language->getLanguageId(),
                'title' => $language->getTitle(),
                'code' => $language->getLocale()->getLanguageCode(),
                'link' => (string)$site->getRouter()->generateUri($pageId, ['_language' => $language])->getPath(),
                'active' => $current instanceof SiteLanguage && $current->getLanguageId() === $language->getLanguageId(),
            ];
        }

        $homeParameters = $current instanceof SiteLanguage ? ['_language' => $current] : [];

        return [
            'title' => (string)$site->getAttribute('websiteTitle'),
            'home' => (string)$site->getRouter()->generateUri($site->getRootPageId(), $homeParameters)->getPath(),
            'language' => $current instanceof SiteLanguage ? $current->getLocale()->getLanguageCode() : '',
            'languages' => $languages,
        ];
    }
}

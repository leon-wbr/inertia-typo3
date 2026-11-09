<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Controller;

use LeonWbr\Inertia\Event\BeforeInertiaPageRenderedEvent;
use LeonWbr\Inertia\Service\InertiaContentElementRenderer;
use LeonWbr\Inertia\Service\InertiaService;
use LeonWbr\Inertia\Settings\PageSettings;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Renders the current TYPO3 page as an Inertia page: the page title and its
 * content elements (colPos 0) as props of the component set in the site
 * setting inertia.component.
 */
class MainController extends ActionController
{
    public function __construct(
        protected readonly InertiaService $inertia,
        protected readonly InertiaContentElementRenderer $contentElementRenderer,
        protected readonly PageRepository $pageRepository,
    ) {}

    public function indexAction(): ResponseInterface
    {
        $routing = $this->request->getAttribute('routing');
        $pageId = $routing instanceof PageArguments ? $routing->getPageId() : 0;
        $page = $this->request->getAttribute('frontend.page.information')?->getPageRecord() ?? $this->pageRepository->getPage($pageId);

        $settings = PageSettings::fromRequest($this->request);

        $cObj = $this->request->getAttribute('currentContentObject');
        if (!$cObj instanceof ContentObjectRenderer) {
            throw new \LogicException('No current content object; is MainController rendered as a TYPO3 page plugin?', 1762160011);
        }
        $rows = $cObj->getRecords('tt_content', [
            'pidInList' => (string)$pageId,
            'where' => '{#colPos}=0',
            'orderBy' => 'sorting',
        ]);

        $contentElements = array_map(
            fn(array $row): array => $this->contentElementRenderer->renderRecord(
                $row,
                $cObj,
                allowProperties: $settings->allowProperties,
                useCamelCase: true,
                renderHtml: $settings->rendersHtmlFor((string)($row['CType'] ?? '')),
            ),
            $rows,
        );

        $event = new BeforeInertiaPageRenderedEvent(
            $this->request,
            $page,
            $settings->component,
            [
                'title' => $page['title'] ?? '',
                'contentElements' => $contentElements,
            ],
        );
        $this->eventDispatcher->dispatch($event);

        $response = $this->inertia->render($event->getComponent(), $event->getProps(), $this->request);

        if ($this->inertia->isInertiaRequest($this->request)) {
            // A JSON page object replaces the whole TYPO3 page.
            throw new PropagateResponseException($response, 1762160010);
        }

        return $response;
    }
}

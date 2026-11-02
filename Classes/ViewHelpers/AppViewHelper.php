<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders the Inertia root element with the page object, in the markup of
 * inertia-laravel 3.x (Inertia\Directive): a JSON script element followed
 * by the mount point.
 *
 *     <inertia:app page="{page}" />
 *
 * renders
 *
 *     <script data-page="app" type="application/json">{…}</script><div id="app"></div>
 */
final class AppViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('page', 'array', 'The Inertia page object', true);
        $this->registerArgument('id', 'string', 'Id of the mount element', false, 'app');
    }

    public function render(): string
    {
        $id = htmlspecialchars((string)$this->arguments['id'], ENT_QUOTES | ENT_HTML5);
        $json = json_encode(
            $this->arguments['page'],
            JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        return '<script data-page="' . $id . '" type="application/json">' . $json . '</script><div id="' . $id . '"></div>';
    }
}

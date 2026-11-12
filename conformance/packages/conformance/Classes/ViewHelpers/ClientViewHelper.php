<?php

declare(strict_types=1);

namespace LeonWbr\InertiaConformance\ViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Loads the bundle of one official Inertia client, picked by the
 * `inertia-conformance-client` cookie (react, vue or svelte; react by
 * default). Each Playwright project sets the cookie, so the same TYPO3
 * pages run against every client.
 *
 *     <c:client />
 */
final class ClientViewHelper extends AbstractViewHelper
{
    public const COOKIE = 'inertia-conformance-client';
    public const CLIENTS = ['react', 'vue', 'svelte'];

    protected $escapeOutput = false;

    public function render(): string
    {
        $client = $this->client();
        $base = '/_assets/conformance/' . $client . '/';
        $manifestFile = Environment::getPublicPath() . $base . '.vite/manifest.json';
        if (!is_file($manifestFile)) {
            return '<!-- conformance: no build for ' . $client . ' -->';
        }

        $manifest = json_decode((string)file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);
        $tags = [];
        foreach (\is_array($manifest) ? $manifest : [] as $chunk) {
            if (!\is_array($chunk) || ($chunk['isEntry'] ?? false) !== true) {
                continue;
            }
            foreach ((array)($chunk['css'] ?? []) as $css) {
                $tags[] = '<link rel="stylesheet" href="' . htmlspecialchars($base . $css) . '">';
            }
            $tags[] = '<script type="module" src="' . htmlspecialchars($base . (string)$chunk['file']) . '"></script>';
        }

        return '<meta name="conformance-client" content="' . $client . '">' . implode('', $tags);
    }

    private function client(): string
    {
        $context = $this->renderingContext;
        $request = $context !== null && $context->hasAttribute(ServerRequestInterface::class)
            ? $context->getAttribute(ServerRequestInterface::class)
            : null;
        $client = $request instanceof ServerRequestInterface ? (string)($request->getCookieParams()[self::COOKIE] ?? '') : '';

        return \in_array($client, self::CLIENTS, true) ? $client : 'react';
    }
}

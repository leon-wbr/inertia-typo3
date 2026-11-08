<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Settings;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * The site settings MainController renders a page with
 * (Configuration/Sets/Inertia/settings.definitions.yaml).
 */
final readonly class PageSettings
{
    /**
     * @param list<string> $typedTypes CTypes sent as data instead of rendered HTML
     * @param list<string> $allowProperties Record properties sent per content element; all if empty
     */
    private function __construct(
        public string $component,
        public array $typedTypes,
        public array $allowProperties,
    ) {}

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $site = $request->getAttribute('site');
        $settings = $site instanceof Site ? $site->getSettings() : null;

        return new self(
            (string)($settings?->get('inertia.component') ?? 'Default'),
            self::strings($settings?->get('inertia.contentElements.typedTypes')),
            self::strings($settings?->get('inertia.contentElements.properties')),
        );
    }

    public function rendersHtmlFor(string $cType): bool
    {
        return !in_array($cType, $this->typedTypes, true);
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        return array_values(array_filter((array)$value, 'is_string'));
    }
}

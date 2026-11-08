<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Unit\Settings;

use LeonWbr\Inertia\Settings\PageSettings;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Settings\Settings;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class PageSettingsTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $settings By identifier, as the settings definitions declare them
     */
    private function request(array $settings): ServerRequest
    {
        return (new ServerRequest('https://example.org/', 'GET'))
            ->withAttribute('site', new Site('main', 1, [], SiteSettings::create(new Settings($settings))));
    }

    #[Test]
    public function readsTheSiteSettings(): void
    {
        $settings = PageSettings::fromRequest($this->request([
            'inertia.component' => 'Page',
            'inertia.contentElements.typedTypes' => ['text', 'image'],
            'inertia.contentElements.properties' => ['header', 'bodytext'],
        ]));

        self::assertSame('Page', $settings->component);
        self::assertSame(['text', 'image'], $settings->typedTypes);
        self::assertSame(['header', 'bodytext'], $settings->allowProperties);
    }

    #[Test]
    public function fallsBackToDefaultsWithoutASite(): void
    {
        $settings = PageSettings::fromRequest(new ServerRequest('https://example.org/', 'GET'));

        self::assertSame('Default', $settings->component);
        self::assertSame([], $settings->typedTypes);
        self::assertSame([], $settings->allowProperties);
    }

    #[Test]
    public function fallsBackToDefaultsForMissingSettings(): void
    {
        $settings = PageSettings::fromRequest($this->request([]));

        self::assertSame('Default', $settings->component);
        self::assertSame([], $settings->allowProperties);
    }

    #[Test]
    public function dropsPropertiesThatAreNotStrings(): void
    {
        $settings = PageSettings::fromRequest($this->request([
            'inertia.contentElements.properties' => ['header', 3, null, 'bodytext'],
        ]));

        self::assertSame(['header', 'bodytext'], $settings->allowProperties);
    }

    #[Test]
    public function typedTypesGetDataInsteadOfHtml(): void
    {
        $settings = PageSettings::fromRequest($this->request([
            'inertia.contentElements.typedTypes' => ['text'],
        ]));

        self::assertFalse($settings->rendersHtmlFor('text'));
        self::assertTrue($settings->rendersHtmlFor('textpic'));
    }
}

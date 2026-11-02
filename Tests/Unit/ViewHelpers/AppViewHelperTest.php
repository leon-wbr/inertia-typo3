<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Unit\ViewHelpers;

use LeonWbr\Inertia\ViewHelpers\AppViewHelper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AppViewHelperTest extends UnitTestCase
{
    /**
     * @param array<string, mixed> $arguments
     */
    private function render(array $arguments): string
    {
        $viewHelper = new AppViewHelper();
        $viewHelper->setArguments($arguments + ['id' => 'app']);

        return $viewHelper->render();
    }

    #[Test]
    public function rendersTheInertia3PageScriptAndMountPoint(): void
    {
        $output = $this->render(['page' => ['component' => 'Page', 'props' => ['title' => 'Home'], 'url' => '/', 'version' => '']]);

        self::assertSame(
            '<script data-page="app" type="application/json">{"component":"Page","props":{"title":"Home"},"url":"/","version":""}</script><div id="app"></div>',
            $output,
        );
    }

    #[Test]
    public function doesNotUseTheDataPageAttributeOfInertia2(): void
    {
        self::assertStringNotContainsString('<div id="app" data-page', $this->render(['page' => ['component' => 'Page']]));
    }

    #[Test]
    public function scriptTagsInPropsCannotCloseTheScriptElement(): void
    {
        $output = $this->render(['page' => ['props' => ['html' => '</script><script>alert(1)</script>']]]);

        self::assertSame(1, substr_count($output, '</script>'));
        self::assertStringContainsString('</script>', $output);
    }

    #[Test]
    public function usesTheGivenId(): void
    {
        self::assertSame(
            '<script data-page="root" type="application/json">[]</script><div id="root"></div>',
            $this->render(['page' => [], 'id' => 'root']),
        );
    }
}

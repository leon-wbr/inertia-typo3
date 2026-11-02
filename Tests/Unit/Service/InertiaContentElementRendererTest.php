<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Unit\Service;

use LeonWbr\Inertia\Service\InertiaContentElementRenderer;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Domain\RecordFactory;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class InertiaContentElementRendererTest extends UnitTestCase
{
    private const ROW = ['uid' => 7, 'CType' => 'text'];

    /**
     * @param array<string, mixed> $properties
     */
    private function renderer(array $properties): InertiaContentElementRenderer
    {
        $record = $this->createStub(RecordInterface::class);
        $record->method('toArray')->willReturn($properties);
        $recordFactory = $this->createStub(RecordFactory::class);
        $recordFactory->method('createResolvedRecordFromDatabaseRow')->willReturn($record);

        return new InertiaContentElementRenderer($recordFactory);
    }

    private function cObj(string $html = '<p>html</p>'): ContentObjectRenderer
    {
        $cObj = $this->createStub(ContentObjectRenderer::class);
        $cObj->method('cObjGetSingle')->willReturn($html);

        return $cObj;
    }

    #[Test]
    public function allowPropertiesLimitsTheCamelCasedData(): void
    {
        $result = $this->renderer([
            'uid' => 7,
            'header' => 'Title',
            'header_layout' => 2,
            'pi_flexform' => '<xml>secret</xml>',
            'l18n_diffsource' => 'serialized',
        ])->renderRecord(self::ROW, $this->cObj(), ['uid', 'header', 'header_layout']);

        self::assertSame(['uid' => 7, 'header' => 'Title', 'headerLayout' => 2], $result['data']);
    }

    #[Test]
    public function emptyAllowPropertiesKeepsEverything(): void
    {
        $result = $this->renderer(['uid' => 7, 'space_before_class' => ''])->renderRecord(self::ROW, $this->cObj());

        self::assertSame(['uid' => 7, 'spaceBeforeClass' => ''], $result['data']);
    }

    #[Test]
    public function camelCaseCanBeTurnedOff(): void
    {
        $result = $this->renderer(['header_layout' => 1])->renderRecord(self::ROW, $this->cObj(), [], false);

        self::assertSame(['header_layout' => 1], $result['data']);
    }

    #[Test]
    public function rendersTheHtmlFallback(): void
    {
        $result = $this->renderer([])->renderRecord(self::ROW, $this->cObj('<div>text</div>'));

        self::assertSame('text', $result['CType']);
        self::assertSame('<div>text</div>', $result['html']);
    }

    #[Test]
    public function skipsTheHtmlForTypesWithAComponent(): void
    {
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->expects(self::never())->method('cObjGetSingle');

        $result = $this->renderer([])->renderRecord(self::ROW, $cObj, [], true, false);

        self::assertNull($result['html']);
    }

    #[Test]
    public function relationsAndDatesBecomePlainValues(): void
    {
        $related = $this->createStub(RecordInterface::class);
        $related->method('toArray')->willReturn(['uid' => 3, 'item_title' => 'Child']);

        $result = $this->renderer([
            'items' => new \ArrayIterator([$related]),
            'date' => new \DateTimeImmutable('2026-11-03T10:00:00+00:00'),
        ])->renderRecord(self::ROW, $this->cObj());

        self::assertSame([['uid' => 3, 'itemTitle' => 'Child']], $result['data']['items']);
        self::assertSame('2026-11-03T10:00:00+00:00', $result['data']['date']);
    }
}

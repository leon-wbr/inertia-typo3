<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Unit\Support;

use LeonWbr\Inertia\Support\Key;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

enum KeyTestBacked: string
{
    case Countries = 'countries-v2';
}

enum KeyTestBackedInt: int
{
    case Seven = 7;
}

enum KeyTestPure
{
    case Countries;
}

final class KeyTest extends UnitTestCase
{
    #[Test]
    public function stringsAreKeptAsTheyAre(): void
    {
        self::assertSame('status', Key::from('status'));
    }

    #[Test]
    public function backedEnumsUseTheirValue(): void
    {
        self::assertSame('countries-v2', Key::from(KeyTestBacked::Countries));
        self::assertSame('7', Key::from(KeyTestBackedInt::Seven));
    }

    #[Test]
    public function pureEnumsUseTheirName(): void
    {
        self::assertSame('Countries', Key::from(KeyTestPure::Countries));
    }
}

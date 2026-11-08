<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Unit\Fixtures;

use LeonWbr\Inertia\Props\Onceable;
use LeonWbr\Inertia\Props\ResolvesOnce;

/**
 * A once prop whose clock is fixed, so expiry times are exact.
 */
final class FixedClockOnceProp implements Onceable
{
    use ResolvesOnce;

    public function __construct(
        private readonly int $now,
    ) {
        $this->once = true;
    }

    /**
     * @return list<string>
     */
    public function __invoke(): array
    {
        return [];
    }

    protected function now(): int
    {
        return $this->now;
    }
}

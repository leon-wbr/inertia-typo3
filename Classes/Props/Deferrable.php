<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

/**
 * A prop that the client loads in a follow-up request right after the page
 * has rendered. Props in the same group are fetched together.
 */
interface Deferrable
{
    public function group(): string;
}

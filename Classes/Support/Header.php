<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Support;

/**
 * Inertia protocol headers, as in inertia-laravel 3.x (Inertia\Support\Header).
 */
final class Header
{
    public const INERTIA = 'X-Inertia';
    public const ERROR_BAG = 'X-Inertia-Error-Bag';
    public const LOCATION = 'X-Inertia-Location';
    public const REDIRECT = 'X-Inertia-Redirect';
    public const VERSION = 'X-Inertia-Version';
    public const PARTIAL_COMPONENT = 'X-Inertia-Partial-Component';
    public const PARTIAL_ONLY = 'X-Inertia-Partial-Data';
    public const PARTIAL_EXCEPT = 'X-Inertia-Partial-Except';
    public const RESET = 'X-Inertia-Reset';
}

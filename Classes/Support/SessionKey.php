<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Support;

/**
 * Keys in the TYPO3 frontend user session (Inertia\Support\SessionKey).
 */
final class SessionKey
{
    /** Validation errors by error bag, kept until the next page is rendered. */
    public const ERRORS = 'inertia.errors';

    /** Flash data, kept until the next page is rendered. */
    public const FLASH_DATA = 'inertia.flash_data';
}

<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Props;

/**
 * Marks a prop that is never sent on the first page load. It is only
 * resolved when a partial reload asks for it by name.
 */
interface IgnoreFirstLoad {}

<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Query parameters of the example pages. They only change the uncached
// Inertia page object, so they don't need a cHash.
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] = array_values(array_unique(array_merge(
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] ?? [],
    ['page', 'sent', 'error'],
)));

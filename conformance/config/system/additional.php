<?php

declare(strict_types=1);

/**
 * Settings for the conformance project, on top of settings.php (written by
 * `typo3 setup`, not committed). It has its own database next to the
 * example's, in DDEV's MariaDB.
 */
$GLOBALS['TYPO3_CONF_VARS'] = array_replace_recursive($GLOBALS['TYPO3_CONF_VARS'], [
    'DB' => [
        'Connections' => [
            'Default' => [
                'driver' => 'mysqli',
                'host' => getenv('CONFORMANCE_DB_HOST') ?: 'db',
                'port' => (int)(getenv('CONFORMANCE_DB_PORT') ?: 3306),
                'dbname' => getenv('CONFORMANCE_DB_DBNAME') ?: 'conformance',
                'user' => getenv('CONFORMANCE_DB_USERNAME') ?: 'db',
                'password' => getenv('CONFORMANCE_DB_PASSWORD') ?: 'db',
                'charset' => 'utf8mb4',
                'tableoptions' => [
                    'charset' => 'utf8mb4',
                    'collate' => 'utf8mb4_unicode_ci',
                ],
            ],
        ],
    ],
    'SYS' => [
        'trustedHostsPattern' => '.*',
        'devIPmask' => '*',
        'displayErrors' => 1,
    ],
    'EXTENSIONS' => [
        'inertia' => [
            // Every client build writes a manifest; the React one is the
            // asset version, which all clients share.
            'manifestPath' => '_assets/conformance/react/.vite/manifest.json',
        ],
    ],
]);

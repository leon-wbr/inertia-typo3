<?php

declare(strict_types=1);

/**
 * Settings for the example project, on top of settings.php (written by
 * `typo3 setup`, not committed). Defaults match DDEV; CI uses DDEV too.
 */
$GLOBALS['TYPO3_CONF_VARS'] = array_replace_recursive($GLOBALS['TYPO3_CONF_VARS'], [
    'DB' => [
        'Connections' => [
            'Default' => [
                'driver' => 'mysqli',
                'host' => getenv('TYPO3_DB_HOST') ?: 'db',
                'port' => (int)(getenv('TYPO3_DB_PORT') ?: 3306),
                'dbname' => getenv('TYPO3_DB_DBNAME') ?: 'db',
                'user' => getenv('TYPO3_DB_USERNAME') ?: 'db',
                'password' => getenv('TYPO3_DB_PASSWORD') ?: 'db',
                'charset' => 'utf8mb4',
                'tableoptions' => [
                    'charset' => 'utf8mb4',
                    'collate' => 'utf8mb4_unicode_ci',
                ],
            ],
        ],
    ],
    'MAIL' => [
        'transport' => 'smtp',
        'transport_smtp_server' => 'localhost:1025',
    ],
    'SYS' => [
        'trustedHostsPattern' => '.*',
        'devIPmask' => '*',
        'displayErrors' => 1,
    ],
    'EXTENSIONS' => [
        'vite_asset_collector' => [
            // The Vite dev server writes var/vite-dev-server while it runs
            // (see vite.config.ts). Without it, TYPO3 serves the production
            // build from public/_assets/vite.
            'useDevServer' => is_file(dirname(__DIR__, 2) . '/var/vite-dev-server') ? '1' : '0',
        ],
    ],
]);

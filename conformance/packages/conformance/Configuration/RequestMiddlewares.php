<?php

declare(strict_types=1);

use LeonWbr\InertiaConformance\Middleware\CaseEndpoints;
use LeonWbr\InertiaConformance\Middleware\InertiaMiddleware;

return [
    'frontend' => [
        // Same identifier as the adapter's middleware: replaces its target.
        'leon-wbr/inertia' => [
            'target' => InertiaMiddleware::class,
        ],
        // Inside the adapter's middleware, before TYPO3 resolves a page:
        // the endpoints are not pages.
        'leon-wbr/inertia-conformance/endpoints' => [
            'target' => CaseEndpoints::class,
            'after' => [
                'leon-wbr/inertia',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];

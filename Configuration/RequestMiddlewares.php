<?php

declare(strict_types=1);

use LeonWbr\Inertia\Middleware\InertiaMiddleware;

return [
    'frontend' => [
        'leon-wbr/inertia' => [
            'target' => InertiaMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
                // The frontend user session holds flashed errors and data.
                'typo3/cms-frontend/authentication',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];

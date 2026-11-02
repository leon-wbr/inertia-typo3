<?php

declare(strict_types=1);

use LeonWbr\InertiaSitepackage\Middleware\ContactFormMiddleware;
use LeonWbr\InertiaSitepackage\Middleware\InertiaMiddleware;

return [
    'frontend' => [
        // Same identifier as the adapter's middleware: replaces its target.
        'leon-wbr/inertia' => [
            'target' => InertiaMiddleware::class,
        ],
        'leon-wbr/inertia-sitepackage/contact-form' => [
            'target' => ContactFormMiddleware::class,
            // frontend.page.information is available from here on.
            'after' => [
                'typo3/cms-frontend/prepare-tsfe-rendering',
            ],
        ],
    ],
];

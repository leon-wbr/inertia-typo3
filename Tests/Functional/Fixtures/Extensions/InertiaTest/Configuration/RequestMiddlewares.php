<?php

declare(strict_types=1);

use LeonWbr\Inertia\Tests\Functional\Fixtures\Extensions\InertiaTest\Classes\FormMiddleware;

return [
    'frontend' => [
        'leon-wbr/inertia-test/form' => [
            'target' => FormMiddleware::class,
            'after' => [
                'leon-wbr/inertia',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];

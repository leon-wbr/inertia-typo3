<?php

declare(strict_types=1);

namespace LeonWbr\Inertia\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractFunctionalTestCase extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['fluid_styled_content'];

    protected array $testExtensionsToLoad = ['leon-wbr/inertia-typo3'];

    protected array $configurationToUseInTestInstance = [
        'FE' => [
            'cacheHash' => [
                'excludedParameters' => ['q', 'page'],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');

        $siteDirectory = $this->instancePath . '/typo3conf/sites/test';
        if (!is_dir($siteDirectory)) {
            mkdir($siteDirectory, 0o777, true);
        }
        copy(__DIR__ . '/Fixtures/site.yaml', $siteDirectory . '/config.yaml');
    }
}

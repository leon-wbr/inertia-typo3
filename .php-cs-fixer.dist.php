<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/Classes',
        __DIR__ . '/Configuration',
        __DIR__ . '/Tests',
        __DIR__ . '/Build',
        __DIR__ . '/example/packages/sitepackage/Classes',
        __DIR__ . '/example/packages/sitepackage/Configuration',
        __DIR__ . '/conformance/packages/conformance/Classes',
        __DIR__ . '/conformance/packages/conformance/Configuration',
    ])
    ->append([
        __DIR__ . '/ext_localconf.php',
        __DIR__ . '/example/config/system/additional.php',
        __DIR__ . '/example/packages/sitepackage/ext_localconf.php',
        __DIR__ . '/conformance/config/system/additional.php',
        __DIR__ . '/conformance/packages/conformance/ext_localconf.php',
        __FILE__,
    ]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/example/var/cache/php-cs-fixer.cache')
    ->setRules([
        '@PER-CS3x0' => true,
        '@PHP8x2Migration' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'cast_spaces' => ['space' => 'none'],
    ])
    ->setFinder($finder);

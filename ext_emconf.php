<?php

declare(strict_types=1);

// Only for the TYPO3 Extension Repository (TER): TYPO3 14 reads composer.json.
// TER takes the category and the state from here. `tailor set-version` keeps
// the version in line with composer.json; the publish workflow checks both
// against the tag.
$EM_CONF[$_EXTKEY] = [
    'title' => 'Inertia.js adapter',
    'description' => 'The Inertia.js 3 server adapter for TYPO3: TYPO3 keeps routing, languages and content; React, Vue or Svelte render the pages.',
    'category' => 'fe',
    'state' => 'beta',
    'author' => 'Leon Weber',
    'author_email' => 'lw.leonweber@gmail.com',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '14.3.10-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];

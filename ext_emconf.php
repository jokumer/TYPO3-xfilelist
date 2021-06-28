<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TYPO3 filelist',
    'description' => 'Extends TYPO3 filelist and filebrowser for search within metadata, adds pagination and sorting to filebrowser',
    'category' => 'backend',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'author' => 'J.Kummer',
    'version' => '4.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-10.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'Jokumer\\Xfilelist\\' => 'Classes',
        ],
    ]
];

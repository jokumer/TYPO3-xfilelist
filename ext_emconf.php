<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TYPO3 filelist',
    'description' => 'Extends TYPO3 filelist and filebrowser for search within metadata, adds pagination and sorting to filebrowser',
    'category' => 'backend',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'author' => 'J.Kummer',
    'author_company' => 'jokumer',
    'version' => '3.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-9.5.99',
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

<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TYPO3 filelist',
    'description' => 'Extends TYPO3 filelist and filebrowser for search within metadata, adds pagination and sorting to filebrowser',
    'category' => 'backend',
    'shy' => 0,
    'dependencies' => '',
    'conflicts' => '',
    'priority' => '',
    'loadOrder' => '',
    'module' => '',
    'state' => 'stable',
    'internal' => '',
    'uploadfolder' => 0,
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'author' => 'Joerg Kummer',
    'author_email' => 'typo3 et enobe dot de',
    'author_company' => 'enobe.de',
    'CGLcompliance' => '',
    'CGLcompliance_note' => '',
    'version' => '2.1.0',
    '_md5_values_when_last_written' => '',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-8.7.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'Jokumer\\Xfilelist\\' => 'Classes',
        ],
    ],
];

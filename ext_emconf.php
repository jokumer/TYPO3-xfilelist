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
    'version' => '1.1.1',
    '_md5_values_when_last_written' => '',
    'constraints' => [
        'depends' => [
            'typo3' => '7.6.0-7.6.99',
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

<?php
defined('TYPO3_MODE') or die();

// XClass Core\Resource\FileRepository for file search within metadata
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Resource\\FileRepository'] = [
    'className' => 'Jokumer\\Xfilelist\\Xclass\\FileRepositoryXclass',
];
// XClass Recordlist\Browser\FileBrowser for filelist including pagination
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Recordlist\\Browser\\FileBrowser'] = [
    'className' => 'Jokumer\\Xfilelist\\Xclass\\FileBrowserXclass',
];
// XClass Filelist\FileList to adapt settings
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Filelist\\FileList'] = [
    'className' => 'Jokumer\\Xfilelist\\Xclass\\FileListXclass'
];

<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

// XClass Core\Resource\FileRepository for file search within metadata
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Core\\Resource\\FileRepository'] = array(
    'className' => 'Jokumer\\Xfilelist\\Xclass\\FileRepositoryXclass',
);
// XClass Recordlist\Browser\FileBrowser for filelist including pagination
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Recordlist\\Browser\\FileBrowser'] = array(
    'className' => 'Jokumer\\Xfilelist\\Xclass\\FileBrowserXclass',
);
// XClass Filelist\FileList to adapt settings
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\\CMS\\Filelist\\FileList'] = array(
    'className' => 'Jokumer\\Xfilelist\\Xclass\\FileListXclass'
);

<?php
namespace Jokumer\Xfilelist\Xclass;

use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Recordlist\Browser\FileList;
use TYPO3\CMS\Recordlist\View\FolderUtilityRenderer;

/**
 * Class FileBrowserXclass
 *
 * @package TYPO3
 * @subpackage tx_xfilelist
 * @author 2017 J.Kummer <typo3 et enobe dot de>, enobe.de
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class FileBrowserXclass extends \TYPO3\CMS\Recordlist\Browser\FileBrowser
{

    /**
     * Pointer to listing
     *
     * @var int
     */
    public $pointer;

    /**
     * Initialize browser
     * 
     * @return void
     */
    public function initializeBrowser() {
        $this->pointer = GeneralUtility::_GP('pointer');
    }

    /**
     * For TYPO3 Element Browser: Expand folder of files.
     *
     * @param Folder $folder The folder path to expand
     * @param array $extensionList List of fileextensions to show (allowedFileExtension filters set in TYPO3\CMS\Recordlist\Browser\FileBrowser::render)
     * @param bool $noThumbs Whether to show thumbnails or not. If set, no thumbnails are shown.
     * @return string HTML output
     */
    public function renderFilesInFolder(Folder $folder, array $extensionList = [], $noThumbs = false)
    {
        if (!$folder->checkActionPermission('read')) {
            return '';
        }
        $this->initializeBrowser();
        $lang = $this->getLanguageService();
        // Get search box
        $outSearchField = GeneralUtility::makeInstance(FolderUtilityRenderer::class, $this)->getFileSearchField($this->searchWord);
        // Get file list, sets also class variables
        if ($this->searchWord !== '') {
            $files = $this->fileRepository->searchByName($folder, $this->searchWord, $extensionList);
        } else {
            $files = $this->getFilesInFolder($folder, $extensionList);
        }
        $outFilelist = $this->getFilelist($folder, $files, !$noThumbs);
        // Get checkbox 'show thumbs'
        $outBulkSelector = $this->getBulkSelector(count($this->elements));
        // Get output
        $out = '<h3>' . $lang->getLL('files', true) . ' ' . $this->totalItems . ':</h3>';
        $out .= $outSearchField;
        $out .= '<div id="filelist">' . $outBulkSelector . $outFilelist . '</div>';
        return $out;
    }

    /**
     * Get file list
     * Using a modified version of \TYPO3\CMS\Filelist\FileList CMS 7.6.15
     *
     * @param Folder $folder The folder path to render file list for
     * @param array $files Array of file objects
     * @param bool $thumbs Whether to show thumbnails or not. If set, thumbnails are shown.
     * @return string $code
     */
    public function getFilelist(Folder $folderObject, array $files, $thumbs = false) {
        /** @var RecordListBrowserFileList; $fileList */
        $fileList = GeneralUtility::makeInstance(\Jokumer\Xfilelist\RecordListBrowserFileList::class);
        $fileList->thumbs = $thumbs;
        $fileList->start($folderObject, $this->pointer, false, false, false, true);
        $rowList = 'fileext,tstamp,size';
        $code = $fileList->getFilelist($files, $rowList);
        $this->elements = $fileList->getDataElements();
        $this->totalItems = $fileList->getTotalItemsCount();
        return $code;
    }

}

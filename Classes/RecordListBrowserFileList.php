<?php
namespace Jokumer\Xfilelist;

use Jokumer\Xfilelist\AbstractRecordList;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Clipboard\Clipboard;
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\FolderInterface;
use TYPO3\CMS\Core\Resource\InaccessibleFolder;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Utility\ListUtility;
use TYPO3\CMS\Core\Type\Bitmask\JsConfirmation;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Filelist\Controller\FileListController;

/**
 * Class RecordListBrowserFileList
 * Copy from \TYPO3\CMS\Filelist\FileList
 * To use nearly same codebase from Filelist\Filelist also in RecordList\Browser\FileBrowser
 * Extends deprecated \TYPO3\CMS\Backend\RecordList\AbstractRecordList which was copied into this extension
 *
 * @package TYPO3
 * @subpackage tx_xfilelist
 * @author J.Kummer
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class RecordListBrowserFileList extends AbstractRecordList
{
    /**
     * The mode determines the main kind of output of the element browser.
     *
     * There are these options for values:
     *  - "db" will allow you to browse for pages or records in the page tree for FormEngine select fields
     *  - "file" will allow you to browse for files in the folder mounts for FormEngine file selections
     *  - "folder" will allow you to browse for folders in the folder mounts for FormEngine folder selections
     *  - Other options may be registered via extensions
     *
     * @var string
     */
    protected $mode;

    /**
     * Default Max items shown
     *
     * @var int
     */
    public $iLimit = 40;

    /**
     * Thumbnails on records containing files (pictures)
     *
     * @var bool
     */
    public $thumbs = false;

    /**
     * Space icon used for alignment when no button is available
     *
     * @var string
     */
    public $spaceIcon;

    /**
     * Max length of strings
     *
     * @var int
     */
    public $fixedL = 30;

    /**
     * Pointer to listing
     *
     * @var int
     */
    public $pointer;

    /**
     * Search word
     *
     * @var string
     */
    public $searchWord = '';

    /**
     * The field to sort by
     *
     * @var string
     */
    public $sort = '';

    /**
     * Reverse sorting flag
     *
     * @var bool
     */
    public $sortRev = 1;

    /**
     * @var int
     */
    public $firstElementNumber = 0;

    /**
     * @var bool
     */
    public $clipBoard = 0;

    /**
     * @var bool
     */
    public $bigControlPanel = 0;

    /**
     * @var string
     */
    public $JScode = '';

    /**
     * @var string
     */
    public $HTMLcode = '';

    /**
     * @var int
     */
    public $totalbytes = 0;

    /**
     * @var array
     */
    public $dirs = [];

    /**
     * @var array
     */
    public $files = [];

    /**
     * @var string
     */
    public $path = '';

    /**
     * @var Folder
     */
    protected $folderObject;

    /**
     * Counting the elements no matter what
     *
     * @var int
     */
    public $eCounter = 0;

    /**
     * @var string
     */
    public $totalItems = '';

    /**
     * @var array
     */
    public $CBnames = [];

    /**
     * @var Clipboard $clipObj
     */
    public $clipObj;

    /**
     * @var ResourceFactory
     */
    protected $resourceFactory;

    /**
     * @param ResourceFactory $resourceFactory
     */
    public function injectResourceFactory(ResourceFactory $resourceFactory)
    {
        $this->resourceFactory = $resourceFactory;
    }

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * @var FileListController
     */
    protected $fileListController;

    /**
     * Holds information about files, for body tag data-elements
     *
     * @var mixed[][]
     */
    protected $dataElements = [];

    /**
     * Current file index
     *
     * @var integer
     */
    protected $dataFileIndex = 0;

    /**
     * Construct
     */
    public function __construct()
    {
        parent::__construct();
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $modTSconfig['properties'] = BackendUtility::getPagesTSconfig($pid)['options.']['file_list.'] ?? [];
        if (!empty($modTSconfig['properties']['filesPerPage'])) {
            $this->iLimit = MathUtility::forceIntegerInRange($modTSconfig['properties']['filesPerPage'], 1);
        }
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('xfilelist');
        if ($extensionConfiguration['fileListConfiguration']) {
            // Set default max items shown
            if (isset($extensionConfiguration['fileListConfiguration_iLimit']) && (int) $extensionConfiguration['fileListConfiguration_iLimit'] > 0) {
                $this->iLimit = $extensionConfiguration['fileListConfiguration_iLimit'];
            }
            // Set max length of file title
            if (isset($extensionConfiguration['fileListConfiguration_fixedL']) && (int) $extensionConfiguration['fileListConfiguration_fixedL'] > 0) {
                $this->fixedL = $extensionConfiguration['fileListConfiguration_fixedL'];
            }
        }
        $this->mode = GeneralUtility::_GP('mode');
        $this->pointer = GeneralUtility::_GP('pointer');
        $this->searchWord = GeneralUtility::_GP('searchWord');
        $this->sort = GeneralUtility::_GP('sort');
        $this->sortRev = GeneralUtility::_GP('sortRev');
    }

    /**
     * Initialization of class
     *
     * @param Folder $folderObject The folder to work on
     * @param int $pointer Pointer
     * @param bool $sort Sorting column
     * @param bool $sortRev Sorting direction
     * @param bool $clipBoard
     * @param bool $bigControlPanel Show clipboard flag
     */
    public function start(Folder $folderObject, $pointer, $sort, $sortRev, $clipBoard = false, $bigControlPanel = false)
    {
        $this->folderObject = $folderObject;
        $this->counter = 0;
        $this->totalbytes = 0;
        $this->JScode = '';
        $this->HTMLcode = '';
        $this->path = $folderObject->getReadablePath();
        $this->sort = $sort;
        $this->sortRev = $sortRev;
        $this->firstElementNumber = $pointer;
        $this->clipBoard = $clipBoard;
        $this->bigControlPanel = $bigControlPanel;
        // Setting the maximum length of the filenames to the user's settings or minimum 30 (= $this->fixedL)
        $this->fixedL = max($this->fixedL, $this->getBackendUser()->uc['titleLen']);
        $this->getLanguageService()->includeLLFile('EXT:core/Resources/Private/Language/locallang_common.xlf');
        $this->resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
    }

    /**
     * Reading files and directories, counting elements and generating the list in ->HTMLcode
     */
    public function generateList()
    {
        $this->HTMLcode .= $this->getTable('fileext,modification_date,size,rw,_REF_');
    }

    /**
     * Wrapping input string in a link with clipboard command.
     *
     * @param string $string String to be linked - must be htmlspecialchar'ed / prepared before.
     * @param string $_ unused
     * @param string $cmd "cmd" value
     * @param string $warning Warning for JS confirm message
     * @return string Linked string
     */
    public function linkClipboardHeaderIcon($string, $_, $cmd, $warning = '')
    {
        $jsCode = 'document.dblistForm.cmd.value=' . GeneralUtility::quoteJSvalue($cmd)
            . ';document.dblistForm.submit();';

        $attributes = [];
        if ($warning) {
            $attributes['class'] = 'btn btn-default t3js-modal-trigger';
            $attributes['data-href'] = 'javascript:' . $jsCode;
            $attributes['data-severity'] = 'warning';
            $attributes['data-content'] = $warning;
        } else {
            $attributes['class'] = 'btn btn-default';
            $attributes['onclick'] = $jsCode . 'return false;';
        }

        $attributesString = '';
        foreach ($attributes as $key => $value) {
            $attributesString .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
        }
        return '<a href="#" ' . $attributesString . '>' . $string . '</a>';
    }

    /**
     * Returns a table with directories and files listed.
     *
     * @param array $rowlist Array of files from path
     * @return string HTML-table
     */
    public function getTable($rowlist)
    {
        // prepare space icon
        $this->spaceIcon = '<span class="btn btn-default disabled">' . $this->iconFactory->getIcon('empty-empty', Icon::SIZE_SMALL)->render() . '</span>';

        // @todo use folder methods directly when they support filters
        $storage = $this->folderObject->getStorage();
        $storage->resetFileAndFolderNameFiltersToDefault();

        // Only render the contents of a browsable storage
        if ($this->folderObject->getStorage()->isBrowsable()) {
            try {
                $foldersCount = $storage->countFoldersInFolder($this->folderObject);
                $filesCount = $storage->countFilesInFolder($this->folderObject);
            } catch (InsufficientFolderAccessPermissionsException $e) {
                $foldersCount = 0;
                $filesCount = 0;
            }

            if ($foldersCount <= $this->firstElementNumber) {
                $foldersFrom = false;
                $foldersNum = false;
            } else {
                $foldersFrom = $this->firstElementNumber;
                if ($this->firstElementNumber + $this->iLimit > $foldersCount) {
                    $foldersNum = $foldersCount - $this->firstElementNumber;
                } else {
                    $foldersNum = $this->iLimit;
                }
            }
            if ($foldersCount >= $this->firstElementNumber + $this->iLimit) {
                $filesFrom = false;
                $filesNum  = false;
            } else {
                if ($this->firstElementNumber <= $foldersCount) {
                    $filesFrom = 0;
                    $filesNum  = $this->iLimit - $foldersNum;
                } else {
                    $filesFrom = $this->firstElementNumber - $foldersCount;
                    if ($filesFrom + $this->iLimit > $filesCount) {
                        $filesNum = $filesCount - $filesFrom;
                    } else {
                        $filesNum  = $this->iLimit;
                    }
                }
            }

            $folders = $storage->getFoldersInFolder($this->folderObject, $foldersFrom, $foldersNum, true, false, trim($this->sort), (bool)$this->sortRev);
            $files = $this->folderObject->getFiles($filesFrom, $filesNum, Folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, false, trim($this->sort), (bool)$this->sortRev);
            $this->totalItems = $foldersCount + $filesCount;
            // Adds the code of files/dirs
            $out = '';
            $titleCol = 'file';
            // Cleaning rowlist for duplicates and place the $titleCol as the first column always!
            $rowlist = '_LOCALIZATION_,' . $rowlist;
            $rowlist = GeneralUtility::rmFromList($titleCol, $rowlist);
            $rowlist = GeneralUtility::uniqueList($rowlist);
            $rowlist = $rowlist ? $titleCol . ',' . $rowlist : $titleCol;
            if ($this->clipBoard) {
                $rowlist = str_replace('_LOCALIZATION_,', '_LOCALIZATION_,_CLIPBOARD_,', $rowlist);
                $this->addElement_tdCssClass['_CLIPBOARD_'] = 'col-clipboard';
            }
            if ($this->bigControlPanel) {
                $rowlist = str_replace('_LOCALIZATION_,', '_LOCALIZATION_,_CONTROL_,', $rowlist);
                $this->addElement_tdCssClass['_CONTROL_'] = 'col-control';
            }
            $this->fieldArray = explode(',', $rowlist);

            // Add classes to table cells
            $this->addElement_tdCssClass[$titleCol] = 'col-title col-responsive';
            $this->addElement_tdCssClass['_LOCALIZATION_'] = 'col-localizationa';

            $folders = ListUtility::resolveSpecialFolderNames($folders);

            $iOut = '';
            // Directories are added
            $this->eCounter = $this->firstElementNumber;
            list(, $code) = $this->fwd_rwd_nav();
            $iOut .= $code;

            $iOut .= $this->formatDirList($folders);
            // Files are added
            $iOut .= $this->formatFileList($files);

            $this->eCounter = $this->firstElementNumber + $this->iLimit < $this->totalItems
                ? $this->firstElementNumber + $this->iLimit
                : -1;
            list(, $code) = $this->fwd_rwd_nav();
            $iOut .= $code;

            // Header line is drawn
            $theData = [];
            foreach ($this->fieldArray as $v) {
                if ($v === '_CLIPBOARD_' && $this->clipBoard) {
                    $cells = [];
                    $table = '_FILE';
                    $elFromTable = $this->clipObj->elFromTable($table);
                    if (!empty($elFromTable) && $this->folderObject->checkActionPermission('write')) {
                        $addPasteButton = true;
                        $elToConfirm = [];
                        foreach ($elFromTable as $key => $element) {
                            $clipBoardElement = $this->resourceFactory->retrieveFileOrFolderObject($element);
                            if ($clipBoardElement instanceof Folder && $clipBoardElement->getStorage()->isWithinFolder($clipBoardElement, $this->folderObject)) {
                                $addPasteButton = false;
                            }
                            $elToConfirm[$key] = $clipBoardElement->getName();
                        }
                        if ($addPasteButton) {
                            $cells[] = '<a class="btn btn-default t3js-modal-trigger"' .
                                ' href="' . htmlspecialchars($this->clipObj->pasteUrl(
                                    '_FILE',
                                    $this->folderObject->getCombinedIdentifier()
                                )) . '"'
                                . ' data-content="' . htmlspecialchars($this->clipObj->confirmMsgText(
                                    '_FILE',
                                    $this->path,
                                    'into',
                                    $elToConfirm
                                )) . '"'
                                . ' data-severity="warning"'
                                . ' data-title="' . htmlspecialchars($this->getLanguageService()->getLL('clip_paste')) . '"'
                                . ' title="' . htmlspecialchars($this->getLanguageService()->getLL('clip_paste')) . '">'
                                . $this->iconFactory->getIcon('actions-document-paste-into', Icon::SIZE_SMALL)
                                    ->render()
                                . '</a>';
                        }
                    }
                    if ($this->clipObj->current !== 'normal' && $iOut) {
                        $cells[] = $this->linkClipboardHeaderIcon('<span title="' . htmlspecialchars($this->getLanguageService()->getLL('clip_selectMarked')) . '">' . $this->iconFactory->getIcon('actions-edit-copy', Icon::SIZE_SMALL)->render() . '</span>', $table, 'setCB');
                        $cells[] = $this->linkClipboardHeaderIcon('<span title="' . htmlspecialchars($this->getLanguageService()->getLL('clip_deleteMarked')) . '">' . $this->iconFactory->getIcon('actions-edit-delete', Icon::SIZE_SMALL)->render(), $table, 'delete', $this->getLanguageService()->getLL('clip_deleteMarkedWarning'));
                        $onClick = 'checkOffCB(' . GeneralUtility::quoteJSvalue(implode(',', $this->CBnames)) . ', this); return false;';
                        $cells[] = '<a class="btn btn-default" rel="" href="#" onclick="' . htmlspecialchars($onClick) . '" title="' . htmlspecialchars($this->getLanguageService()->getLL('clip_markRecords')) . '">' . $this->iconFactory->getIcon('actions-document-select', Icon::SIZE_SMALL)->render() . '</a>';
                    }
                    $theData[$v] = implode('', $cells);
                } else {
                    // Normal row:
                    $theT = $this->linkWrapSort(htmlspecialchars($this->getLanguageService()->getLL('c_' . $v)), $this->folderObject->getCombinedIdentifier(), $v);
                    $theData[$v] = $theT;
                }
            }

            $out .= '<thead>' . $this->addElement(1, '', $theData, '', '', '', 'th') . '</thead>';
            $out .= '<tbody>' . $iOut . '</tbody>';
            // half line is drawn
            // finish
            $out = '
        <!--
            Filelist table:
        -->
            <div class="table-fit">
                <table class="table table-striped table-hover" id="typo3-filelist">
                    ' . $out . '
                </table>
            </div>';
        } else {
            /** @var $flashMessage FlashMessage */
            $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $this->getLanguageService()->getLL('storageNotBrowsableMessage'), $this->getLanguageService()->getLL('storageNotBrowsableTitle'), FlashMessage::INFO);
            /** @var $flashMessageService \TYPO3\CMS\Core\Messaging\FlashMessageService */
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            /** @var $defaultFlashMessageQueue \TYPO3\CMS\Core\Messaging\FlashMessageQueue */
            $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $defaultFlashMessageQueue->enqueue($flashMessage);
            $out = '';
        }
        return $out;
    }

    /**
     * If there is a parent folder and user has access to it, return an icon
     * which is linked to the filelist of the parent folder.
     *
     * @param Folder $currentFolder
     * @return string
     */
    protected function getLinkToParentFolder(Folder $currentFolder)
    {
        $levelUp = '';
        try {
            $currentStorage = $currentFolder->getStorage();
            $parentFolder = $currentFolder->getParentFolder();
            if ($parentFolder->getIdentifier() !== $currentFolder->getIdentifier() && $currentStorage->isWithinFileMountBoundaries($parentFolder)) {
                $levelUp = $this->linkWrapDir(
                    '<span title="' . htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.upOneLevel')) . '">'
                    . $this->iconFactory->getIcon('actions-view-go-up', Icon::SIZE_SMALL)->render()
                    . '</span>',
                    $parentFolder
                );
            }
        } catch (\Exception $e) {
        }
        return $levelUp;
    }

    /**
     * Gets the number of files and total size of a folder
     *
     * @return string
     */
    public function getFolderInfo()
    {
        if ($this->counter == 1) {
            $fileLabel = htmlspecialchars($this->getLanguageService()->getLL('file'));
        } else {
            $fileLabel = htmlspecialchars($this->getLanguageService()->getLL('files'));
        }
        return $this->counter . ' ' . $fileLabel . ', ' . GeneralUtility::formatSize($this->totalbytes, htmlspecialchars($this->getLanguageService()->getLL('byteSizeUnits')));
    }

    /**
     * This returns tablerows for the directories in the array $items['sorting'].
     *
     * @param Folder[] $folders Folders of \TYPO3\CMS\Core\Resource\Folder
     * @return string HTML table rows.
     */
    public function formatDirList(array $folders)
    {
        $out = '';
        foreach ($folders as $folderName => $folderObject) {
            $role = $folderObject->getRole();
            if ($role === FolderInterface::ROLE_PROCESSING) {
                // don't show processing-folder
                continue;
            }
            if ($role !== FolderInterface::ROLE_DEFAULT) {
                $displayName = '<strong>' . htmlspecialchars($folderName) . '</strong>';
            } else {
                $displayName = htmlspecialchars($folderName);
            }

            $isLocked = $folderObject instanceof InaccessibleFolder;
            $isWritable = $folderObject->checkActionPermission('write');

            // Initialization
            $this->counter++;

            // The icon with link
            $theIcon = '<span title="' . htmlspecialchars($folderName) . '">' . $this->iconFactory->getIconForResource($folderObject, Icon::SIZE_SMALL)->render() . '</span>';
            if (!$isLocked) {
                $theIcon = BackendUtility::wrapClickMenuOnIcon($theIcon, 'sys_file', $folderObject->getCombinedIdentifier());
            }

            // Preparing and getting the data-array
            $theData = [];
            if ($isLocked) {
                foreach ($this->fieldArray as $field) {
                    $theData[$field] = '';
                }
                $theData['file'] = $displayName;
            } else {
                foreach ($this->fieldArray as $field) {
                    switch ($field) {
                        case 'size':
                            try {
                                $numFiles = $folderObject->getFileCount();
                            } catch (InsufficientFolderAccessPermissionsException $e) {
                                $numFiles = 0;
                            }
                            $theData[$field] = $numFiles . ' ' . htmlspecialchars($this->getLanguageService()->getLL(($numFiles === 1 ? 'file' : 'files')));
                            break;
                        case 'rw':
                            $theData[$field] = '<strong class="text-danger">' . htmlspecialchars($this->getLanguageService()->getLL('read')) . '</strong>' . (!$isWritable ? '' : '<strong class="text-danger">' . htmlspecialchars($this->getLanguageService()->getLL('write')) . '</strong>');
                            break;
                        case 'fileext':
                            $theData[$field] = htmlspecialchars($this->getLanguageService()->getLL('folder'));
                            break;
                        case 'modification_date':
                            $modificationDate = $folderObject->getModificationTime();
                            $theData[$field] = $modificationDate ? BackendUtility::date($modificationDate) : '-';
                            break;
                        case 'file':
                            $theData[$field] = $this->linkWrapDir($displayName, $folderObject);
                            break;
                        case '_CONTROL_':
                            $theData[$field] = $this->makeControl($folderObject);
                            break;
                        case '_CLIPBOARD_':
                            $theData[$field] = $this->makeClip($folderObject);
                            break;
                        case '_REF_':
                            $theData[$field] = $this->makeRef($folderObject);
                            break;
                        default:
                            $theData[$field] = GeneralUtility::fixed_lgd_cs($theData[$field], $this->fixedL);
                    }
                }
            }
            $out .= $this->addElement(1, $theIcon, $theData);
        }
        return $out;
    }

    /**
     * Wraps the directory-titles
     *
     * @param string $title String to be wrapped in links
     * @param Folder $folderObject Folder to work on
     * @return string HTML
     */
    public function linkWrapDir($title, Folder $folderObject)
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $href = (string)$uriBuilder->buildUriFromRoute('file_FilelistList', ['id' => $folderObject->getCombinedIdentifier()]);
        $onclick = ' onclick="' . htmlspecialchars(('top.document.getElementsByName("navigation")[0].contentWindow.Tree.highlightActiveItem("file","folder' . GeneralUtility::md5int($folderObject->getCombinedIdentifier()) . '_"+top.fsMod.currentBank)')) . '"';
        // Sometimes $code contains plain HTML tags. In such a case the string should not be modified!
        if ((string)$title === strip_tags($title)) {
            return '<a href="' . htmlspecialchars($href) . '"' . $onclick . ' title="' . htmlspecialchars($title) . '">' . $title . '</a>';
        } else {
            return '<a href="' . htmlspecialchars($href) . '"' . $onclick . '>' . $title . '</a>';
        }
    }

    /**
     * Wraps filenames in links which opens the metadata editor.
     *
     * @param string $code String to be wrapped in links
     * @param File $fileObject File to be linked
     * @return string HTML
     */
    public function linkWrapFile($code, File $fileObject)
    {
        try {
            if ($fileObject instanceof File && $fileObject->isIndexed()
                && $fileObject->checkActionPermission('write')
                && $this->getBackendUser()->check('tables_modify', 'sys_file_metadata')) {
                $code = '<a href="#" title="' . htmlspecialchars($fileObject->getName()) . '" data-file-index="' . $this->dataFileIndex . '" data-close="1">' . GeneralUtility::fixed_lgd_cs($code, $this->fixedL) . '</a>';
            }
        } catch (\Exception $e) {
            // intentional fall-through
        }
        return $code;
    }

    /**
     * Returns list URL; This is the URL of the current script with id and imagemode parameters, that's all.
     * The URL however is not relative, otherwise GeneralUtility::sanitizeLocalUrl() would say that
     * the URL would be invalid
     *
     * @param string $altId
     * @param string $table Table name to display. Enter "-1" for the current table.
     * @param string $exclList Comma separated list of fields NOT to include ("sortField", "sortRev" or "firstElementNumber")
     * @param array $overrideParams
     *
     * @return string URL
     */
    public function listURL($altId = '', $table = '-1', $exclList = '', $overrideParams = [])
    {
        $params = [
            'target' => rawurlencode($this->folderObject->getCombinedIdentifier()),
            'imagemode' => $this->thumbs
        ];
        // Pointer
        if (!$exclList || !GeneralUtility::inList($exclList, 'pointer')) {
            $params['pointer'] = $this->pointer ? $this->pointer : '0';
        } else {
            if (isset($overrideParams['pointer'])) {
                $params['pointer'] = $overrideParams['pointer'];
            }
        }
        // SearchWord
        if (!$exclList || !GeneralUtility::inList($exclList, 'searchWord')) {
            $params['searchWord'] = $this->searchWord ? $this->searchWord : '';
        } else {
            if (isset($overrideParams['searchWord'])) {
                $params['searchWord'] = $overrideParams['searchWord'];
            }
        }
        // Sort
        if (!$exclList || !GeneralUtility::inList($exclList, 'sort')) {
            $params['sort'] = $this->sort ? $this->sort : '0';
        } else {
            if (isset($overrideParams['sort'])) {
                $params['sort'] = $overrideParams['sort'];
            }
        }
        // SortRev
        if (!$exclList || !GeneralUtility::inList($exclList, 'sortRev')) {
            $params['sortRev'] = $this->sortRev ? $this->sortRev : '0';
        } else {
            if (isset($overrideParams['sortRev'])) {
                $params['sortRev'] = $overrideParams['sortRev'];
            }
        }
        return GeneralUtility::linkThisScript($params);
    }

    /**
     * This returns tablerows for the files in the array $items['sorting'].
     *
     * @param File[] $files File items
     * @return string HTML table rows.
     */
    public function formatFileList(array $files)
    {
        $out = '';
        // first two keys are "0" (default) and "-1" (multiple), after that comes the "other languages"
        $allSystemLanguages = GeneralUtility::makeInstance(TranslationConfigurationProvider::class)->getSystemLanguages();
        $systemLanguages = array_filter($allSystemLanguages, function ($languageRecord) {
            if ($languageRecord['uid'] === -1 || $languageRecord['uid'] === 0 || !$this->getBackendUser()->checkLanguageAccess($languageRecord['uid'])) {
                return false;
            } else {
                return true;
            }
        });

        foreach ($files as $fileObject) {
            // Add elements index
            $this->addDataElement($fileObject);
            // Initialization
            $this->counter++;
            $this->totalbytes += $fileObject->getSize();
            $ext = $fileObject->getExtension();
            $fileName = trim($fileObject->getName());
            // The icon with link
            $theIcon = '<span title="' . htmlspecialchars($fileName . ' [' . (int)$fileObject->getUid() . ']') . '">'
                . $this->iconFactory->getIconForResource($fileObject, Icon::SIZE_SMALL)->render() . '</span>';
            $theIcon = BackendUtility::wrapClickMenuOnIcon($theIcon, 'sys_file', $fileObject->getCombinedIdentifier());
            // Preparing and getting the data-array
            $theData = [];
            foreach ($this->fieldArray as $field) {
                switch ($field) {
                    case 'size':
                        $theData[$field] = GeneralUtility::formatSize($fileObject->getSize(), htmlspecialchars($this->getLanguageService()->getLL('byteSizeUnits')));
                        break;
                    case 'rw':
                        $theData[$field] = '' . (!$fileObject->checkActionPermission('read') ? ' ' : '<strong class="text-danger">' . htmlspecialchars($this->getLanguageService()->getLL('read')) . '</strong>') . (!$fileObject->checkActionPermission('write') ? '' : '<strong class="text-danger">' . htmlspecialchars($this->getLanguageService()->getLL('write')) . '</strong>');
                        break;
                    case 'fileext':
                        $theData[$field] = strtoupper($ext);
                        break;
                    case 'modification_date':
                        $theData[$field] = BackendUtility::date($fileObject->getModificationTime());
                        break;
                    case '_CONTROL_':
                        $theData[$field] = $this->makeControl($fileObject);
                        break;
                    case '_CLIPBOARD_':
                        $theData[$field] = $this->makeClip($fileObject);
                        break;
                    case '_LOCALIZATION_':
                        if (!empty($systemLanguages) && $fileObject->isIndexed() && $fileObject->checkActionPermission('write') && $this->getBackendUser()->check('tables_modify', 'sys_file_metadata')) {
                            $metaDataRecord = $fileObject->_getMetaData();
                            $translations = $this->getTranslationsForMetaData($metaDataRecord);
                            $languageCode = '';

                            foreach ($systemLanguages as $language) {
                                $languageId = $language['uid'];
                                $flagIcon = $language['flagIcon'];
                                if (array_key_exists($languageId, $translations)) {
                                    $title = htmlspecialchars(sprintf($this->getLanguageService()->getLL('editMetadataForLanguage'), $language['title']));
                                    // @todo the overlay for the flag needs to be added ($flagIcon . '-overlay')
                                    $urlParameters = [
                                        'edit' => [
                                            'sys_file_metadata' => [
                                                $translations[$languageId]['uid'] => 'edit'
                                            ]
                                        ],
                                        'returnUrl' => $this->listURL()
                                    ];
                                    $flagButtonIcon = $this->iconFactory->getIcon($flagIcon, Icon::SIZE_SMALL, 'overlay-edit')->render();
                                    $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
                                    $url = (string)$uriBuilder->buildUriFromRoute('record_edit', $urlParameters);
                                    $languageCode .= '<a href="' . htmlspecialchars($url) . '" class="btn btn-default" title="' . $title . '">'
                                        . $flagButtonIcon . '</a>';
                                } else {
                                    $parameters = [
                                        'justLocalized' => 'sys_file_metadata:' . $metaDataRecord['uid'] . ':' . $languageId,
                                        'returnUrl' => $this->listURL()
                                    ];
                                    $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
                                    $returnUrl = (string)$uriBuilder->buildUriFromRoute('record_edit', $parameters);
                                    $href = (string)$uriBuilder->buildUriFromRoute(
                                        '&cmd[sys_file_metadata][' . $metaDataRecord['uid'] . '][localize]=' . $languageId,
                                        $returnUrl
                                    );
                                    $flagButtonIcon = '<span title="' . htmlspecialchars(sprintf($this->getLanguageService()->getLL('createMetadataForLanguage'), $language['title'])) . '">' . $this->iconFactory->getIcon($flagIcon, Icon::SIZE_SMALL, 'overlay-new')->render() . '</span>';
                                    $languageCode .= '<a href="' . htmlspecialchars($href) . '" class="btn btn-default">' . $flagButtonIcon . '</a> ';
                                }
                            }

                            // Hide flag button bar when not translated yet
                            $theData[$field] = ' <div class="localisationData btn-group" data-fileid="' . $fileObject->getUid() . '"' .
                                (empty($translations) ? ' style="display: none;"' : '') . '>' . $languageCode . '</div>';
                            $theData[$field] .= '<a class="btn btn-default filelist-translationToggler" data-fileid="' . $fileObject->getUid() . '">' .
                                '<span title="' . htmlspecialchars($this->getLanguageService()->getLL('translateMetadata')) . '">'
                                . $this->iconFactory->getIcon('mimetypes-x-content-page-language-overlay', Icon::SIZE_SMALL)->render() . '</span>'
                                . '</a>';
                        }
                        break;
                    case '_REF_':
                        $theData[$field] = $this->makeRef($fileObject);
                        break;
                    case 'file':
                        // Edit metadata of file
                        $theData[$field] = $this->linkWrapFile(htmlspecialchars($fileName), $fileObject);

                        if ($fileObject->isMissing()) {
                            $theData[$field] .= '<span class="label label-danger label-space-left">'
                                . htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:warning.file_missing'))
                                . '</span>';
                            // Thumbnails?
                        } elseif ($this->thumbs && ($this->isImage($ext) || $this->isMediaFile($ext))) {
                            $processedFile = $fileObject->process(ProcessedFile::CONTEXT_IMAGEPREVIEW, []);
                            if ($processedFile) {
                                $thumbUrl = $processedFile->getPublicUrl(true);
                                $theData[$field] .= '<br /><img src="' . $thumbUrl . '" ' .
                                    'width="' . $processedFile->getProperty('width') . '" ' .
                                    'height="' . $processedFile->getProperty('height') . '" ' .
                                    'title="' . htmlspecialchars($fileName) . '" alt="" />';
                            }
                        }
                        break;
                    default:
                        $theData[$field] = '';
                        if ($fileObject->hasProperty($field)) {
                            $theData[$field] = htmlspecialchars(GeneralUtility::fixed_lgd_cs($fileObject->getProperty($field), $this->fixedL));
                        }
                }
            }
            $out .= $this->addElement(1, $theIcon, $theData);
            $this->dataFileIndex ++;
        }
        return $out;
    }

    /**
     * Fetch the translations for a sys_file_metadata record
     *
     * @param $metaDataRecord
     * @return array keys are the sys_language uids, values are the $rows
     */
    protected function getTranslationsForMetaData($metaDataRecord)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_metadata');
        $queryBuilder->getRestrictions()->removeAll();
        $translationRecords = $queryBuilder->select('*')
            ->from('sys_file_metadata')
            ->where(
                $queryBuilder->expr()->eq(
                    $GLOBALS['TCA']['sys_file_metadata']['ctrl']['transOrigPointerField'],
                    $queryBuilder->createNamedParameter($metaDataRecord['uid'], \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->gt(
                    $GLOBALS['TCA']['sys_file_metadata']['ctrl']['languageField'],
                    $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetchAll();

        $translations = [];
        foreach ($translationRecords as $record) {
            $translations[$record[$GLOBALS['TCA']['sys_file_metadata']['ctrl']['languageField']]] = $record;
        }
        return $translations;
    }

    /**
     * Returns TRUE if $ext is an image-extension according to $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']
     *
     * @param string $ext File extension
     * @return bool
     */
    public function isImage($ext)
    {
        return GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], strtolower($ext));
    }

    /**
     * Returns TRUE if $ext is an media-extension according to $GLOBALS['TYPO3_CONF_VARS']['SYS']['mediafile_ext']
     *
     * @param string $ext File extension
     * @return bool
     */
    public function isMediaFile($ext)
    {
        return GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['SYS']['mediafile_ext'], strtolower($ext));
    }

    /**
     * Wraps the directory-titles ($code) in a link to filelist/Modules/Filelist/index.php (id=$path) and sorting commands...
     *
     * @param string $code String to be wrapped
     * @param string $folderIdentifier ID (path)
     * @param string $col Sorting column
     * @return string HTML
     */
    public function linkWrapSort($code, $folderIdentifier, $col)
    {
        $params = ['id' => $folderIdentifier, 'SET' => [ 'sort' => $col ]];

        if ($this->sort === $col) {
            // Check reverse sorting
            $params['SET']['reverse'] = ($this->sortRev ? '0' : '1');
            $sortArrow = $this->iconFactory->getIcon('status-status-sorting-light-' . ($this->sortRev ? 'desc' : 'asc'), Icon::SIZE_SMALL)->render();
        } else {
            $params['SET']['reverse'] = 0;
            $sortArrow = '';
        }
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $href = (string)$uriBuilder->buildUriFromRoute('file_FilelistList', $params);
        return '<a href="' . htmlspecialchars($href) . '">' . $code . ' ' . $sortArrow . '</a>';
    }

    /**
     * Creates the clipboard control pad
     *
     * @param File|Folder $fileOrFolderObject Array with information about the file/directory for which to make the clipboard panel for the listing.
     * @return string HTML-table
     */
    public function makeClip($fileOrFolderObject)
    {
        if (!$fileOrFolderObject->checkActionPermission('read')) {
            return '';
        }
        $cells = [];
        $fullIdentifier = $fileOrFolderObject->getCombinedIdentifier();
        $fullName = $fileOrFolderObject->getName();
        $md5 = GeneralUtility::shortMD5($fullIdentifier);
        // For normal clipboard, add copy/cut buttons:
        if ($this->clipObj->current === 'normal') {
            $isSel = $this->clipObj->isSelected('_FILE', $md5);
            $copyTitle = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.copy'));
            $cutTitle = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.cut'));
            $copyIcon = $this->iconFactory->getIcon('actions-edit-copy', Icon::SIZE_SMALL)->render();
            $cutIcon = $this->iconFactory->getIcon('actions-edit-cut', Icon::SIZE_SMALL)->render();

            if ($isSel === 'copy') {
                $copyIcon = $this->iconFactory->getIcon('actions-edit-copy-release', Icon::SIZE_SMALL)->render();
            } elseif ($isSel === 'cut') {
                $cutIcon = $this->iconFactory->getIcon('actions-edit-cut-release', Icon::SIZE_SMALL)->render();
            }

            $cells[] = '<a class="btn btn-default"" href="' . htmlspecialchars($this->clipObj->selUrlFile($fullIdentifier, 1, ($isSel === 'copy'))) . '" title="' . $copyTitle . '">' . $copyIcon . '</a>';
            // we can only cut if file can be moved
            if ($fileOrFolderObject->checkActionPermission('move')) {
                $cells[] = '<a class="btn btn-default" href="' . htmlspecialchars($this->clipObj->selUrlFile($fullIdentifier, 0, ($isSel === 'cut'))) . '" title="' . $cutTitle . '">' . $cutIcon . '</a>';
            } else {
                $cells[] = $this->spaceIcon;
            }
        } else {
            // For numeric pads, add select checkboxes:
            $n = '_FILE|' . $md5;
            $this->CBnames[] = $n;
            $checked = $this->clipObj->isSelected('_FILE', $md5) ? ' checked="checked"' : '';
            $cells[] = '<input type="hidden" name="CBH[' . $n . ']" value="0" /><label class="btn btn-default btn-checkbox"><input type="checkbox" name="CBC[' . $n . ']" value="' . htmlspecialchars($fullIdentifier) . '" ' . $checked . ' /><span class="t3-icon fa"></span></label>';
        }
        // Display PASTE button, if directory:
        $elFromTable = $this->clipObj->elFromTable('_FILE');
        if ($fileOrFolderObject instanceof Folder && !empty($elFromTable) && $fileOrFolderObject->checkActionPermission('write')) {
            $addPasteButton = true;
            $elToConfirm = [];
            foreach ($elFromTable as $key => $element) {
                $clipBoardElement = $this->resourceFactory->retrieveFileOrFolderObject($element);
                if ($clipBoardElement instanceof Folder && $clipBoardElement->getStorage()->isWithinFolder($clipBoardElement, $fileOrFolderObject)) {
                    $addPasteButton = false;
                }
                $elToConfirm[$key] = $clipBoardElement->getName();
            }
            if ($addPasteButton) {
                $cells[] = '<a class="btn btn-default t3js-modal-trigger" '
                    . ' href="' . htmlspecialchars($this->clipObj->pasteUrl('_FILE', $fullIdentifier)) . '"'
                    . ' data-content="' . htmlspecialchars($this->clipObj->confirmMsgText('_FILE', $fullName, 'into', $elToConfirm)) . '"'
                    . ' data-severity="warning"'
                    . ' data-title="' . htmlspecialchars($this->getLanguageService()->getLL('clip_pasteInto')) . '"'
                    . ' title="' . htmlspecialchars($this->getLanguageService()->getLL('clip_pasteInto')) . '"'
                    . '>'
                    . $this->iconFactory->getIcon('actions-document-paste-into', Icon::SIZE_SMALL)->render()
                    . '</a>';
            }
        }
        // Compile items into a DIV-element:
        return ' <div class="btn-group" role="group">' . implode('', $cells) . '</div>';
    }

    /**
     * Creates the edit control section
     *
     * @param File|Folder $fileOrFolderObject Array with information about the file/directory for which to make the edit control section for the listing.
     * @return string HTML-table
     */
    public function makeEdit($fileOrFolderObject)
    {
        $cells = [];
        $fullIdentifier = $fileOrFolderObject->getCombinedIdentifier();
        $md5 = GeneralUtility::shortMD5($fullIdentifier);
        $isSel = $this->clipObj->isSelected('_FILE', $md5);

        // Edit file content (if editable)
        if ($fileOrFolderObject instanceof File && $fileOrFolderObject->checkActionPermission('write') && GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['SYS']['textfile_ext'], $fileOrFolderObject->getExtension())) {
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $url = (string)$uriBuilder->buildUriFromRoute('file_edit', ['target' => $fullIdentifier]);
            $editOnClick = 'top.list_frame.location.href=' . GeneralUtility::quoteJSvalue($url) . '+\'&returnUrl=\'+top.rawurlencode(top.list_frame.document.location.pathname+top.list_frame.document.location.search);return false;';
            $cells['edit'] = '<a href="#" class="btn btn-default" onclick="' . htmlspecialchars($editOnClick) . '" title="' . $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.editcontent') . '">'
                . $this->iconFactory->getIcon('actions-page-open', Icon::SIZE_SMALL)->render()
                . '</a>';
        } else {
            $cells['edit'] = $this->spaceIcon;
        }

        // Edit metadata of file
        if ($fileOrFolderObject instanceof File && $fileOrFolderObject->checkActionPermission('write') && $this->getBackendUser()->check('tables_modify', 'sys_file_metadata')) {
            $metaData = $fileOrFolderObject->_getMetaData();
            $urlParameters = [
                'edit' => [
                    'sys_file_metadata' => [
                        $metaData['uid'] => 'edit'
                    ]
                ],
                'returnUrl' => $this->listURL()
            ];
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $url = (string)$uriBuilder->buildUriFromRoute('record_edit', $urlParameters);
            $title = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.editMetadata'));
            $cells['metadata'] = '<a class="btn btn-default" href="' . htmlspecialchars($url) . '" title="' . $title . '">' . $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL)->render() . '</a>';
        }

        // document view
        if ($fileOrFolderObject instanceof File) {
            $fileUrl = $fileOrFolderObject->getPublicUrl(true);
            if ($fileUrl) {
                $aOnClick = 'return top.openUrlInWindow(' . GeneralUtility::quoteJSvalue($fileUrl) . ', \'WebFile\');';
                $cells['view'] = '<a href="#" class="btn btn-default" onclick="' . htmlspecialchars($aOnClick) . '" title="' . $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.view') . '">' . $this->iconFactory->getIcon('actions-document-view', Icon::SIZE_SMALL)->render() . '</a>';
            } else {
                $cells['view'] = $this->spaceIcon;
            }
        } else {
            $cells['view'] = $this->spaceIcon;
        }

        // replace file
        if ($fileOrFolderObject instanceof File && $fileOrFolderObject->checkActionPermission('replace')) {
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $url = (string)$uriBuilder->buildUriFromRoute(
                'file_replace', [
                    'target' => $fullIdentifier,
                    'uid' => $fileOrFolderObject->getUid()
                ]
            );
            $replaceOnClick = 'top.list_frame.location.href = ' . GeneralUtility::quoteJSvalue($url) . '+\'&returnUrl=\'+top.rawurlencode(top.list_frame.document.location.pathname+top.list_frame.document.location.search);return false;';
            $cells['replace'] = '<a href="#" class="btn btn-default" onclick="' . $replaceOnClick . '"  title="' . $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.replace') . '">' . $this->iconFactory->getIcon('actions-edit-replace', Icon::SIZE_SMALL)->render() . '</a>';
        }

        // rename the file
        if ($fileOrFolderObject->checkActionPermission('rename')) {
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $url = (string)$uriBuilder->buildUriFromRoute('file_rename', ['target' => $fullIdentifier]);
            $renameOnClick = 'top.list_frame.location.href = ' . GeneralUtility::quoteJSvalue($url) . '+\'&returnUrl=\'+top.rawurlencode(top.list_frame.document.location.pathname+top.list_frame.document.location.search);return false;';
            $cells['rename'] = '<a href="#" class="btn btn-default" onclick="' . htmlspecialchars($renameOnClick) . '"  title="' . $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.rename') . '">' . $this->iconFactory->getIcon('actions-edit-rename', Icon::SIZE_SMALL)->render() . '</a>';
        } else {
            $cells['rename'] = $this->spaceIcon;
        }
        if ($fileOrFolderObject->checkActionPermission('read')) {
            $infoOnClick = '';
            if ($fileOrFolderObject instanceof Folder) {
                $infoOnClick = 'top.launchView( \'_FOLDER\', ' . GeneralUtility::quoteJSvalue($fullIdentifier) . ');return false;';
            } elseif ($fileOrFolderObject instanceof File) {
                $infoOnClick = 'top.launchView( \'_FILE\', ' . GeneralUtility::quoteJSvalue($fullIdentifier) . ');return false;';
            }
            $cells['info'] = '<a href="#" class="btn btn-default" onclick="' . htmlspecialchars($infoOnClick) . '" title="' . $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.info') . '">' . $this->iconFactory->getIcon('actions-document-info', Icon::SIZE_SMALL)->render() . '</a>';
        } else {
            $cells['info'] = $this->spaceIcon;
        }

        // copy the file
        if ($fileOrFolderObject->checkActionPermission('copy') && $this->clipObj->current === 'normal') {
            $copyTitle = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.copy'));
            $copyIcon = $this->iconFactory->getIcon('actions-edit-copy', Icon::SIZE_SMALL)->render();

            if ($isSel === 'copy') {
                $copyIcon = $this->iconFactory->getIcon('actions-edit-copy-release', Icon::SIZE_SMALL)->render();
            }

            $cells['copy'] = '<a class="btn btn-default"" href="' . htmlspecialchars($this->clipObj->selUrlFile($fullIdentifier, 1, ($isSel === 'copy'))) . '" title="' . $copyTitle . '">' . $copyIcon . '</a>';
        }

        // cut the file
        if ($fileOrFolderObject->checkActionPermission('move') && $this->clipObj->current === 'normal') {
            $cutTitle = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.cut'));
            $cutIcon = $this->iconFactory->getIcon('actions-edit-cut', Icon::SIZE_SMALL)->render();

            if ($isSel === 'cut') {
                $cutIcon = $this->iconFactory->getIcon('actions-edit-cut-release', Icon::SIZE_SMALL)->render();
            }

            $cells['cut'] = '<a class="btn btn-default" href="' . htmlspecialchars($this->clipObj->selUrlFile($fullIdentifier, 0, ($isSel === 'cut'))) . '" title="' . $cutTitle . '">' . $cutIcon . '</a>';
        }

        // delete the file
        if ($fileOrFolderObject->checkActionPermission('delete')) {
            $identifier = $fileOrFolderObject->getIdentifier();
            if ($fileOrFolderObject instanceof Folder) {
                $referenceCountText = BackendUtility::referenceCount('_FILE', $identifier, ' ' . $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.referencesToFolder'));
                $deleteType = 'delete_folder';
            } else {
                $referenceCountText = BackendUtility::referenceCount('sys_file', $fileOrFolderObject->getUid(), ' ' . $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.referencesToFile'));
                $deleteType = 'delete_file';
            }

            if ($this->getBackendUser()->jsConfirmation(JsConfirmation::DELETE)) {
                $confirmationCheck = '1';
            } else {
                $confirmationCheck = '0';
            }

            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $deleteUrl = (string)$uriBuilder->buildUriFromRoute('tce_file');
            $confirmationMessage = sprintf($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:mess.delete'), $fileOrFolderObject->getName()) . $referenceCountText;
            $title = $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.delete');
            $cells['delete'] = '<a href="#" class="btn btn-default t3js-filelist-delete" data-content="' . htmlspecialchars($confirmationMessage)
                . '" data-check="' . $confirmationCheck
                . '" data-delete-url="' . htmlspecialchars($deleteUrl)
                . '" data-title="' . htmlspecialchars($title)
                . '" data-identifier="' . htmlspecialchars($fileOrFolderObject->getCombinedIdentifier())
                . '" data-delete-type="' . $deleteType
                . '" title="' . htmlspecialchars($title) . '">'
                . $this->iconFactory->getIcon('actions-edit-delete', Icon::SIZE_SMALL)->render() . '</a>';
        } else {
            $cells['delete'] = $this->spaceIcon;
        }

        // Hook for manipulating edit icons.
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['fileList']['editIconsHook'])) {
            $cells['__fileOrFolderObject'] = $fileOrFolderObject;
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['fileList']['editIconsHook'] as $classData) {
                $hookObject = GeneralUtility::makeInstance($classData);
                if (!$hookObject instanceof FileListEditIconHookInterface) {
                    throw new \UnexpectedValueException(
                        $classData . ' must implement interface ' . FileListEditIconHookInterface::class,
                        1235225797
                    );
                }
                $hookObject->manipulateEditIcons($cells, $this);
            }
            unset($cells['__fileOrFolderObject']);
        }
        // Compile items into a DIV-element:
        return '<div class="btn-group">' . implode('', $cells) . '</div>';
    }

    /**
     * Make reference count
     *
     * @param File|Folder $fileOrFolderObject Array with information about the file/directory for which to make the clipboard panel for the listing.
     * @return string HTML
     */
    public function makeRef($fileOrFolderObject)
    {
        if ($fileOrFolderObject instanceof FolderInterface) {
            return '-';
        }
        // Look up the file in the sys_refindex.
        // Exclude sys_file_metadata records as these are no use references
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_refindex');
        $referenceCount = $queryBuilder->count('*')
            ->from('sys_refindex')
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq(
                    'ref_table',
                    $queryBuilder->createNamedParameter('sys_file', \PDO::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'ref_uid',
                    $queryBuilder->createNamedParameter($fileOrFolderObject->getUid(), \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->neq(
                    'tablename',
                    $queryBuilder->createNamedParameter('sys_file_metadata', \PDO::PARAM_STR)
                )
            )
            ->execute()
            ->fetchColumn();

        return $this->generateReferenceToolTip($referenceCount, '\'_FILE\', ' . GeneralUtility::quoteJSvalue($fileOrFolderObject->getCombinedIdentifier()));
    }

    /**
     * Returns an instance of LanguageService
     *
     * @return \TYPO3\CMS\Lang\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Returns the current BE user.
     *
     * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * Returns a table with directories and files listed.
     *
     * @param array $files Array of file objects
     * @param array $rowlist Array of files from path
     * @return string HTML-table
     */
    public function getFilelist(array $files, $rowlist)
    {
        $lang = $this->getLanguageService();
        // prepare space icon
        $this->spaceIcon = '<span class="btn btn-default disabled">' . $this->iconFactory->getIcon('empty-empty', Icon::SIZE_SMALL)->render() . '</span>';
        // Filters has been set in TYPO3\CMS\Recordlist\Browser\FileBrowser::render ... $storage->addFileAndFolderNameFilter
        // Only render the contents of a browsable storage
        if ($this->folderObject->getStorage()->isBrowsable()) {
            try {
                $filesCount = count($files);
            } catch (InsufficientFolderAccessPermissionsException $e) {
                $filesCount = 0;
            }

            if ($this->firstElementNumber <= 1) {
                $filesFrom = 0;
                $filesNum  = $this->iLimit;
            } else {
                $filesFrom = $this->firstElementNumber;
                if ($filesFrom + $this->iLimit > $filesCount) {
                    $filesNum = $filesCount - $filesFrom;
                } else {
                    $filesNum  = $this->iLimit;
                }
            }
            // Get slice depend on range
            //@todo: FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, $this->sort, $this->sortRev
            $files = array_slice($files, $filesFrom, $filesNum);
            $this->totalItems = $filesCount;
            // Adds the code of files
            $titleCol = 'file';
            // Cleaning rowlist for duplicates and place the $titleCol as the first column always!
            $rowlist = GeneralUtility::rmFromList($titleCol, $rowlist);
            $rowlist = GeneralUtility::uniqueList($rowlist);
            $rowlist = $rowlist ? $titleCol . ',' . $rowlist : $titleCol;
            $rowlist .= ',_CONTROL_';
            $this->addElement_tdCssClass['_CONTROL_'] = 'col-control';
            $this->fieldArray = explode(',', $rowlist);
            // Add classes to table cells
            $this->addElement_tdCssClass[$titleCol] = 'col-title';
            // Render header with folder path and selection buttons
            $hOut = '';
            $iOut = '';
            $titleLen = (int)$this->getBackendUser()->uc['titleLen'];
            $folderIcon = $this->iconFactory->getIconForResource($this->folderObject, Icon::SIZE_SMALL);
            $headerData['file'] = htmlspecialchars(GeneralUtility::fixed_lgd_cs($this->folderObject->getIdentifier(), $titleLen));
            if (intval($this->totalItems)) {
                if (!empty($this->fieldArray)) {
                    foreach ($this->fieldArray as $field) {
                        switch ($field) {
                            // Filename - leave as is
                            case 'file':
                                break;
                            // Controls - import and toggle
                            case '_CONTROL_':
                                $linkImportSelection = '<a href="#" class="btn btn-default" id="t3js-importSelection" title="' . $lang->getLL('importSelection', true) . '">' . $this->iconFactory->getIcon('actions-document-import-t3d', Icon::SIZE_SMALL) . '</a>';
                                $linkToggleSelection = '<a href="#" class="btn btn-default" id="t3js-toggleSelection" title="' . $lang->getLL('toggleSelection', true) . '">' . $this->iconFactory->getIcon('actions-document-select', Icon::SIZE_SMALL) . '</a>';
                                $headerData[$field] = '<div class="btn-group">' . $linkImportSelection . $linkToggleSelection . '</div>';
                                break;
                            // Sorting - ensures sorting fields only
                            default:
                                $allowedRowListSortingFieldsList = 'fileext,modification_date,size'; // Defined as $rowList in FileBrowserXclass->getFilelist()
                                $allowedRowListSortingFields = GeneralUtility::trimExplode(',', $allowedRowListSortingFieldsList);
                                if (in_array($field, $allowedRowListSortingFields)) {
                                    // Get sorting direction
                                    $sort = GeneralUtility::_GP('sort');
                                    $sortRev = GeneralUtility::_GP('sortRev');
                                    if ($field === $sort) {
                                        if (!$sortRev) {
                                            $sortRev = 1;
                                            $sortArrow = $this->iconFactory->getIcon('status-status-sorting-asc', Icon::SIZE_SMALL)->render();
                                        } else {
                                            $sortRev = 0;
                                            $sortArrow = $this->iconFactory->getIcon('status-status-sorting-desc', Icon::SIZE_SMALL)->render();
                                        }
                                    } else {
                                        $sortRev = 0;
                                        $sortArrow = '';
                                    }
                                    $title = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:filelist/Resources/Private/Language/locallang_mod_file_list.xlf:c_' . (($field === 'modification_date') ? 'tstamp' : $field)));
                                    $headerData[$field] = '<a href="' . htmlspecialchars($this->listURL('', '-1', 'pointer,sort,sortRev', ['sort' => $field, 'sortRev' => $sortRev, 'pointer' => 0])) . '" title="' . $title . '">' . $title . $sortArrow . '</a>';
                                }
                                break;
                        }
                    }
                }
                $hOut .= $this->addElement(1, $folderIcon, $headerData, '', '', '', 'th');
                $iOut = '';
                // No directories are added
                $this->eCounter = $this->firstElementNumber;
                list(, $code) = $this->fwd_rwd_nav();
                $iOut .= $code;
                // Files are added
                $iOut .= $this->formatFileList($files);
                $this->eCounter = $this->firstElementNumber + $this->iLimit < $this->totalItems
                    ? $this->firstElementNumber + $this->iLimit
                    : -1;
                list(, $code) = $this->fwd_rwd_nav();
                $iOut .= $code;
            } else {
                $hOut .= $this->addElement(1, $folderIcon, $headerData, '', '', '', 'th');
                $iOut .= $this->addElement(1, '', ['file' => 'No files found.'], '', '', '', 'td');
            }

            // Render header, list and pagination
            $out = '';
            $out .= '<thead>' . $hOut . '</thead>';
            $out .= '<tbody>' . $iOut . '</tbody>';
            $out = '
        <!--
            Filelist table:
        -->
            <div class="table-fit">
                <table class="table table-striped table-hover" id="typo3-filelist">
                    ' . $out . '
                </table>
            </div>';
        } else {
            /** @var $flashMessage FlashMessage */
            $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $this->getLanguageService()->getLL('storageNotBrowsableMessage'), $this->getLanguageService()->getLL('storageNotBrowsableTitle'), FlashMessage::INFO);
            /** @var $flashMessageService \TYPO3\CMS\Core\Messaging\FlashMessageService */
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            /** @var $defaultFlashMessageQueue \TYPO3\CMS\Core\Messaging\FlashMessageQueue */
            $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $defaultFlashMessageQueue->enqueue($flashMessage);
            $out = '';
        }
        return $out;
    }

    /**
     * Creates the edit control section
     *
     * @param File|Folder $fileOrFolderObject Array with information about the file/directory for which to make the edit control section for the listing.
     * @return string HTML-table
     */
    public function makeControl($fileOrFolderObject)
    {
        $lang = $this->getLanguageService();
        // Create link to showing details about the file in a window:
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $Ahref = (string)$uriBuilder->buildUriFromRoute('show_item', [
            'type' => 'file',
            'table' => '_FILE',
            'uid' => $fileOrFolderObject->getCombinedIdentifier(),
            'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI')
        ]);
        if ($fileOrFolderObject instanceof FileInterface) {
            $ATag = '<a href="#" class="btn btn-default" title="' . htmlspecialchars($fileOrFolderObject->getName()) . '" data-file-index="' . $this->dataFileIndex . '" data-close="0">';
            $ATag .= '<span title="' . htmlspecialchars($lang->getLL('addToList')) . '">' . $this->iconFactory->getIcon('actions-add', Icon::SIZE_SMALL)->render() . '</span>';
            $ATag_e = '</a>';
            $bulkCheckBox = '<label class="btn btn-default btn-checkbox"><input type="checkbox" class="typo3-bulk-item" name="file_' . $this->dataFileIndex . '" value="0" /><span class="t3-icon fa"></span></label>';
        } else {
            $ATag = '<span class="btn btn-default disabled"><span class="t3js-icon icon icon-size-small icon-state-default icon-empty-empty" data-identifier="empty-empty"><span class="icon-markup"><span class="icon-unify"><i class="fa fa-empty-empty"></i></span></span></span></span>';
            $ATag_e = '';
            $bulkCheckBox = '';
        }
        // Compile items into a DIV-element:
        return '<div class="btn-group">' . $ATag . $ATag_e . '<a href="' . htmlspecialchars($Ahref) . '" class="btn btn-default" title="' . $lang->getLL('info', true) . '">' . $this->iconFactory->getIcon('actions-document-info', Icon::SIZE_SMALL) . '</a>' . $bulkCheckBox . '</div>';
    }

    /**
     * Add element data
     * Adds information about files, for body tag data-elements
     *
     * @param File $fileObject
     * @return void
     */
    public function addDataElement(File $fileObject)
    {
        $fileExtension = $fileObject->getExtension();
        // Thumbnail/size generation:
        if (!$noThumbs && GeneralUtility::inList(strtolower($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'] . ',' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['mediafile_ext']), strtolower($fileExtension))) {
            $imgInfo = [
                $fileObject->getProperty('width'),
                $fileObject->getProperty('height')
            ];
            $pDim = $imgInfo[0] . 'x' . $imgInfo[1] . ' pixels';
        } else {
            $pDim = '';
        }
        // Create file icon:
        $size = ' (' . GeneralUtility::formatSize($fileObject->getSize()) . 'bytes' . ($pDim ? ', ' . $pDim : '') . ')';
        $icon = '<span title="' . htmlspecialchars($fileObject->getName() . $size) . '">' . $this->iconFactory->getIconForResource($fileObject, Icon::SIZE_SMALL) . '</span>';
        // Add element
        $this->dataElements['file_' . $this->dataFileIndex] = [
            'type' => 'file',
            'table' => 'sys_file',
            'uid' => $fileObject->getUid(),
            'fileName' => $fileObject->getName(),
            'filePath' => $fileObject->getUid(),
            'fileExt' => $fileExtension,
            'fileIcon' => $icon
        ];
    }

    /**
     * Get elements data
     * Returns information about files, for body tag data-elements
     *
     * @return array
     */
    public function getDataElements()
    {
        return $this->dataElements;
    }

    /**
     * Get total items count
     * Returns count of total items
     *
     * @return integer
     */
    public function getTotalItemsCount()
    {
        return $this->totalItems;
    }
}

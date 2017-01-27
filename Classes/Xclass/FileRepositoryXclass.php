<?php
namespace Jokumer\Xfilelist\Xclass;

use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FileRepositoryXclass
 *
 * @package TYPO3
 * @subpackage tx_xfilelist
 * @author 2017 J.Kummer <typo3 et enobe dot de>, enobe.de
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class FileRepositoryXclass extends FileRepository
{

    /**
     * Search for files by name in a given folder
     * Overrides original method to search within metadata
     * CMS7.6.0
     *
     * @param Folder $folder
     * @param string $searchWord
     * @param array $allowedFileExtension List of fileextensions to show
     * @return File[]
     */
    public function searchByName(Folder $folder, $searchWord, array $allowedFileExtension = [])
    {
        /** @var \TYPO3\CMS\Core\Resource\ResourceFactory $fileFactory */
        $fileFactory = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Resource\ResourceFactory::class);

        $folders = $folder->getStorage()->getFoldersInFolder($folder, 0, 0, true, true);
        $folders[$folder->getIdentifier()] = $folder;

        $fileRecords = $this->getFileIndexRepository()->findByFolders($folders, false);

        $fileUidsFromMetaDataRecordsMatchingSearchWord = $this->getFileUidsFromSysFileMetaDataRecordsMatchingSearchWord($searchWord);

        $files = array();
        foreach ($fileRecords as $fileRecord) {
            if (!empty($allowedFileExtension)
                && !in_array($fileRecord['extension'], $allowedFileExtension)
            ) {
                continue;
            }
            if (!empty($fileUidsFromMetaDataRecordsMatchingSearchWord)
                && !in_array($fileRecord['uid'],
                    $fileUidsFromMetaDataRecordsMatchingSearchWord) && stristr($fileRecord['name'],
                    $searchWord) === false
            ) {
                continue;
            }
            try {
                $files[] = $fileFactory->getFileObject($fileRecord['uid'], $fileRecord);
            } catch (\TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException $ignoredException) {
                continue;
            }
        }

        return $files;
    }

    /**
     * Get file uids from sys_file_metadata records matching search word
     *
     * @param string $searchWord
     * @return array
     */
    protected function getFileUidsFromSysFileMetaDataRecordsMatchingSearchWord($searchWord)
    {
        $availableFields = $this->getSysFileMetaDataTextFields();
        $extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['xfilelist']);
        if (isset($extConf['sysFileMetadataSearchFields'])) {
            $searchFields = GeneralUtility::trimExplode(',', $extConf['sysFileMetadataSearchFields']);
        } else {
            $searchFields = array('title', 'description', 'keywords', 'caption');
        }
        $searchFieldsForWhere = array_intersect($searchFields, $availableFields);
        $additionalWhere = '';
        if (!empty($searchFieldsForWhere)) {
            $additionalWhereItems = array();
            foreach ($searchFieldsForWhere as $searchField) {
                $additionalWhereItems[] = $searchField . ' LIKE \'%' . $searchWord . '%\'';
            }
            $records = $this->getDatabaseConnection()->exec_SELECTgetRows(
                'file',
                'sys_file_metadata',
                'sys_language_uid IN (0,-1) AND (' . implode(' OR ', $additionalWhereItems) . ')',
                '',
                '',
                '',
                'file'
            );
        }
        if (is_array($records)) {
            return array_keys($records);
        } else {
            return array();
        }
    }

    /**
     * Get sys_file_metadata fields of type text
     * Include fields from third extensions if installed
     *
     * @return array $fields
     */
    protected function getSysFileMetaDataTextFields()
    {
        // default text fields by core v7.6.0
        $fields = array(
            'title',
            'description',
            'alternative'
        );
        if (ExtensionManagementUtility::isLoaded('filemetadata')) { // v7.6.0
            array_push($fields, 'status', 'keywords', 'caption', 'creator_tool', 'download_name',
                'creator', 'publisher', 'source', 'copyright', 'location_country', 'location_region',
                'location_city', 'note', 'color_space', 'language'
            );
        }
        if (ExtensionManagementUtility::isLoaded('metadata')) { // v2.2.3
            array_push($fields, 'copyright_notice', 'shutter_speed_value', 'iso_speed_ratings', 'camera_model');
        }
        return $fields;
    }
}

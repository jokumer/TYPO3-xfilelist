<?php
namespace Jokumer\Xfilelist\Xclass;

use Doctrine\DBAL\Query\QueryBuilder;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FileRepositoryXclass
 *
 * @package TYPO3
 * @subpackage tx_xfilelist
 * @author J.Kummer
 * @author 2018 T.Karliczek
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class FileRepositoryXclass extends FileRepository
{
    /**
     * Search for files by name in a given folder
     * Overrides original method to search within metadata
     *
     * @param Folder $folder
     * @param string $searchWord
     * @param array $allowedFileExtension List of fileextensions to show
     * @return array $files
     */
    public function searchByName(Folder $folder, $searchWord, array $allowedFileExtension = [])
    {
        return $this->getFilesInFolderByFilters($folder, ['searchWord' => trim($searchWord)], $allowedFileExtension);
    }

    /**
     * Search for files by filter
     * Filter can be of 'searchWord', 'sortingField' and 'sortingOrder'
     *
     * @param Folder $folder
     * @param array $filters
     * @param array $allowedFileExtension List of fileextensions to show
     * @return array $files
     */
    public function getFilesInFolderByFilters(Folder $folder, array $filters, array $allowedFileExtension = [])
    {
        $sortingField = $filters['sort'] ? $filters['sort'] : '';
        $sortingReverse = $filters['sortRev'] ? 1 : 0;
        /** @var ResourceFactory $fileFactory */
        $fileFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $folders = $folder->getStorage()->getFoldersInFolder($folder, 0, 0, true, true);
        $folders[$folder->getIdentifier()] = $folder;
        $files = $folder->getFiles(0, 0, Folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, true, trim($sortingField), (bool)$sortingReverse);
        if ($filters['searchWord']) {
            $filesMatchingSearchWord = [];
            $fileUidsFromMetaDataRecordsMatchingSearchWord = $this->getFileUidsFromSysFileMetaDataRecordsMatchingSearchWord($filters['searchWord']);
            foreach ($files as $file) {
                try {
                    if (!empty($allowedFileExtension)
                        && !in_array($file->getExtension(), $allowedFileExtension)
                    ) {
                        continue;
                    }
                    if (!empty($fileUidsFromMetaDataRecordsMatchingSearchWord)
                        && in_array($file->getUid(), $fileUidsFromMetaDataRecordsMatchingSearchWord)
                    ) {
                        $filesMatchingSearchWord[] = $fileFactory->getFileObject($file->getUid(), $file->getProperties());
                    }
                    $nameParts = str_getcsv($filters['searchWord'], ' ');
                    foreach ($nameParts as $namePart) {
                        $fileObject = $fileFactory->getFileObject($file->getUid(), $file->getProperties());
                        // Find matches AND prevent prevent duplicate results
                        if (stripos($file->getName(), $namePart) !== false && !in_array($fileObject, $filesMatchingSearchWord)) {
                            $filesMatchingSearchWord[] = $fileObject;
                        }
                    }
                } catch (FileDoesNotExistException $ignoredException) {
                    continue;
                }
            }
            $files = $filesMatchingSearchWord;
        }
        return $files;
    }

    /**
     * Get file uids from sys_file_metadata records matching search word
     *
     * @param integer $limit
     * @param string $searchWord
     * @return array
     */
    protected function getFileUidsFromSysFileMetaDataRecordsMatchingSearchWord($searchWord, $limit=5000)
    {
        $availableFields = $this->getSysFileMetaDataTextFields();
        /** @var ExtensionConfiguration $extensionConfiguration */
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('xfilelist');
        if (isset($extensionConfiguration['sysFileMetadataSearchFields'])) {
            $searchFields = GeneralUtility::trimExplode(',', $extensionConfiguration['sysFileMetadataSearchFields']);
        } else {
            $searchFields = ['title', 'description', 'alternative'];
        }
        $searchFieldsForWhere = array_intersect($searchFields, $availableFields);
        $records = [];
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_metadata');
        if (!empty($searchFieldsForWhere)) {
            $additionalWhereItems = [];
            foreach ($searchFieldsForWhere as $searchField) {
                $additionalWhereItems[] = $queryBuilder->expr()->like(
                    $searchField,
                    $queryBuilder->createNamedParameter('%' . $searchWord . '%')
                );
            }
            $res = $queryBuilder
                ->select('file')
                ->from('sys_file_metadata')
                ->orWhere(...$additionalWhereItems)
                ->orderBy('file')
                ->execute();
            while ($row = $res->fetch()) {
                $records[] = $row['file'];
            }
        }
        if (is_array($records)) {
            return $records;
        } else {
            return [];
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
        // default text fields by core v9.5
        $fields = [
            'title',
            'description',
            'alternative'
        ];
        if (ExtensionManagementUtility::isLoaded('filemetadata')) { // v9.5
            array_push(
                $fields,
                'caption',
                'color_space',
                'copyright',
                'creator',
                'creator_tool',
                'download_name',
                'keywords',
                'language',
                'location_city',
                'location_country',
                'location_region',
                'note',
                'publisher',
                'source',
                'status',
                'unit'
            );

        }
        if (ExtensionManagementUtility::isLoaded('metadata')) { // v2.2.5
            array_push(
                $fields,
                'camera_model',
                'copyright_notice',
                'credit',
                'iso_speed_ratings',
                'shutter_speed_value'
            );
        }
        return $fields;
    }
}

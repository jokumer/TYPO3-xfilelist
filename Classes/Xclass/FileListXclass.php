<?php
namespace Jokumer\Xfilelist\Xclass;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Filelist\Controller\FileListController;
use TYPO3\CMS\Filelist\FileList;

/**
 * Class FileListXclass
 *
 * @package TYPO3
 * @subpackage tx_xfilelist
 * @author 2017-2019 J.Kummer
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class FileListXclass extends FileList
{
    /**
     * Construct
     *
     * @param FileListController $fileListController
     */
    public function __construct(FileListController $fileListController)
    {
        parent::__construct($fileListController);
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('xfilelist');
        if ($extensionConfiguration['fileListConfiguration']) {
            // Set default max items shown
            if (isset($extensionConfiguration['fileListConfiguration_iLimit']) && (int) $extensionConfiguration['fileListConfiguration_iLimit'] > 0) {
                $this->iLimit = $extensionConfiguration['fileListConfiguration_iLimit'];
            }
            // Set max length of file title
            if (isset($settings['fileListConfiguration_fixedL']) && (int) $extensionConfiguration['fileListConfiguration_fixedL'] > 0) {
                $this->fixedL = $extensionConfiguration['fileListConfiguration_fixedL'];
            }
        }
    }
}

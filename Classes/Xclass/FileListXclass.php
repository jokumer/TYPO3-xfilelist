<?php
namespace Jokumer\Xfilelist\Xclass;

use TYPO3\CMS\Filelist\Controller\FileListController;

/**
 * Class FileListXclass
 *
 * @package TYPO3
 * @subpackage tx_xfilelist
 * @author 2017 J.Kummer <typo3 et enobe dot de>, enobe.de
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class FileListXclass extends \TYPO3\CMS\Filelist\FileList
{

    /**
     * Construct
     *
     * @param FileListController $fileListController
     */
    public function __construct(FileListController $fileListController)
    {
        parent::__construct($fileListController);
        $settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['xfilelist']);
        if ($settings['fileListConfiguration']) {
            // Set default max items shown
            if (isset($settings['fileListConfiguration_iLimit']) && (int)$settings['fileListConfiguration_iLimit'] > 0) {
                $this->iLimit = $settings['fileListConfiguration_iLimit'];
            }
            // Set max length of file title
            if (isset($settings['fileListConfiguration_fixedL']) && (int)$settings['fileListConfiguration_fixedL'] > 0) {
                $this->fixedL = $settings['fileListConfiguration_fixedL'];
            }
        }
    }

}

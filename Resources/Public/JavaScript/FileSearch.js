/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Module: TYPO3/CMS/Recordlist/BrowseFiles
 * JavaScript for search in file browser
 * @export TYPO3/CMS/Xfilelist/FileSearch
 */
define(['jquery', 'TYPO3/CMS/Backend/jquery.clearable'], function($) {

  $(function() {
    var $searchFields = $('input[name="searchWord"]');
    var searchResultShown = ('' !== $searchFields.first().val());

    // make search field clearable
    $searchFields.clearable({
      onClear: function() {
        if (searchResultShown) {
          $(this).closest('form').submit();
        }
      }
    });
  });

});

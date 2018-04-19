# TYPO3 EXT:xfilelist

TYPO3 extension to extend the default file search within metadata for TYPO3 backend modul filelist and the TYPO3 filebrowser.
Adds pagination for TYPO3 filelist and filebrowser, where amount of list entries is configurable.
Adds missing sorting for TYPO3 filebrowser.

## Installation

Install the extension in the Extension Manager. Nothing more is required.

## Project info and releases

Project home: https://github.com/jokumer/TYPO3-xfilelist

Development: https://github.com/jokumer/TYPO3-xfilelist.git

	git clone https://github.com/jokumer/TYPO3-xfilelist.git

Bug reports: https://github.com/jokumer/TYPO3-xfilelist/issues

## Search

When searching for files, the search includes metadata.
Metadata are stored in DB table *sys_file_metadata*.
Extensions *filemetadata* and *metadata* extends this table by further fields.
Define fields to be included for search in the extension-configuration via extension-manager (*sysFileMetadataSearchFields*).
Default is:

	title, description, keywords, caption

You can add some of following, depend on which extensions has been installed:

	title, description, alternative, status, keywords, caption, creator_tool, download_name,creator, publisher, source, copyright, location_country, location_region, location_city, note, color_space, language, copyright_notice, shutter_speed_value, iso_speed_ratings, camera_model

See also
- https://docs.typo3.org/typo3cms/extensions/core/latest/Changelog/7.6/Feature-69120-AddBasicFileSearchInElementBrowser.html
- https://forge.typo3.org/issues/69120
- https://forge.typo3.org/issues/58724

## Pagination

Pagination is available in filelist and filebrowser. In backend modul filelist for list view only, not for search view.

Amount of entries in list can be configured in the extension-configuration via extension-manager (*fileListConfiguration_iLimit*).
Default is: 40

See also
- https://forge.typo3.org/issues/64764

## Sorting

Sorting is already available in filelist. For filebrowser this extension supports sorting for file-type, modification-date and size.

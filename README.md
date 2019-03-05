# TYPO3 EXT:xfilelist

TYPO3 extension to extend the default TYPO3 backend modul filelist and the TYPO3 filebrowser.
* search within metadata for filelist and filebrowser
* pagination for filelist and filebrowser on search
* file infos in filebrowser like in filelist
* sorting by file infos in filebrowser like in filelist
* clear search in filebrowser like in filelist

## Installation

Install the extension in the Extension Manager. Nothing more is required.

## Project info and releases

Project home: https://github.com/jokumer/TYPO3-xfilelist

Development: https://github.com/jokumer/TYPO3-xfilelist.git

	git clone https://github.com/jokumer/TYPO3-xfilelist.git

Bug reports: https://github.com/jokumer/TYPO3-xfilelist/issues

## Search

When searching for files, the search includes text from metadata as stored in DB table *sys_file_metadata*.
Extensions *filemetadata* and *metadata* extends this table by further fields.
Define text fields to be included for search in the extension-configuration via extension-manager (*sysFileMetadataSearchFields*).
Default is:

	title, description, alternative

You can add/change text fields, depend on which extensions has been installed:

### System EXT:filemetadata

	caption, color_space, copyright, creator, creator_tool, download_name, keywords, language, location_city, location_country, location_region, note, publisher, source, status, unit

### EXT:metadata

	camera_model, copyright_notice, iso_speed_ratings, shutter_speed_value

See also
- https://docs.typo3.org/typo3cms/extensions/core/latest/Changelog/7.6/Feature-69120-AddBasicFileSearchInElementBrowser.html
- https://forge.typo3.org/issues/69120
- https://forge.typo3.org/issues/58724

## Pagination

Pagination is available in filebrowser for list and search view. In backend modul filelist for list view only, not for search view.

Amount of entries in list can be configured in the extension-configuration via extension-manager (*fileListConfiguration_iLimit*).
Default is: 40

See also
- https://forge.typo3.org/issues/64764

Archive Folder (plugin for Omeka)
=================================

[Archive Folder] is a plugin for [Omeka] that allows to import a folder of files
and/or metadata and to create or update items in Omeka. Files can be local or
remote.

In the true world, this tool is designed for people and institutions who store
folders of files somewhere on hard drives or servers, who manage them with a
simple file manager and with some metadata in various files (text, spreadsheet,
xml...). Of course, if files and metadata are well managed, they can be ingested
too.

This plugin replaces the [fork of Csv Import] and the plugin [Xml Import], that
won't be maintained any more. All of their features have equivalents in this
tool via internal functions, classes (for the mapping between specific metadata
and Omeka elements) and hooks (for special data).


Examples
--------

An example is included, but you can build your own before importing true files
and metadata.

### Included Example

A sample folder with some metadata is available in "tests/suite/_files/Folder_test".
To test it, follow these steps:
- if you want to test import of extra data, install [Geolocation] too;
- if you want to import all metadata that are in examples, allow the formats
`xml` and `json` and check the default extensions for `ods`, `odt` and `txt` in
the page `/admin/settings/edit-security`, and the same too for the respective
media types `application/xml`, `text/xml`, `application/json`, and default
`application/vnd.oasis.opendocument.spreadsheet`, `application/vnd.oasis.opendocument.text`
and `text/plain`;
- copy the folder outside of the folder where Omeka is installed, somewhere the
server can access (or in a subdirectory of `files`.
- click on "Add a new archive folder" in the "Archive Folders" tab;
- fill the base uri, something like `http://localhost/path/to/the/Folder_Test`;
- select "One item by repository" (all files in a subfolder belong to one item);
- select "Dublin Core : Identifier" as Identifier field (this allows update);
- don't care about other parameters, they will be default;
- click on the submit button;
- click on "Check" to check the folder (results are displayed when the page is
refreshed);
- if there is no issue, click "Process" to process the folder.

After a few seconds, the items and files will be automatically created.

In order to preserve memory and cpu, the process is done one record by one, via
a background job. In case of error, the process can be relaunched at the last
record.

See the notes below for other issues.

Currently, the update process is not available.

### Simple folder

The plugin creates an xml file that represents the content of a folder in a
standard way and makes it available. So, the existing folder of files:

```
    My Folder
    ├── image_n1.jpg
    ├── image_n2.jpg
    ├── image_n3.jpg
    └── image_n4.jpg
```

will be imported as four items (parameter: "One item by file") or as one item
with four files (parameter "One item by directory"). The Dublin Core Title,
Source and Identifier will be automatically set. The identifier is important for
automatic update of metadata.

### Nested folders

The folder can contains sub-folders, so, if wanted, each folder can be imported
as an item with multiple files.

```
    My Nested Folder
    ├──┬─ Item_1
    │  ├───┬─ Item_2
    │  │   ├──── my_image_1.jpg
    │  │   └──── my_image_2.jpg
    │  ├──── my_image_1.jpg
    │  └──── my_image_2.jpg
    ├──┬─ Item_3
    │  ├──── my_image_3.jpg
    │  └──── my_image_4.jpg
    ├──── my_image_5.jpg
    └──── my_image_6.jpg
```

Here, there are 4 or 8 items according to the parameter `unreferenced files`.

### Metadata ingest

The metadata of each item can be imported if they are available in files in a
supported format. Currently, some formats are implemented: a simple text one
(as raw text or as OpenDocument Text `odt`, for testing purpose), a simple json
format, a table one (as OpenDocument Spreadsheet `ods`), the [Mets] xml, if the
profile is based on Dublin Core, and an internal xml one, `Documents`, that
allows to manage all specificities of Omeka. Other ones can be easily added via
a simple class, like [Ead] with the plugin [Ead for Omeka].

```
    My Digitized Books
    ├──── Book_1.xml
    ├──── External.metadata.txt
    ├──┬─ Book 1
    │  ├──── Page_1.tiff
    │  ├──── Page_2.tiff
    │  ├──── Page_3.tiff
    │  └──── Page_4.tiff
    └──┬─ Book 2
       ├──── Book_2.metadata.txt
       ├──── Page_1.tiff
       ├──── Page_2.tiff
       ├──── Page_3.tiff
       └──── Page_4.tiff
```

Notes for metadata files:

- A metadata file can contain one or multiple documents.
- Referenced files in metadata files can be external to the original folder.
- Metadata files can be anywhere in the folder, as long as the paths to the
referenced files urls are absolute or relative to it and that the server has
access to it.

See below for more details on metadata files.

### Standard digitized books and serials (Mets / Alto)

Books and serials are often digitized with an undesctructive compression via the
format [Jpeg 2000], the metadata are saved in [Mets] and the content texts (OCR)
are saved in [Alto]. All of them can be imported automagically.

```
    My Digitized Books
    ├──┬─ Book_1
    │  ├───── Book_1.mets.xml
    │  ├───┬─ master
    │  │   ├──── Book_1_001.jp2
    │  │   ├──── Book_1_002.jp2
    │  │   ├──── Book_1_003.jp2
    │  │   └──── Book_1_004.jp2
    │  └───┬─ ocr
    │      ├──── Book_1_001.alto.xml
    │      ├──── Book_1_002.alto.xml
    │      ├──── Book_1_003.alto.xml
    │      └──── Book_1_004.alto.xml
    ├──┬─ Book_2
    │  ├───── Book_2.mets.xml
    │  ├───┬─ master
    │  │   ├──── Book_2_001.jp2

```

The xml Mets file contains path to each subordinate file (master, ocr, etc.), so
the structure may be different.

The plugin [OcrElementSet] should be installed to import ocr data, if any.

If there are other files in folders, for example old xml [refNum] or any other
old texts files that may have been used previously for example for a conversion
from refNum to Mets via the tool [refNum2Mets], they need to be skipped via
the option "Unreferenced files" and/or the option "File extensions to exclude",
with "refnum.xml ods txt" for example.


Installation
------------

Uncompress files and rename plugin folder `ArchiveFolder`.

Then install it like any other Omeka plugin and follow the config instructions.

The plugin [OcrElementSet] can be installed to import ocr data (xml Alto).
The plugin [Ead for Omeka] can be installed to import Ead data.
The plugin [Dublin Core Extended] can be installed too.

Some points should be checked too.

* Server Access

The server should allow the indexing of the folder for the localhost. So,
for [Apache httpd], the following commands may be added in a file `.htaccess` at
the root of the folder (this is the default in the folder test, so it may be
needed to change it):

```
Options Indexes FollowSymLinks
# AllowOverride all
Order Deny,Allow
Deny from all
Allow from 127.0.0.1 ::1
```

* Local path

If the server doesn't allow indexing, you can use the equivalent path `/var/www/path/to/the/Folder_Test`
or something similar. Nevertheless, for security reasons, the allowed base path
or a parent should be defined before in the file `security.ini` of the plugin.

* Characters

It is recommended to have filenames with characters whose representations are
the same in metadata files, on the source file system, the transport layer
(http) and the destination file system, in particular for uppercase/lowercase,
for non-latin characters and even if they simply contains spaces. Furthermore,
the behavior depends on the version of PHP.

Nevertheless, the plugin manages all unicode characters. A quick check can be
done with the folders "Folder_Test_Characters_Http" and "Folder_Test_Characters_Local".
These folders contains files with spaces and some non-alphanumeric characters
and metadata adapted for an ingest via http or local path.

In fact, currently, if the main uri is an url, all paths in metadata files
should be raw url encoded, except the reserved characters: `$-_.+!*'()[],`.

* Files extensions

For security reasons, the plugin checks the extension of each ingested file. So,
if you import specific files, in particular XML metadata files and json ones,
they should be allowed in the page "/admin/settings/edit-security".

* XSLT processor

Xslt has two main versions:  xslt 1.0 and xslt 2.0. The first is often installed
with php via the extension "php-xsl" or the package "php5-xsl", depending on
your system. It is until ten times slower than xslt 2.0 and sheets are more
complex to write.

So it's recommended to install an xslt 2 processor, that can process xslt 1.0
and xslt 2.0 sheets. The command can be configured in the configuration page of
the plugin. Use "%1$s", "%2$s", "%3$s", without escape, for the file input, the
stylesheet, and the output.

Examples for Debian 6, 7, 8 / Ubuntu / Mint (with the package "libsaxonb-java"):
```
saxonb-xslt -ext:on -versionmsg:off -warnings:silent -s:%1$s -xsl:%2$s -o:%3$s
```

Examples for Debian 8 / Ubuntu / Mint (with the package "libsaxonhe-java"):
```
CLASSPATH=/usr/share/java/Saxon-HE.jar java net.sf.saxon.Transform -ext:on -versionmsg:off -warnings:silent -s:%1$s -xsl:%2$s -o:%3$s
```

Example for Fedora / RedHat / Centos / Mandriva / Mageia:
```
saxon -ext:on -versionmsg:off -warnings:silent -s:%1$s -xsl:%2$s -o:%3$s
```

Note: Only saxon is currently supported as xslt 2 processor. Because Saxon is a
Java tool, a JRE should be installed, for example "openjdk-8-jre-headless".

Note: Warnings are processed as errors. That's why the parameter "-warnings:silent"
is important to be able to process an import with a bad xsl sheet. It can be
removed with default xsl, that doesn't warn anything.

Anyway, if there is no xslt2 processor installed, the command field should be
cleared. The plugin will use the default xslt 1 processor of php, if installed.


Formats of metadata
-------------------

### Common notes

* See examples in the folder "tests/suite/_files" of the plugin. Most of the
examples are formatted with strange keys or values, but this is to test uncommon
metadata. Of course, normal metadata files are simpler and more coherent.
Furthermore, some have missing metadata that are completed by another file for
testing purpose too.

* Keys are case sensitive, except for specific fields ("record type"... and
Dublin Core elements without the element set.

* `Dublin Core` (simple or qualified if wanted) is the default element set. So
the element `Title` corresponds to "Dublin Core:Title".

* If the element set name is not set, the check is case insensitive; `Table of contents`
will be imported as "Dublin Core:Table Of Contents". Else, the check is case
sensitive: "Dublin Core:Table of contents" won't be identified.

* If the element name contains a `:`, it will be interpreted as a standard
element, else as an extra data, that may be processed via a hook or by a special
plugin. Extra data can be the `Item type`, the `collection`, the `tags`,
`featured`, `public`, or any other values, like for geolocation (see below).

* If an element doesn't exist in Omeka, for example an element such "Dublin Core:Abstract"
when [Dublin Core Extended] is not installed, it will processed as extra data,
so a specific plugin can handle it.

* Some terms are reserved, like `public`, `featured`, `collection`, `tags`, etc.
They  should not be used for extra data.

* Internal relative paths should be relative to the metadata file where they are
referenced, not to the root of the main folder that is imported.

### Documents XML

A internal and simple xml format is used as a pivot. It is designed to be used
internaly only, not to be exposed. Other xml formats should be converted to it
be imported.

It has only the five tags `record`, `elementSet`, `element`, `extra` and `data`
under the root tag `documents` allows to import all metadata and specific fields
of Omeka. For compatibilty purposes, it supports too the tags of the Dublin Core
(simple or qualified). Standard attributes of the record can be set as extra
data too.

Here is the structure (see true examples for details):

```xml
<record xmlns="http://localhost/documents/" name="my-doc #1">
    <elementSet name="Dublin Core">
        <element name="Title">
            <data>Foo Bar</data>
        </element>
        <element name="Creator">
            <data>John Smith</data>
        </element>
    </elementSet>
    <extra>
        <data name="collection">My collection</data>
        <data name="item type">Still Image</data>
        <data name="featured">1</data>
        <data name="public">1</data>
        <data name="tag">First tag</data>
        <data name="tag">Second tag</data>
        <data name="Another field">This field can be written in the static repository with a special format.</data>
    </extra>
    <record file="http://localhost/path/to/the/file" />
</record>
```

### METS and ALTO XML

Any METS file can be imported as long as the profile uses Dublin Core metadata.
Else, the class should be extended.

The associated [Alto] file, an OCR format, can be ingested too as text. The
plugin [OcrElementSet] should be installed first to create fields for it,
because texts are saved at file level. Else, a hook can be used to import data
somewhere else.

The plugin [OcrElementSet] saves ocr about each image at file level, so the
option "File Metadata" should be set.

Two extra parameters can be managed:

- "mets_fileGrp_document": allows to set the main file, if wanted and if any
("document" by default).
- "mets_fileGrps": allows to set the groups of files to import. This avoids
to import only main files and not the thumbnails or other unwanted files. The
default is "master, ocr, MASTER, OCR". If set to empty, only the first file
group will be imported.

Note: The namespace of the xslt stylesheets may need to be changed according to
your files, or extend the class "ArchiveFolder_Mapping_Mets".

### Table via OpenDocument Spreadsheet (`ods`)

See examples in `tests/suite/_files/Folder_Test/External_Metadata.ods` and
`tests/suite/_files/Folder_Test_Update/External_Metadata_Update.ods`.

The first row of each sheet represents the element to import. Order of columns
is not important. Unknown headers should be managed by the formats.

One row can represent multiple records and multiple rows can represent one
record. This is the case for example for a file without metadata that is set on
a row for an item, or when there is a new collection on the same row, or when
there are multiple files attached to an item.

To add multiple values to the same element, for example multiple authors, three
ways can be used:

1. Set them in one or multiple columns with the same header;
2. Fill the cell with multiple values separated by an element delimiter, like
pipe `|` or a character `end of line`;
3. Repeat the data in some other rows, as long as the identifier is set and that
there is no column "action", in which case each row is processed separately.

Metadata for a file should be set after the item ones and require a column
`Document` that indicates the item to which the file is attached.

Beside the [fork of Csv Import], some headers changed:

- `ItemType` and `RecordType` are replaced by `Item Type` and `Record Type`;
- `FileUrl` is replaced by `Files` (or `File`);
- Extra data are now written with the array notation, so they are different from
standard elements: for example, `geolocation : latitude` is replaced by `geolocation [ latitude ]`;
- The standard delimiter ":" can still be used for extra data, but the value
will be an array like other elements, not a string;
- As identifier, it's recommended to use a true value from an element field like
the "Dubliin Core:Identifier";
- To enter an empty value, that may be required by some extra data like the
standard [Geolocation] plugin, enter the value "Empty value" (case sensitive) or
the one you specified; This may be required when the action is "replace" too.
- if an extra data has only one value, it will be a string. To force an array,
add an element delimiter ("|" by default).
- TODO Convert OpenDocument styles into html ones.

Three modes allows to link collections, items and files between rows.

- The recommended format is the cleares: fill a column "Collection" for items
and a column "Item" for files, where the value is the identifier, that is
generally the "Dublin Core:Identifier".
- An index may be used with the column "name". All rows with the same name
belong to the same document.
- Else, the documents are processed as ordered, so a file belong to the previous
item. This is always the case if there is a column "action".

### Text (example for testing purpose)

The text format for the metadata is just an example to try the plugin.It's a
simple tagged file format, very similar to `.ini` formats: a metadata is a line
that starts with the name of the element set (`Dublin Core` by default),
followed by a colon `:` and by the name of the element (`Title`). The value is
separated from the field name with an equal sign `=`. If this character is not
present, the line is ignored, so it can be a comment. If an element has multiple
lines, next lines start with two non significant spaces. Fields names and values
are trimmed. Because values are trimmed, an empty line between two fields is not
taken into account. Fields are repeatable. All documents in a file are merged.

The `File` field, with the path to the file (absolute or relative to the
metadata file), is needed only for a flat folder or when there are metadata for
files. Metadata for each file are managed like the item. All lines next to a
`File` line are attached to this file. If one file is referenced, all other
files should be referenced too, even if they don't have metadata.

If there are multiple records in the file, they should be preceded by an `Item`
field (the first one is optional).

There is no need to escape any character, unlike many other formats. The
extension of the file should be a double one: ".metadata.txt".

```txt
Title = The Title
Creator = John Smith
Creator=Mary Smith
Description = This is the Dublin Core Description of this document.
  This is the second line of the description, after a line break.
This line is a comment, because the equal sign is absent and it doesn't begin with two spaces.
Date = 2015

File = Image_1.jpg
Title = The first Image
Rights = Creative Commons CC-BY-SA

File = Image_2.jpg
Title = The second Image
Rights = Public Domain

Item = Document 2
Title = Second Document
```

### OpenDocument Text (`odt`) (example for testing purpose)

This format is the same than the text one, except that the two spaces are
replaced by two underscores `__`. It is only added as an example.


Add a custom format
-------------------

Two filters and associated classes are available to create xml documents for
custom formats.

* `archive_folder_mappings`

This filter makes the mapping between the metadata files and the elements that
exists in Omeka. This filter is required to process metadata files. The mapping
should be done at least for Dublin Core, because this is the base format of
Omeka.

If the format is an xml one, it can be copied as this in the final xml document.

Note: In Omeka, all metadata are flat by default, so the hierarchical structure
of a complex XML file should be interpreted. For [Ead], the element "Dublin Core:Relation"
is used. It's recommended to use [Dublin Core Extended] to improve the meaning
of links.

* `archive_folder_ingesters`

This filter makes the mapping for specific files that are part of another file.
Currently, this filter is used only to import Alto xml files for OCR.


Update of documents
-------------------

To be updated, a document should contains an unique identifier. Commonly, this
is a value in the element "Dublin Core:Identifier", but it may be another one,
like the title.

### Update of metadata (standard elements)

The update process updates all metadata of each record. It uses the core
functions of Omeka. When a metadata doesn't exist any more, Omeka keeps them by
default. It is useful if you add other metadata inside Omeka, but it can cause
synchronisation issues if you re-process the metadata after updating them.

So, three modes of update are possible:
- update only metadata that are set and keep other ones (action "update");
- add metadata that are set and keep other ones (action "add";
- replace all metadata that are set (action "replace").

### Deletion of documents

A record can be deleted with the action "delete".

Known issues
------------

### Extra data: Geolocation

The official [Geolocation] requires five fields to be able to ingest location.
So, for all formats, you should set all of them, even with an empty string for
the map type and the address (case sensitive):

```
    geolocation [latitude]
    geolocation [longitude]
    geolocation [zoom_level]
    geolocation [map_type]
    geolocation [address]
```

For the format Open Document Spreadsheet (ods), a value should be set in all of
these cells, because, empty cells are skipped.

| geolocation [latitude] | geolocation [longitude] | geolocation [zoom_level] | geolocation [map_type] | geolocation [address] |
| ----------------------:| -----------------------:| ------------------------:| ---------------------- | --------------------- |
|             48.8583701 |               2.2944813 |                       17 | hybrid                 | Empty value           |

Because in a cell, an empty value is not different from an absent value, the
selected string ("Empty value" by default, case sensitive) should be used. To
avoid this issue, you should use the [fixed release] of Geolocation.

If you want to import multiple points by item with the [fork of Geolocation],
this can be used too:

```
    geolocation [0] [latitude]
    geolocation [0] [longitude]
    geolocation [0] [zoom_level]
    geolocation [0] [map_type]
    geolocation [0] [address]
```

### Open Document Text and Spreadsheet

For metadata files `odt` and `ods`, double or multiple successive spaces are
merged into one space.

### Import of metadata files with the same extension than files

Import of files `ods` and `odt` are not possible, because these files are
ingested as metadata files. If wanted, currently, they should be disabled
via the filter directly in the code of the plugin.

### Extensions and Media types

Files are checked with the white-list of extensions set in in the page "/admin/settings/edit-security",
but the media type is currently not checked when the folder is prepared. Anyway,
it is checked during the import.

### Proxy and https

The harvester may have issues if files are available through "https", but cached
by a proxy. In that case, you will have to wait some minutes (or days) before
re-process, or to check settings of the proxy and the server.

### Xml Alto

The namespace of the xslt stylesheets may need to be changed according to your
files.

### Order of files

When a large number of files are attached to an item, their order may be lost
because the server imports them in parallel. So an option in the batch edit
mechanism allows to reorder them by filename: go to items/browse > check boxes
of items > click the main button Edit > check the box "Order files" and process.


Warning
-------

Use it at your own risk.

It's always recommended to backup your files and database regularly so you can
roll back if needed.


Troubleshooting
---------------

See online issues on the [plugin issues] page on GitHub.


License
-------

This plugin is published under the [CeCILL v2.1] licence, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software's author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user's
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.
This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.


Contact
-------

Current maintainers:

* Daniel Berthereau (see [Daniel-KM] on GitHub)


Copyright
---------

* Copyright Daniel Berthereau, 2015-2016


[Archive Folder]: https://github.com/Daniel-KM/ArchiveFolder
[Omeka]: https://www.omeka.org
[fork of Csv Import]: https://github.com/Daniel-KM/CsvImport
[Xml Import]: https://github.com/Daniel-KM/XmlImport
[fixed release]: https://github.com/Daniel-KM/Geolocation
[Mets]: https://www.loc.gov/standards/mets
[Ead]: https://www.loc.gov/standards/ead
[Ead for Omeka]: https://github.com/Daniel-KM/Ead4Omeka
[Dublin Core Extended]: https://github.com/omeka/plugin-DublinCoreExtended
[Alto]: https://www.loc.gov/standards/alto
[OcrElementSet]: https://github.com/Daniel-KM/OcrElementSet
[Geolocation]: https://omeka.org/add-ons/plugins/geolocation
[fork of Geolocation]: https://github.com/Daniel-KM/Geolocation
[Apache httpd]: https://httpd.apache.org
[Jpeg 2000]: http://www.jpeg.org/jpeg2000
[refNum]: http://bibnum.bnf.fr/refNum
[refNum2Mets]: https://github.com/Daniel-KM/refNum2Mets
[plugin issues]: https://github.com/Daniel-KM/ArchiveFolder/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"

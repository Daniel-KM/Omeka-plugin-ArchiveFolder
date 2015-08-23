Archive Folder (plugin for Omeka)
=================================

[Archive Folder] is a plugin for [Omeka] that allows to import a folder of files
and/or metadata and to create or update items in Omeka. Files can be local or
remote.

Instead of using a specific import schema for a specific API, it follows the
standard [OAI-PMH static repository]. By this way, the folder can be fetched by
any standard OAI-PMH harvesters, in particular the one available for Omeka,
[OAI-PMH Harvester], through any standard OAI-PMH gateway, in particular another
plugin for Omeka, [OAI-PMH Gateway]. In other words, you can self-harvest, or
ingest, your own directories, files and metadata via a standard process.

Concretely, just install these three plugins, set a local folder or a remote
one with files and/or metadata, then they will be harvested automatically and
available directly in Omeka (see the included example below).

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

### Included Example

A sample folder with some metadata is available in "tests/suite/_files/Folder_test".
To test it, follow these steps:
- install the fixed and improved [fork] of [OAI-PMH Harvester], [OAI-PMH Gateway]
and this plugin;
- if you want to test import of extra data, install [Geolocation] too;
- if you want to import all metadata that are in examples, allow the formats
`xml` and `json` and check the default extensions for `ods`, `odt` and `txt` in
the page `/admin/settings/edit-security`, and the same too for the respective
media types `application/xml`, `text/xml`, `application/json`, and default
`application/vnd.oasis.opendocument.spreadsheet`, `application/vnd.oasis.opendocument.text`
and `text/plain`;
- copy the folder outside of the Omeka install, somewhere the server can access;
- click on "Add Folder" in the "Archive Folders" tab;
- fill the base uri, something like `http://localhost/path/to/the/Folder_Test`;
- don't care about other parameters, they will be default;
- click on the submit button;
- click on "Check" to check the folder (results are displayed when the page is
refreshed);
- if there is no issue, click "Update" to process the folder.

After a few seconds, the static repository will be automatically available via
the OAI-PMH Gateway, and the harvest will be launched.

It can take a few tens of seconds for the harvester to import documents in Omeka,
according to the server. Furthermore, in order to preserve memory and cpu, the
harvest process can be done in multiple steps so if the twenty items are not
loaded in one time, just re-launch the harvester (without updating folder) or
increase values of parameters of the harvester.

See the notes below for other issues.

You can try the update of this harvest too:
- replace file "Dir_B/Subdir_B-A/document.xml" and/or "Dir_B/Subdir_B-A/document_external.xml"
of your copied directory by the matching ones that are prepared in the directory
"tests/suite/_files/Update_files/";
- click on "Update" in the "Archive folders" tab.

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

will be available as a standard OAI-PMH Repository (simplified here):

```xml
    <Repository>
      <Identify>
        <oai:repositoryName>My Folder</oai:repositoryName>
        <oai:baseURL>http://example.org/gateway/example.org/repository/my_folder.xml</oai:baseURL>
      </Identify>
      <ListRecords metadataPrefix="oai_dc">
        <oai:record>
          <oai:header>
            <oai:identifier>oai:example.org:1</oai:identifier>
          </oai:header>
          <oai:metadata>
            <oai_dc:dc>
              <dc:title>image_n1</dc:title>
              <dc:identifier>http://exemple.org/repository/My_Folder/image_n1.jpg</dc:identifier>
            </oai_dc:dc>
          </oai:metadata>
        </oai:record>
    [...]
      </ListRecords>
    </Repository>
```

### Nested folders

The folder can contains sub-folders, so, if wanted, each folder can be imported
as an item with multiple files.

```
    My Nested Folder
    ├── Item_1
    │   ├── Item_2
    │   │   ├── my_image_1.jpg
    │   │   └── my_image_2.jpg
    │   ├── my_image_1.jpg
    │   └── my_image_2.jpg
    ├── Item_3
    │   ├── my_image_3.jpg
    │   └── my_image_4.jpg
    ├── my_image_5.jpg
    └── my_image_6.jpg
```

### Metadata ingest

The metadata of each item can be imported if they are available in files in a
supported format. Currently, some formats are implemented: a simple text one
(as raw text or as OpenDocument Text `odt`), a simple json format, a table one
(as OpenDocument Spreadsheet `ods`), the [Mets] xml, if the profile is based on
Dublin Core, and an internal xml one, `Documents`, that allows to manage all
specificities of Omeka. Other ones can be easily added via a simple class.

```
    My Digitized Books
    ├── Book_1.xml
    ├── External.metadata.txt
    ├── Book 1
    │   ├── Page_1.tiff
    │   ├── Page_2.tiff
    │   ├── Page_3.tiff
    │   └── Page_4.tiff
    └── Book 2
        ├── Book_2.metadata.txt
        ├── Page_1.tiff
        ├── Page_2.tiff
        ├── Page_3.tiff
        └── Page_4.tiff
```

Notes for metadata files:

- A metadata file can contain multiple documents.
- Referenced files in metadata files can be external to the original folder.
- Metadata files can be anywhere in the folder, as long as the paths to the
referenced files urls are absolute or relative to it.

See below for more details on metadata files.


Installation
------------

Install first the plugins [Archive Folder Document], [OAI-PMH Harvester], and
['Oai-PMH Gateway]. Even if only the first one is required, the latter are used
to import data inside the Omeka database.

Note: the official [OAI-PMH Harvester] can only ingest standard metadata
(elements). If you want to ingest other standards, files and metadata of files,
extra data, and to manage records, you should use the fixed and improved [fork]
of it.

The optional plugin [OAI-PMH Repository] can be used to expose data directly
from Omeka. Note: the official [OAI-PMH Repository] has been completed in an
[improved fork], in particular with a human interface, and until merge of the
commits, the latter is recommended.

Then uncompress files and rename plugin folder `ArchiveFolder`.

Then install it like any other Omeka plugin and follow the config instructions.

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

The xslt processor of php is a slow xslt 1 one. So it's recommended to use an
external xslt 2 processor, ten to twenty times faster. The command can be
configured in the configuration page of the plugin. Use "%1$s", "%2$s", "%3$s",
without escape, for the file input, the stylesheet, and the output.

Examples for Debian / Ubuntu / Mint:
```
saxonb-xslt -ext:on -versionmsg:off -s:%1$s -xsl:%2$s -o:%3$s
CLASSPATH=/usr/share/java/Saxon-HE.jar java net.sf.saxon.Transform -ext:on -versionmsg:off -s:%1$s -xsl:%2$s -o:%3$s
```

Example for Fedora / RedHat / Centos / Mandriva:
```
saxon -ext:on -versionmsg:off -s:%1$s -xsl:%2$s -o:%3$s
```


Formats of metadata
-------------------

### Common notes

* See examples in the folder "tests/suite/_files" of the plugin.

* `Dublin Core` (simple or qualified if wanted) is the default element set. The
element `Title` corresponds to `Dublin Core : Title`.

* If the element set name is not set, the check is case insensitive; `title`
will be imported as `Dublin Core : Title`. Else, the check is case sensitive:
`Dublin Core : title` will not be identified.

* If the element name contains a `:`, it will be interpreted as a standard
element, else as an extra data, that will be harvested via format `Documents`
(see below). Extra data can be the `Item type`, the `collection`, the `tags`,
`featured`, `public`, etc.

* A static repository does not allow sets, so there will be only one collection
by folder. Nevertheless, the use of the format of harvesting `Documents` allows
to ingest records in other collections (see below).

* Internal relative paths should be relative to the metadata file where they are
referenced.

* Xml files can be imgested as this in the static repository, as long as there
is an associated class, that is needed to convert the metadata to the required
Dublin Core format.

### Documents XML

This internal and simple format is a pivot format and is designed to be internal
only, not to be exposed.

It has only the five tags `record`, `elementSet`, `element`, `extra` and `data`
under the root tag `documents` allows to import all metadata and specific fields
of Omeka, so this is the one that is set by default for the harvesting. For
compatibilty purposes, it supports the tags of the Dublin Core (simple or
qualified). Standard attributes of the record can be set as extra data.

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

### Text

The text format for the metadata is a simple tagged file format, very similar to
`.ini` formats: a metadata is a line that starts with the name of the element
set (`Dublin Core` by default), followed by a colon `:` and by the name of the
element (`Title`). The value is separated from the field name with an equal sign
`=`. If this character is not present, the line is ignored, so it can be a
comment. If an element has multiple lines, next lines start with two non
significant spaces. Fields names and values are trimmed. Because values are
trimmed, an empty line between two fields is not taken into account. Fields are
repeatable.

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

### OpenDocument Text (`odt`)

This format is the same than the text one, except that the two spaces is
replaced by two underscores `__`.

### OpenDocument Spreadsheet (`ods`)

The first row of each sheet represents the element to import. Order of columns
is not important. Unknown headers should be managed by the formats.

To add multiple values to the same elements, for example multiple authors,
three ways can be used:

1. Set them in one or multiple columns with the same header;
2. Repeat the data in some other rows, as long as there is a name in a column
`Document`, or, if this is a file, to the file attached to the current document;
3. Fill the cell with multiple values separated by an element delimiter, like
pipe `|` or a character `end of line`.

Metadata for a file should be set after the item ones and require a column
`Document` that indicates the item to which the file is attached.

Beside [Csv Import], some headers changed:

- `ItemType` and `RecordType` are replaced by `Item Type` and `Record Type`;
- `FileUrl` is replaced by `Files` (or `File`);
- extra data are now written with the array notation, so they are different from
standard elements: for example, `geolocation:latitude` is replaced by `geolocation[latitude]`;
- all headers used to update a record are replaced by a unique extra identifier
`Name` (or `Document`);
- the update of a record always overwrites all of previous data, because the
static repository contains only full records;
- the only available `action` is `delete`, used only with the harvest format
`Documents`.

### METS XML

Any METS file can be imported as long as the profile uses Dublin Core metadata.
Else, the class should be extended.


Add a custom format
-------------------

Three filters and associated classes are available to create static repositories
for custom formats.

* `archive_folder_mappings`

This filter makes the mapping between the metadata files and the elements that
exists in Omeka. This filter is required to process metadata files. The mapping
should be done at least for Dublin Core, because this is the base format of
Omeka and OAI-PMH.

If the format is an xml one, it can be copied as this in the final static
repository. In that case, the ingest should use this format and the mapping
should be done with the filter `oai_pmh_harvester_maps`.

Note: In Omeka, all metadata are flat by default, so the hierarchical structure
of a complex XML file should be interpreted.

* `archive_folder_formats`

This filter specifies a class that defines a format that will be used as a
metadata format in the static repository. It is not needed as long as all data
are mapped into the Omeka format with the filter `archive_folder_mappings` so
that the import can be done with default formats, in particular the `Documents`
one. On the contrary, of course, It's required if the import is done with this
format, in particular when the xml is raw copied.

* `oai_pmh_harvester_maps`

This filter processes the import inside Omeka. It is required only if the format
is designed to be ingested by an harvester.


Harvesting extra data and metadata inside original files
--------------------------------------------------------

The import into Omeka is done via the [OAI-PMH Harvester] plugin. Because this
is a standard, only standard elements are imported via the standard formats
(Dublin Core and [METS]). The format `Documents` allows to import extra data,
for example the collection, the item type, the featured and public status, the
tags, and any other data that are managed by specific plugins.

With the [OAI-PMH Harvester] plugin and the "documents" format, extra data are
imported via two ways.

* Standard "Post": the name should be the same that is used in the form of the
original plugin, for example `geolocation[latitude]` for the latitude of an item
in array notation with the plugin [Geolocation].
* If this is not possible, the hook `archive_folder_ingest_data` should be set
and managed in a plugin. This hook is called after the harvest of each record.

Furthermore, this hook can be used to ingest data that are contained inside
original files, in particular for audio, photo and video files.


Update of documents
-------------------

According to the specifications of OAI-PMH protocol, the static repository can
be updated. The update is done for a whole record: it's not possible to add or
remove a specific element.

In practice, there are two updates: the update of records inside a folder, that
builds the static repository, and the update inside Omeka, realized througn the
harvester.

The update is based on the oai identifier and the date stamp of each record.

### OAI identifier

This identifier is built with the original path of each document and files and
their name. For records defined in metadata files, the path (for files) and the
name (for document) are used too. When there is no path and no name, the order
in the folder and in the metadata file is used.

So, when using metadata files, it's recommended to use a unique name for each
document (unique across all the folder). If not, new documents should be added
at the end of the list of records. If not, you shouldn't update metadata files
in the static repository, else updates may be applied on wrong records.

### Update of metadata (standard elements)

The update process updates all metadata of each item and files. It uses the
core functions of Omeka. When a metadata doesn't exist any more, Omeka keeps
them by default. It is useful if you add other metadata inside Omeka, but it can
cause synchronisation issues if you re-harvest the metadata.

So, when you set up a harvest, three choices are possible:
- keep all old metadata, updated or not (Omeka default);
- remove only metadata from elements that have been updated, so specific
metadata that have been added in other elements are kept (plugin default);
- remove all old metadata, so the items will be strictly the same that in the
static repository.

Tip: The good process depends on the static repository, but, generally, when you
want to add or to modify metadata, the better is to update records directly in
the folder and in metadata files.

### Date stamp

A static repository requires a date stamp without time, because the finality of
such a repository is to be static and stable.

Therefore, if the static repository is updated the same day but after an harvest
has been done, the updated metadata will never be harvested. This issue applies
too for badly formatted repositories.

A checkbox allows to bypass this limitation when the harvester uses the default
`Documents` format, because it is designed for internal management only.

This constraint applies only to the harvest: the update of the static repository
itself can be done at any time.

### Deletion of documents

A static repository doesn't manage the deleted status. A record can be deleted
and removed from the repository, but the harvester won't see it, so it won't be
removed from Omeka.

This limitation can be bypassed only when the harvester uses the `Documents`
format and that there is an extra data `action` with the value `delete`.

### Harvesting Throttle

if the request exceeds the threshold (5 seconds by default), the harvest is not
processed. A shorter or longer delay can be set in the main "config.ini" file:
`plugins.OaipmhHarvester.wait = 5`.


Known issues
------------

### Extra data: Geolocation

The official [Geolocation] requires five fields to be able to ingest location.
So, for all formats, you should set all of them:

```
    geolocation[latitude]
    geolocation[longitude]
    geolocation[zoom_level]
    geolocation[map_type]
    geolocation[address]
```

For the format Open Document Spreadsheet (ods), a value should be set in all of
these cells, because, empty cells are skipped.

| geolocation[latitude] | geolocation[longitude] | geolocation[zoom_level] | geolocation[map_type] | geolocation[address] |
| ---------------------:| ----------------------:| -----------------------:| --------------------- | -------------------- |
|            48.8583701 |              2.2944813 |                      17 | Google Maps v3.x      | -                    |

If you use a spreadsheet and don't want to set a false address or map type,
you should use the [fixed release] of Geolocation.

### Open Document Text and Spreadsheet

For metadata files `odt` and `ods`, double or multiple successive spaces are
merged into one space.

### Import of metadata files with the same extension than files

Import of files `ods` and `odt` are not possible, because these files are
ingested as metadata files.

### Extensions and Media types

Files are checked with the white-list of extensions set in in the page "/admin/settings/edit-security",
but the media type is currently not checked when the static repository is built.
Anyway, it is checked during the harvest.

### Oai identifiers

If names inside metadata files are not unique across all the folder, the plugin
can't determine which record may be updated. So, if using metadata files, it's
recommended to use a unique name for each document (unique across all the
folder).

### Proxy and https

The harvester may have issues if files are available through "https", but cached
by a proxy. In that case, you will have to wait some minutes (or days) before
re-harvest, or to check settings of the proxy and the server.

### TODO

- List element texts set by the harvester in the table harvest_records, for the
item and attached files in order to keep other ones, manually changed in Omeka?


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

* Copyright Daniel Berthereau, 2015


[Archive Folder]: https://github.com/Daniel-KM/ArchiveFolder
[Omeka]: https://www.omeka.org
[OAI-PMH static repository]: https://www.openarchives.org/OAI/2.0/guidelines-static-repository.htm
[OAI-PMH Harvester]: https://omeka.org/add-ons/plugins/oai-pmh-harvester
[OAI-PMH Gateway]: https://github.com/Daniel-KM/OaiPmhGateway
[fork of Csv Import]: https://github.com/Daniel-KM/CsvImport
[Xml Import]: https://github.com/Daniel-KM/XmlImport
[improved fork]: https://github.com/Daniel-KM/OaiPmhRepository
[fork]: https://github.com/Daniel-KM/OaipmhHarvester
[fixed release]: https://github.com/Daniel-KM/Geolocation
[Mets]: https://www.loc.gov/standards/mets
[Geolocation]: https://omeka.org/add-ons/plugins/geolocation
[Apache httpd]: https://httpd.apache.org
[Archive Folder Document]: https://github.com/Daniel-KM/ArchiveFolderDocument
[OAI-PMH Repository]: https://omeka.org/add-ons/plugins/oai-pmh-repository
[plugin issues]: https://github.com/Daniel-KM/ArchiveFolder/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"

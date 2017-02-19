<?xml version="1.0" encoding="UTF-8"?>
<!--
    Description : Extract metadata to be used in Omeka from an xml mets file.

    METS "Metadata Encoding and Transmission Standard" is an xml format largely
    used manage digitalized documents.

    Notes
    - Only profiles where descriptive metadata are saved as Dublin Core (simple
    or terms) are managed currently.
    - Structural metadata should be simple: all subtile features of the Mets are
    not managed.
    - The main object of this sheet is to import metadata into Omeka, so all
    data of the Mets are not imported.

    See below for the parameters.

    @copyright Daniel Berthereau, 2012-2016
    @license CeCILL v2.1 http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
    @package Omeka/Plugins/ArchiveFolder
    @see https://www.loc.gov/standards/mets
-->

<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"

    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:dcterms="http://purl.org/dc/terms/"

    xmlns:mets="http://www.loc.gov/METS/"
    xmlns:premis="info:lc/xmlns/premis-v2"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xlink="http://www.w3.org/1999/xlink"
    xmlns:niso="http://www.niso.org/Z39-87-2006.pdf"
    xmlns:textmd="info:lc/xmlns/textMD-v3"

    exclude-result-prefixes="
        #default xsl mets premis xsi xlink niso textmd
        "
    >

    <xsl:output method="xml" indent="yes" encoding="UTF-8" />

    <xsl:strip-space elements="*" />

    <!-- Options -->

    <!-- Other options can be used in the file "advanced_manage". -->

    <!-- Profiles of Mets may be very different, so these options allows to set
    some details for the mapping. -->

    <!-- If a collection is set, it will be used. Else, the gen/collection will be used. -->
    <xsl:param name="collection"></xsl:param>

    <!-- The full path of files will be required in next steps. It is built with
    a base path and generally a specific one relative to the current document.
    It can be a local path it it is allowed.
    -->
    <xsl:param name="base_url"></xsl:param>
    <!-- The path of the document to add to the base url, if any. The code below
    may need to be updated (only the formats below are ready). A common prefix
    and suffix can be added too. -->
    <xsl:param name="document_path_prefix"></xsl:param>
    <xsl:param name="document_path"></xsl:param>
    <xsl:param name="document_path_suffix"></xsl:param>
    <!-- <xsl:param name="document_path">objid</xsl:param> -->
    <!-- <xsl:param name="document_path">basename_objid</xsl:param> -->

    <!-- The title of the file can be used by the Book Reader or the UniversalViewer,
    so it is cleaned for title, but kept for the identifier. Possible choices:
    - "order"
    - "orderlabel"
    - "label"
    - "position"
    - 'none"
    The xml "data" title will be added automatically, if any.
    -->
    <xsl:param name="format_file_title">orderlabel</xsl:param>

    <!-- The size of an image can be set in "cm", "inch", "auto", or none. -->
    <xsl:param name="format_file_image_size">auto</xsl:param>
    <!-- The precision is an integer from 1 (not float) to 9.
    It is not used with "auto". -->
    <xsl:param name="format_file_image_precision">1</xsl:param>

    <!-- To simplify future updates, a unique identifier is recommended for each
    file. It can be: "documentId_order", "documentUrl_order", "url", "basename",
    "filename", "file_id", or "none".
    -->
    <xsl:param name="format_file_identifier">file_id</xsl:param>
    <!-- The order can be automatically formatted, or not: "auto", "none" or the
    number (1 to 9, generally 4). -->
    <xsl:param name="format_file_identifier_order">4</xsl:param>

    <!-- Omeka need only the master, because it will rebuild all derivatives.
    The master is the first group of files, except if this option is set. -->
    <xsl:param name="group_of_files_to_use"></xsl:param>

    <!-- Add some useful extra element (not Dublin Core).
    Only the orientation of the image currently.
    -->
    <xsl:param name="add_extra_data">true</xsl:param>

    <!-- Add Alto file. -->
    <!-- TODO Alto. -->
    <xsl:param name="add_alto_file">false</xsl:param>
    <!-- Constants -->

    <!-- Allow to lowercase or uppercase a string (European strings, for xslt 1.0). -->
    <xsl:variable name="uppercase" select="'ABCDEFGHIJKLMNOPQRSTUVWXYZÁÉÍÓÚÀÈÌÒÙÂÊÎÔÛÄËÏÖÜŸÇ'" />
    <xsl:variable name="lowercase" select="'abcdefghijklmnopqrstuvwxyzáéíóúàèìòùâêîôûäëïöüÿç'" />

    <xsl:variable name="precision" select="number($format_file_image_precision)" />

    <!-- Main template. -->
    <xsl:template match="/mets:mets">
        <documents
            xmlns:dc="http://purl.org/dc/elements/1.1/"
            xmlns:dcterms="http://purl.org/dc/terms/"
            >

            <!-- An xml file represents only one document, with multiple files. -->
            <xsl:element name="record">

                <!-- The item type is an extra Omeka value. -->
                <xsl:attribute name="itemType">
                    <xsl:text>Text</xsl:text>
                </xsl:attribute>

                <!-- Check the collection. -->
                <xsl:choose>
                    <xsl:when test="$collection != ''">
                        <xsl:attribute name="collection">
                            <xsl:value-of select="$collection" />
                        </xsl:attribute>
                    </xsl:when>
                </xsl:choose>

                <xsl:call-template name="mets_title" />
                <xsl:call-template name="mets_type" />
                <xsl:call-template name="mets_id" />

                <!-- Copy the division of the record. -->
                <xsl:variable name="divRecord" select="
                    /mets:mets
                    /mets:structMap[@TYPE  = 'physical']
                    /mets:div[1]
                    " />

                <!-- Copy all Dublin Core elements. -->
                <!-- They may be cleaned or repeated, but the input may be not
                clear (no standard separator). -->
                <xsl:call-template name="loopIdsForDublinCore">
                    <xsl:with-param name="ids" select="$divRecord/@DMDID" />
                </xsl:call-template>

                <!-- TODO Add extra data (only if there is a specific element set in Omeka)? -->

                <!-- Attach each image to the record.
                The process uses the files section, because the physical
                structural map may vary between mets. -->
                <xsl:choose>
                    <xsl:when test="$group_of_files_to_use != ''">
                        <xsl:apply-templates select="mets:fileSec
                            /mets:fileGrp[@USE = $group_of_files_to_use]
                            /mets:file" />
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:apply-templates select="mets:fileSec
                            /mets:fileGrp[1]
                            /mets:file" />
                    </xsl:otherwise>
                </xsl:choose>

                <!-- Manage ALTO file if exists. -->
                <!-- TODO -->

            </xsl:element>
        </documents>
    </xsl:template>

    <!-- In Omeka, each file is a record with Dublin Core metadata too. -->
    <xsl:template match="mets:file">
        <!-- Get the div id, that is used later. -->
        <xsl:variable name="fileDivId">
            <xsl:call-template name="fileDivId" />
        </xsl:variable>
        <xsl:variable name="fileDiv" select="//mets:div[@ID = $fileDivId]" />

        <xsl:element name="record">
            <xsl:attribute name="file">
                <xsl:if test="$base_url != ''">
                    <xsl:value-of select="$base_url" />
                    <xsl:if test="substring($base_url, string-length($base_url), 1) != '/'">
                        <xsl:text>/</xsl:text>
                    </xsl:if>
                </xsl:if>

                <xsl:value-of select="$document_path_prefix" />

                <xsl:choose>
                    <xsl:when test="$document_path = 'objid'">
                        <xsl:value-of select="/mets:mets/@OBJID" />
                        <xsl:text>/</xsl:text>
                    </xsl:when>
                    <xsl:when test="$document_path = 'basename_objid'">
                        <xsl:call-template name="lastPart">
                            <xsl:with-param name="string" select="/mets:mets/@OBJID" />
                            <xsl:with-param name="delimiter" select="'/'" />
                        </xsl:call-template>
                        <xsl:text>/</xsl:text>
                    </xsl:when>
                </xsl:choose>

                <xsl:value-of select="$document_path_suffix" />

                <xsl:call-template name="cleanFilepathStart">
                    <xsl:with-param name="filepath" select="mets:FLocat/@xlink:href" />
                </xsl:call-template>
            </xsl:attribute>

            <!-- Currently, these values are not used by XmlImport and are
            managed directly by Omeka. -->
            <xsl:attribute name="order">
                <xsl:value-of select="position()" />
            </xsl:attribute>
            <xsl:if test="@CHECKSUMTYPE = 'md5' or @CHECKSUMTYPE = 'MD5'">
                <xsl:attribute name="md5">
                    <xsl:value-of select="@CHECKSUM" />
                </xsl:attribute>
            </xsl:if>
            <xsl:if test="@SIZE">
                <xsl:attribute name="filesize">
                    <xsl:value-of select="@SIZE" />
                </xsl:attribute>
            </xsl:if>

            <!-- The title can be used by the Book Reader or the UniversalViewer,
            so it is cleaned for title, but kept for the identifier. -->
            <xsl:if test="$format_file_title != 'none'">
                <xsl:variable name="fileDcTitle">
                    <xsl:call-template name="fileDcTitle">
                        <xsl:with-param name="fileDivId" select="$fileDivId" />
                    </xsl:call-template>
                </xsl:variable>

                <xsl:variable name="fileTitle">
                    <xsl:choose>
                        <xsl:when test="$format_file_title = 'order'">
                            <xsl:value-of select="$fileDiv/@ORDER" />
                        </xsl:when>
                        <xsl:when test="$format_file_title = 'orderlabel'">
                            <xsl:value-of select="$fileDiv/@ORDERLABEL" />
                        </xsl:when>
                        <xsl:when test="$format_file_title = 'label'">
                            <xsl:value-of select="$fileDiv/@LABEL" />
                        </xsl:when>
                        <xsl:when test="$format_file_title = 'position'">
                                <xsl:value-of select="position()" />
                        </xsl:when>
                        <xsl:when test="$format_file_title = 'title'">
                                <xsl:value-of select="$fileDcTitle" />
                        </xsl:when>
                    </xsl:choose>
                </xsl:variable>

                <xsl:if test="$fileTitle != '' and $fileTitle != $fileDcTitle">
                    <dc:title>
                        <xsl:value-of select="$fileTitle" />
                    </dc:title>
                </xsl:if>
            </xsl:if>

            <!-- List of descriptive metadata of the current file. -->
            <xsl:call-template name="loopIdsForDublinCore">
                <xsl:with-param name="ids" select="$fileDiv/@DMDID" />
            </xsl:call-template>

            <!-- List of administrative  metadata of the current file. -->
            <xsl:call-template name="loopIdsForDublinCore">
                <xsl:with-param name="ids" select="@ADMID" />
            </xsl:call-template>

            <!-- List of technical metadata of the current file. -->
            <xsl:call-template name="loopIdsForNiso">
                <xsl:with-param name="ids" select="@ADMID" />
            </xsl:call-template>

            <!-- The main identifier of the file. -->
            <xsl:if test="$format_file_identifier != 'none'">
                <dc:identifier>
                    <xsl:choose>
                        <xsl:when test="substring-after($format_file_identifier, '_') = 'order'">
                            <xsl:choose>
                                <xsl:when test="$format_file_identifier = 'documentId_order'">
                                    <xsl:value-of select="/mets:mets/@OBJID" />
                                    <xsl:text>_</xsl:text>
                                </xsl:when>
                                <xsl:when test="$format_file_identifier = 'documentUrl_order'">
                                    <xsl:value-of select="/mets:mets/@OBJID" />
                                    <xsl:text>/</xsl:text>
                                </xsl:when>
                            </xsl:choose>
                            <xsl:choose>
                                <xsl:when test="$format_file_identifier_order = 'none'">
                                    <xsl:value-of select="position()" />
                                </xsl:when>
                                <xsl:when test="$format_file_identifier_order = 'auto'">
                                    <xsl:call-template name="addLeadingZero">
                                        <xsl:with-param name="value" select="position()" />
                                        <xsl:with-param name="count" select="count(../mets:file)
                                            + count(../../mets:fileGrp[@USE = 'ocr' or @USE = 'ALTO']/mets:file)" />
                                    </xsl:call-template>
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:call-template name="addLeadingZero">
                                        <xsl:with-param name="value" select="position()" />
                                        <xsl:with-param name="format" select="$format_file_identifier_order" />
                                    </xsl:call-template>
                                </xsl:otherwise>
                            </xsl:choose>
                        </xsl:when>
                        <xsl:when test="$format_file_identifier = 'url'">
                            <xsl:value-of select="mets:FLocat/@xlink:href" />
                        </xsl:when>
                        <xsl:when test="$format_file_identifier = 'basename'">
                            <xsl:variable name="cleanIdStart">
                                <xsl:call-template name="cleanFilepathStart">
                                    <xsl:with-param name="filepath" select="mets:FLocat/@xlink:href" />
                                </xsl:call-template>
                            </xsl:variable>
                            <xsl:call-template name="removeExtension">
                                <xsl:with-param name="filepath" select="$cleanIdStart" />
                            </xsl:call-template>
                        </xsl:when>
                        <xsl:when test="$format_file_identifier = 'filename'">
                            <xsl:call-template name="lastPart">
                                <xsl:with-param name="string" select="mets:FLocat/@xlink:href" />
                                <xsl:with-param name="delimiter" select="'/'" />
                            </xsl:call-template>
                        </xsl:when>
                        <xsl:when test="$format_file_identifier = 'file_id'">
                            <xsl:value-of select="@ID" />
                        </xsl:when>
                    </xsl:choose>
                </dc:identifier>
            </xsl:if>

            <!-- The image metrics are useless in Omeka, because they are
            recomputed. -->

        </xsl:element>
    </xsl:template>

    <xsl:template match="dc:*">
        <xsl:element name="dc:{local-name()}">
            <xsl:value-of select="." />
        </xsl:element>
    </xsl:template>

    <xsl:template match="dc:dc" />

    <xsl:template match="dcterms:*">
        <xsl:element name="dcterms:{local-name()}">
            <xsl:copy-of select="." />
        </xsl:element>
    </xsl:template>

    <xsl:template match="dc:dcterms" />

    <xsl:template match="mets:mdWrap[@MDTYPE = 'NISOIMG']/mets:xmlData">
        <!-- The original size of image can be "cm", "inch", "auto", or none. -->
        <xsl:call-template name="formatFileFormat" />

        <!-- There may be some general capture information. -->
        <xsl:if test="descendant::niso:imageProducer">
            <dc:publisher>
                <xsl:value-of select="descendant::niso:imageProducer" />
            </dc:publisher>
        </xsl:if>

        <xsl:if test="descendant::niso:processingAgency">
            <dc:contributor>
                <xsl:text>Processing Agency: </xsl:text>
                <xsl:value-of select="descendant::niso:processingAgency" />
            </dc:contributor>
        </xsl:if>

        <xsl:if test="descendant::niso:dateTimeCreated">
            <dcterms:created>
                <xsl:value-of select="descendant::niso:dateTimeCreated" />
            </dcterms:created>
        </xsl:if>

        <!-- The size in pixels. -->
        <xsl:if test="niso:imageWidth != ''
                and niso:imageWidth != '0'
                and niso:imageHeight != ''
                and niso:imageHeight!= '0'" >
            <dc:format>
                <xsl:value-of select="niso:imageWidth" />
                <xsl:text> x </xsl:text>
                <xsl:value-of select="niso:imageHeight" />
                <xsl:text> pixels</xsl:text>
            </dc:format>
        </xsl:if>

        <!-- The media type is set by Omeka too, but in extra data. -->
        <xsl:if test="niso:formatName != ''">
            <dc:format>
                <xsl:value-of select="niso:formatName" />
            </dc:format>
        </xsl:if>

        <xsl:if test="$add_extra_data = 'true'">
            <!-- The orientation needs a specific element set, but this is an
            important data. -->
            <xsl:if test="niso:orientation != ''">
                <xsl:element name="elementSet">
                    <xsl:attribute name="name">Image</xsl:attribute>
                    <xsl:element name="element">
                        <xsl:attribute name="name">Orientation</xsl:attribute>
                        <xsl:element name="data">
                            <xsl:choose>
                                <xsl:when test="niso:orientation = 1">
                                    <xsl:text>0</xsl:text>
                                </xsl:when>
                                <xsl:when test="niso:orientation = 2">
                                    <xsl:text>0</xsl:text>
                                </xsl:when>
                                <xsl:when test="niso:orientation = 3">
                                    <xsl:text>180</xsl:text>
                                </xsl:when>
                                <xsl:when test="niso:orientation = 4">
                                    <xsl:text>180</xsl:text>
                                </xsl:when>
                                <xsl:when test="niso:orientation = 5">
                                    <xsl:text>90</xsl:text>
                                </xsl:when>
                                <xsl:when test="niso:orientation = 6">
                                    <xsl:text>90</xsl:text>
                                </xsl:when>
                                <xsl:when test="niso:orientation = 7">
                                    <xsl:text>270</xsl:text>
                                </xsl:when>
                                <xsl:when test="niso:orientation = 8">
                                    <xsl:text>270</xsl:text>
                                </xsl:when>
                                <xsl:when test="niso:orientation = 9">
                                    <xsl:text>Unknown</xsl:text>
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:text>Error</xsl:text>
                                </xsl:otherwise>
                            </xsl:choose>
                        </xsl:element>
                    </xsl:element>
                    <xsl:element name="element">
                        <xsl:attribute name="name">Flip</xsl:attribute>
                        <xsl:element name="data">
                            <xsl:choose>
                                <xsl:when test="niso:orientation = 1
                                    or niso:orientation = 3
                                    or niso:orientation = 6
                                    or niso:orientation = 8
                                    ">
                                    <xsl:text>false</xsl:text>
                                </xsl:when>
                                <xsl:when test="niso:orientation = 2
                                    or niso:orientation = 4
                                    or niso:orientation = 5
                                    or niso:orientation = 7">
                                    <xsl:text>true</xsl:text>
                                </xsl:when>
                                <xsl:when test="niso:orientation = 9">
                                    <xsl:text>Unknown</xsl:text>
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:text>Error</xsl:text>
                                </xsl:otherwise>
                            </xsl:choose>
                        </xsl:element>
                    </xsl:element>
                </xsl:element>
            </xsl:if>
        </xsl:if>
    </xsl:template>

    <!-- Specific templates. -->

    <!-- Get the main title if it is not set in descriptive metadata. -->
    <xsl:template name="mets_title">
        <xsl:if test="@LABEL != ''">
            <!-- Should take care of some boolean specificities of xslt 1. -->
            <xsl:choose>
                <xsl:when test="mets:dmdSec[1]//dc:title">
                    <xsl:if test="@LABEL != mets:dmdSec[1]//dc:title">
                        <xsl:choose>
                            <xsl:when test="mets:dmdSec[1]//dcterms:title">
                                <xsl:if test="@LABEL != mets:dmdSec[1]//dcterms:title">
                                    <dc:title>
                                        <xsl:value-of select="@LABEL" />
                                    </dc:title>
                                </xsl:if>
                            </xsl:when>
                            <xsl:otherwise>
                                <dc:title>
                                    <xsl:value-of select="@LABEL" />
                                </dc:title>
                            </xsl:otherwise>
                        </xsl:choose>
                    </xsl:if>
                </xsl:when>
                <xsl:when test="mets:dmdSec[1]//dcterms:title">
                    <xsl:if test="@LABEL != mets:dmdSec[1]//dcterms:title">
                        <dc:title>
                            <xsl:value-of select="@LABEL" />
                        </dc:title>
                    </xsl:if>
                </xsl:when>
            </xsl:choose>
        </xsl:if>
    </xsl:template>

    <!-- Get the main type if it is not set in descriptive metadata. -->
    <xsl:template name="mets_type">
        <xsl:if test="@TYPE != ''">
            <!-- Should take care of some boolean specificities of xslt 1. -->
            <xsl:choose>
                <xsl:when test="mets:dmdSec[1]//dc:type">
                    <xsl:if test="@TYPE != mets:dmdSec[1]//dc:type">
                        <xsl:choose>
                            <xsl:when test="mets:dmdSec[1]//dcterms:type">
                                <xsl:if test="@TYPE != mets:dmdSec[1]//dcterms:type">
                                    <dc:type>
                                        <xsl:value-of select="@TYPE" />
                                    </dc:type>
                                </xsl:if>
                            </xsl:when>
                            <xsl:otherwise>
                                <dc:type>
                                    <xsl:value-of select="@TYPE" />
                                </dc:type>
                            </xsl:otherwise>
                        </xsl:choose>
                    </xsl:if>
                </xsl:when>
                <xsl:when test="mets:dmdSec[1]//dcterms:type">
                    <xsl:if test="@TYPE != mets:dmdSec[1]//dcterms:type">
                        <dc:type>
                            <xsl:value-of select="@TYPE" />
                        </dc:type>
                    </xsl:if>
                </xsl:when>
            </xsl:choose>
        </xsl:if>
    </xsl:template>

    <!-- Get the main id if it is not set in descriptive metadata. -->
    <xsl:template name="mets_id">
        <xsl:if test="@OBJID != ''">
            <!-- Should take care of some boolean specificities of xslt 1. -->
            <xsl:choose>
                <xsl:when test="mets:dmdSec[1]//dc:identifier">
                    <xsl:if test="@OBJID != mets:dmdSec[1]//dc:identifier">
                        <xsl:choose>
                            <xsl:when test="mets:dmdSec[1]//dcterms:identifier">
                                <xsl:if test="@OBJID != mets:dmdSec[1]//dcterms:identifier">
                                    <dc:identifier>
                                        <xsl:value-of select="@OBJID" />
                                    </dc:identifier>
                                </xsl:if>
                            </xsl:when>
                            <xsl:otherwise>
                                <dc:identifier>
                                    <xsl:value-of select="@OBJID" />
                                </dc:identifier>
                            </xsl:otherwise>
                        </xsl:choose>
                    </xsl:if>
                </xsl:when>
                <xsl:when test="mets:dmdSec[1]//dcterms:identifier">
                    <xsl:if test="@OBJID != mets:dmdSec[1]//dcterms:identifier">
                        <dc:identifier>
                            <xsl:value-of select="@OBJID" />
                        </dc:identifier>
                    </xsl:if>
                </xsl:when>
            </xsl:choose>
        </xsl:if>
    </xsl:template>

    <!-- Get the first parent division in the structural map for a file.
    Normally, there is only one descriptive metadata by page. -->
    <!-- TODO Manage multiple descriptive metadata by page. -->
    <xsl:template name="fileDivId">
        <xsl:value-of select="/mets:mets/mets:structMap[@TYPE  = 'physical']
            //mets:fptr[@FILEID = current()/@ID]
            /parent::mets:div/@ID" />
    </xsl:template>

    <!-- Get the main title of a file in descriptive metadata. -->
    <xsl:template name="fileDcTitle">
        <xsl:param name="fileDivId" />

        <xsl:variable name="fileDescriptiveMetadataId" select="
            //mets:div[@ID = $fileDivId]/@DMDID" />

        <xsl:value-of select="
            (
                /mets:mets/mets:dmdSec[@ID = $fileDescriptiveMetadataId]
                //dc:title
            |
                /mets:mets/mets:dmdSec[@ID = $fileDescriptiveMetadataId]
                //dcterms:title
            )[1]
            " />
    </xsl:template>

    <xsl:template name="loopIdsForDublinCore">
        <xsl:param name="ids" select="''" />

        <xsl:choose>
            <xsl:when test="$ids = ''">
            </xsl:when>
            <xsl:when test="not(contains($ids, ' '))">
                <xsl:apply-templates select="
                    //mets:*[@ID = $ids]//dc:*
                    |
                    //mets:*[@ID = $ids]//dcterms:*
                    " />
            </xsl:when>
            <xsl:otherwise>
                <xsl:apply-templates select="
                    //mets:*[@ID = substring-before($ids, ' ')]//dc:*
                    |
                    //mets:*[@ID = substring-before($ids, ' ')]//dcterms:*
                    " />
                <xsl:call-template name="loopIdsForDublinCore">
                    <xsl:with-param name="ids" select="substring-after($ids, ' ')" />
                </xsl:call-template>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <xsl:template name="loopIdsForNiso">
        <xsl:param name="ids" select="''" />

        <xsl:choose>
            <xsl:when test="$ids = ''">
            </xsl:when>
            <xsl:when test="not(contains($ids, ' '))">
                <xsl:apply-templates select="
                    //mets:*[@ID = $ids]
                    /mets:mdWrap[@MDTYPE = 'NISOIMG'][descendant::niso:*]
                    /mets:xmlData
                    " />
            </xsl:when>
            <xsl:otherwise>
                <xsl:apply-templates select="
                    //mets:*[@ID = $ids]
                    /mets:mdWrap[@MDTYPE = 'NISOIMG'][descendant::niso:*]
                    /mets:xmlData
                    " />
                <xsl:call-template name="loopIdsForNiso">
                    <xsl:with-param name="ids" select="substring-after($ids, ' ')" />
                </xsl:call-template>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <!-- Set the Dublin Core: format of the file according to param and source. -->
    <xsl:template name="formatFileFormat">
        <xsl:if test="$format_file_image_size != 'none'">
            <xsl:choose>
                <!-- Check if the source size is set. -->
                <xsl:when test="niso:sourceXDimensionValue != ''
                        and niso:sourceXDimensionValue != '0'
                        and niso:sourceYDimensionValue != ''
                        and niso:sourceYDimensionValue != '0'">
                        <dc:format>
                            <xsl:choose>
                                <xsl:when test="$format_file_image_size = 'auto'">
                                    <xsl:value-of select="niso:sourceXDimensionValue" />
                                    <xsl:if test="niso:sourceXDimensionUnit != niso:sourceYDimensionUnit">
                                        <xsl:text> </xsl:text>
                                        <xsl:value-of select="niso:sourceXDimensionUnit" />
                                    </xsl:if>
                                    <xsl:text> x </xsl:text>
                                    <xsl:value-of select="niso:sourceYDimensionValue" />
                                    <xsl:text> </xsl:text>
                                    <xsl:value-of select="niso:sourceYDimensionUnit" />
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:call-template name="convertCmAndInch">
                                        <xsl:with-param name="value" select="niso:sourceXDimensionValue" />
                                        <xsl:with-param name="fromFormat" select="niso:sourceXDimensionUnit" />
                                    </xsl:call-template>
                                    <xsl:text> x </xsl:text>
                                    <xsl:call-template name="convertCmAndInch">
                                        <xsl:with-param name="value" select="niso:sourceYDimensionValue" />
                                        <xsl:with-param name="fromFormat" select="niso:sourceYDimensionUnit" />
                                    </xsl:call-template>
                                    <xsl:choose>
                                        <xsl:when test="$format_file_image_size = 'cm'">
                                            <xsl:text> cm</xsl:text>
                                        </xsl:when>
                                        <xsl:when test="$format_file_image_size = 'inch'">
                                            <xsl:text> inches</xsl:text>
                                        </xsl:when>
                                    </xsl:choose>
                                </xsl:otherwise>
                            </xsl:choose>
                        </dc:format>
                </xsl:when>
                <!-- Check if the size of the image is set. -->
                <xsl:when test="niso:imageWidth != ''
                        and niso:imageWidth != '0'
                        and niso:imageHeight != ''
                        and niso:imageHeight != '0'
                        and niso:xSamplingFrequency != ''
                        and niso:xSamplingFrequency != '0'
                        and niso:ySamplingFrequency != ''
                        and niso:ySamplingFrequency != '0'" >
                    <dc:format>
                        <xsl:choose>
                            <xsl:when test="niso:samplingFrequencyUnit = 3">
                                <xsl:choose>
                                    <xsl:when test="$format_file_image_size = 'cm'">
                                        <xsl:call-template name="round">
                                            <xsl:with-param name="value" select="
                                                number(niso:imageWidth)
                                                div number(niso:xSamplingFrequency)" />
                                        </xsl:call-template>
                                        <xsl:text> x </xsl:text>
                                        <xsl:call-template name="round">
                                            <xsl:with-param name="value" select="
                                                number(niso:imageHeight)
                                                div number(niso:ySamplingFrequency)" />
                                        </xsl:call-template>
                                        <xsl:text> cm</xsl:text>
                                    </xsl:when>
                                    <xsl:otherwise>
                                        <xsl:call-template name="round">
                                            <xsl:with-param name="value" select="
                                                number(niso:imageWidth)
                                                div number(niso:xSamplingFrequency)
                                                div 2.54" />
                                        </xsl:call-template>
                                        <xsl:text> x </xsl:text>
                                        <xsl:call-template name="round">
                                            <xsl:with-param name="value" select="
                                                number(niso:imageHeight)
                                                div number(niso:ySamplingFrequency)
                                                div 2.54" />
                                        </xsl:call-template>
                                        <xsl:text> inches</xsl:text>
                                    </xsl:otherwise>
                                </xsl:choose>
                            </xsl:when>
                            <xsl:otherwise>
                                <xsl:choose>
                                    <xsl:when test="$format_file_image_size = 'cm'">
                                        <xsl:call-template name="round">
                                            <xsl:with-param name="value" select="
                                                number(niso:imageWidth)
                                                div number(niso:xSamplingFrequency)
                                                * 2.54" />
                                        </xsl:call-template>
                                        <xsl:text> x </xsl:text>
                                        <xsl:call-template name="round">
                                            <xsl:with-param name="value" select="
                                                number(niso:imageHeight)
                                                div number(niso:ySamplingFrequency)
                                                * 2.54" />
                                        </xsl:call-template>
                                        <xsl:text> cm</xsl:text>
                                    </xsl:when>
                                    <xsl:otherwise>
                                        <xsl:call-template name="round">
                                            <xsl:with-param name="value" select="
                                                number(niso:imageWidth)
                                                div number(niso:xSamplingFrequency)" />
                                        </xsl:call-template>
                                        <xsl:text> x </xsl:text>
                                        <xsl:call-template name="round">
                                            <xsl:with-param name="value" select="
                                                number(niso:imageHeight)
                                                div number(niso:ySamplingFrequency)" />
                                        </xsl:call-template>
                                        <xsl:text> inches</xsl:text>
                                    </xsl:otherwise>
                                </xsl:choose>
                            </xsl:otherwise>
                        </xsl:choose>
                    </dc:format>
                </xsl:when>
            </xsl:choose>
        </xsl:if>
    </xsl:template>

    <!-- Generic templates. -->

    <!-- Capitalize first character of a string. -->
    <xsl:template name="capitalizeFirst">
        <xsl:param name="string" select="." />

        <xsl:value-of select="concat(translate(substring($string, 1, 1), $lowercase, $uppercase), substring($string, 2))" />
    </xsl:template>

    <!-- Get the last partof a string. -->
    <xsl:template name="lastPart">
        <xsl:param name="string" />
        <xsl:param name="delimiter" />

        <xsl:if test="$delimiter != ''">
            <xsl:choose>
                <xsl:when test="not(contains($string, $delimiter))">
                    <xsl:value-of select="$string" />
                </xsl:when>
                <xsl:otherwise>
                    <xsl:call-template name="lastPart">
                        <xsl:with-param name="string" select="substring-after($string, $delimiter)" />
                        <xsl:with-param name="delimiter" select="$delimiter" />
                    </xsl:call-template>
                </xsl:otherwise>
            </xsl:choose>
        </xsl:if>
    </xsl:template>

    <!-- Remove the useless "./" at the start of the file path. -->
    <xsl:template name="cleanFilepathStart">
        <xsl:param name="filepath" select="." />

        <xsl:choose>
            <xsl:when test="substring($filepath, 1, 2) = './'">
                <xsl:value-of select="substring($filepath, 3)" />
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$filepath" />
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <!-- Remove an extension from a file. -->
    <xsl:template name="removeExtension">
        <xsl:param name="filepath" select="''" />

        <xsl:value-of select="substring-before($filepath, '.')" />
        <xsl:if test="contains(substring-after($filepath, '.'), '.')">
            <xsl:text>.</xsl:text>
            <xsl:call-template name="removeExtension">
                <xsl:with-param name="filepath" select="substring-after($filepath, '.')" />
            </xsl:call-template>
        </xsl:if>
    </xsl:template>

    <!-- Convert between cm and inch and round it if wanted. -->
    <xsl:template name="convertCmAndInch">
        <xsl:param name="value" />
        <xsl:param name="fromFormat" />
        <xsl:param name="toFormat" select="$format_file_image_size" />
        <xsl:param name="round" select="true()" />

        <xsl:variable name="result">
            <xsl:choose>
                <xsl:when test="$fromFormat = $toFormat">
                    <xsl:value-of select="$value" />
                </xsl:when>
                <xsl:when test="$fromFormat = 'cm'">
                    <xsl:value-of select="number($value) div 2.54" />
                </xsl:when>
                <xsl:when test="$fromFormat = 'in' or $fromFormat = 'inch' ">
                    <xsl:value-of select="number($value) * 2.54" />
                </xsl:when>
            </xsl:choose>
        </xsl:variable>

        <xsl:choose>
            <xsl:when test="$round">
                <xsl:call-template name="round">
                    <xsl:with-param name="value" select="$result" />
                </xsl:call-template>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="result" />
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <!-- Round a number to the defined precision. -->
    <xsl:template name="round">
        <xsl:param name="value" select="0" />
        <xsl:param name="precision" select="$precision" />

        <xsl:variable name="intPrecision" select="number($precision)" />

        <xsl:choose>
            <xsl:when test="$intPrecision &gt; 0">
                <xsl:value-of select="round(number($value) * $intPrecision) div $intPrecision" />
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$value" />
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <!-- Format a number with leading zero according to a number. -->
    <xsl:template name="addLeadingZero">
        <xsl:param name="value" select="0" />
        <xsl:param name="count" select="1000000000" />
        <xsl:param name="format" select="''" />

        <xsl:choose>
            <xsl:when test="$count &lt; 10 or $format = '1'">
                <xsl:value-of select="number($value)" />
            </xsl:when>
            <xsl:when test="$count &lt; 100 or $format = '2'">
                <xsl:value-of select="format-number(number($value), '00')" />
            </xsl:when>
            <xsl:when test="$count &lt; 1000 or $format = '3'">
                <xsl:value-of select="format-number(number($value), '000')" />
            </xsl:when>
            <xsl:when test="$count &lt; 10000 or $format = '4'">
                <xsl:value-of select="format-number(number($value), '0000')" />
            </xsl:when>
            <xsl:when test="$count &lt; 100000 or $format = '5'">
                <xsl:value-of select="format-number(number($value), '00000')" />
            </xsl:when>
            <xsl:when test="$count &lt; 1000000 or $format = '6'">
                <xsl:value-of select="format-number(number($value), '000000')" />
            </xsl:when>
            <xsl:when test="$count &lt; 10000000 or $format = '7'">
                <xsl:value-of select="format-number(number($value), '0000000')" />
            </xsl:when>
            <xsl:when test="$count &lt; 100000000 or $format = '8'">
                <xsl:value-of select="format-number(number($value), '00000000')" />
            </xsl:when>
            <xsl:when test="$count &lt; 1000000000 or $format = '9'">
                <xsl:value-of select="format-number(number($value), '000000000')" />
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$value" />
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <!-- Don't write anything else. -->
    <xsl:template match="text()" />

</xsl:stylesheet>

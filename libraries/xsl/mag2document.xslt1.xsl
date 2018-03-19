<?xml version="1.0" encoding="UTF-8"?>
<!--
    Description : Extract metadata to be used in Omeka from an xml mag file.

    Mag "Metadati Amministrativi e Gestionali" is an xml format similar to Mets.
    It is used in Italy to manage administrative data about digitalized
    documents.

    This sheet convert the mag file into a simplified document with metadata
    that are used by Omeka and that can be imported by the plugins Archive Folder
    (recommended) or Xml Import.

    Notes
    - Every field can be commented if not wanted.
    - Useless values for Omeka are not extracted, but they can be added.

    @copyright Daniel Berthereau, 2012-2016
    @license CeCILL v2.1 http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
    @package Omeka/Plugins/ArchiveFolder
    @see http://www.iccu.sbn.it/opencms/opencms/it/main/standard/metadati/pagina_267.html
    @see https://www.loc.gov/standards/mets
-->

<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"

    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:dcterms="http://purl.org/dc/terms/"

    xmlns:mag="http://www.iccu.sbn.it/metaAG1.pdf"
    xmlns:niso="http://www.niso.org/pdfs/DataDict.pdf"
    xmlns:xlink="http://www.w3.org/TR/xlink"

    exclude-result-prefixes="
        xsl mag niso xlink
        "
    >

    <xsl:output method="xml" indent="yes" encoding="UTF-8" />

    <xsl:strip-space elements="*" />

    <!-- Parameters -->

    <!-- The mag standard doesn't describe all possibilities, so these options
    allows to set some details for the mapping. -->

    <!-- If a collection is set, it will be used. Else, the mag:gen/mag:collection will be used. -->
    <xsl:param name="collection"></xsl:param>

    <!-- The full path of files will be required in next steps. It is built with
    a base path and generally a specific one relative to the current document.
    It can be a local path if it is allowed by Csv Import (fork at http://github.com/Daniel-KM/Omeka-plugin-CsvImport)
    of by Archive Folder (http://github.com/Daniel-KM/Omeka-plugin-ArchiveFolder).
    -->
    <xsl:param name="base_url"></xsl:param>
    <!-- The path of the document to add to the base url, if any. The code below
    may need to be updated (only the formats below are ready). A common prefix
    and suffix can be added too. -->
    <xsl:param name="document_path_prefix"></xsl:param>
    <xsl:param name="document_path"></xsl:param>
    <xsl:param name="document_path_suffix"></xsl:param>
    <!-- <xsl:param name="document_path">mag:bib/dc:identifier</xsl:param> -->
    <!-- <xsl:param name="document_path">library_number</xsl:param> -->
    <!-- <xsl:param name="document_path">mag:bib/mag:holdings/mag:inventory_number</xsl:param> -->
    <!-- <xsl:param name="document_path">mag:bib/mag:holdings/mag:shelfmark</xsl:param> -->

    <!-- The title of the file can be used by the Book Reader or the UniversalViewer,
    so it is cleaned for title, but kept for the identifier.
    The nomenclature is not standardized, so it may be different for
    another digitalized collection. Possible choices:
    - "cleaned": convert "tavola.0002" to "Tavola 2",
    - "nomenclature": the title will be the nomenclature.
    - "auto": check if there is a dot and no space to process.
    - 'none"
    -->
    <xsl:param name="format_file_title">auto</xsl:param>

    <!-- The size of an image can be set in "cm", "inch", "auto", or none. -->
    <!-- In mag, "auto" means "inch". -->
    <xsl:param name="format_file_image_size">cm</xsl:param>
    <!-- The precision is an integer from 1 (not float) to 9.
    It is not used with "auto". -->
    <xsl:param name="format_file_image_precision">4</xsl:param>

    <!-- To simplify future updates, a unique identifier is recommended for each
    file. It can be: "documentId_order", "inventory_order", shelfmark_order",
    "basename", or "none".
    -->
    <xsl:param name="format_file_identifier">documentId_order</xsl:param>
    <!-- The order can be automatically formatted, or not: "auto", "none" or the
    number (1 to 9, generally 4). -->
    <xsl:param name="format_file_identifier_order">4</xsl:param>

    <!-- Constants -->

    <!-- Allow to lowercase or uppercase a string (European strings, for xslt 1.0). -->
    <xsl:variable name="uppercase" select="'ABCDEFGHIJKLMNOPQRSTUVWXYZÁÉÍÓÚÀÈÌÒÙÂÊÎÔÛÄËÏÖÜŸÇ'" />
    <xsl:variable name="lowercase" select="'abcdefghijklmnopqrstuvwxyzáéíóúàèìòùâêîôûäëïöüÿç'" />

    <xsl:variable name="precision" select="number($format_file_image_precision)" />

    <!-- Main template. -->
    <xsl:template match="/mag:metadigit">
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
                    <xsl:when test="mag:gen/mag:collection != ''">
                        <xsl:attribute name="collection">
                            <xsl:value-of select="mag:gen/mag:collection" />
                        </xsl:attribute>
                    </xsl:when>
                </xsl:choose>

                <!-- TODO Use Dublin Core:Rights? -->
                <xsl:attribute name="public">
                    <xsl:choose>
                        <xsl:when test="mag:gen/mag:access_rights = 1">
                            <xsl:text>1</xsl:text>
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:text>0</xsl:text>
                        </xsl:otherwise>
                    </xsl:choose>
                </xsl:attribute>

                <!-- TODO Keep other values from the gen? Convert to Dublin Core extended? -->
                <!-- mag:gen/@last_update -->
                <!-- mag:gen/@creation -->
                <!-- mag:stprog -->
                <!-- mag:agency -->
                <!-- mag:completeness -->

                <!-- Get the main type of document. -->
                <xsl:if test="mag:bib/@level != ''">
                    <dc:type>
                        <xsl:choose>
                            <xsl:when test="mag:bib/@level = 'a'">
                                <xsl:text>spoglio</xsl:text>
                            </xsl:when>
                            <xsl:when test="mag:bib/@level = 'm'">
                                <xsl:text>monografia</xsl:text>
                            </xsl:when>
                            <xsl:when test="mag:bib/@level = 's'">
                                <xsl:text>seriale</xsl:text>
                            </xsl:when>
                            <xsl:when test="mag:bib/@level = 'c'">
                                <xsl:text>raccolta prodotta dall'istituzione</xsl:text>
                            </xsl:when>
                            <xsl:when test="mag:bib/@level = 'f'">
                                <xsl:text>unità archivistic</xsl:text>
                            </xsl:when>
                            <xsl:when test="mag:bib/@level = 'd'">
                                <xsl:text>unità documentari</xsl:text>
                            </xsl:when>
                        </xsl:choose>
                    </dc:type>
                </xsl:if>

                <!-- Copy all Dublin Core elements. -->
                <!-- They may be cleaned or repeated, but the input may be not
                clear (no standard separator). -->
                <xsl:apply-templates select="mag:bib/dc:* | mag:bib/dcterms:*" />

                <!-- Add holdings as identifiers. -->
                <xsl:if test="mag:bib/mag:holdings/mag:library != ''">
                    <dc:identifier>
                        <xsl:text>Library: </xsl:text>
                        <xsl:value-of select="mag:bib/mag:holdings/mag:library" />
                    </dc:identifier>
                </xsl:if>
                <xsl:if test="mag:bib/mag:holdings/mag:inventory_number != ''">
                    <dc:identifier>
                        <xsl:text>Inventory number: </xsl:text>
                        <xsl:value-of select="mag:bib/mag:holdings/mag:inventory_number" />
                    </dc:identifier>
                </xsl:if>
                <xsl:if test="mag:bib/mag:holdings/mag:shelfmark != ''">
                    <dc:identifier>
                        <xsl:text>Shelf mark: </xsl:text>
                        <xsl:value-of select="mag:bib/mag:holdings/mag:shelfmark" />
                    </dc:identifier>
                </xsl:if>

                <!-- TODO Add extra data (only if there is a specific element set in Omeka)? -->

                <!-- Attach each image to the record. -->
                <xsl:apply-templates select="mag:img" />

            </xsl:element>

        </documents>
    </xsl:template>

    <xsl:template match="dc:*">
        <xsl:element name="dc:{local-name()}">
            <xsl:value-of select="." />
        </xsl:element>
    </xsl:template>

    <xsl:template match="dcterms:*">
        <xsl:element name="dcterms:{local-name()}">
            <xsl:copy-of select="." />
        </xsl:element>
    </xsl:template>

    <!-- In Omeka, each file is a record with Dublin Core metadata too. -->
    <xsl:template match="mag:img">
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
                    <xsl:when test="$document_path = 'mag:bib/dc:identifier'">
                        <xsl:value-of select="../mag:bib/dc:identifier" />
                        <xsl:text>/</xsl:text>
                    </xsl:when>
                    <xsl:when test="$document_path = 'library_number'">
                        <xsl:call-template name="lastPart">
                            <xsl:with-param name="string" select="../mag:bib/mag:holdings/mag:library" />
                            <xsl:with-param name="delimiter" select="' '" />
                        </xsl:call-template>
                        <xsl:text>/</xsl:text>
                    </xsl:when>
                    <xsl:when test="$document_path = 'mag:bib/mag:holdings/mag:inventory_number'">
                        <xsl:value-of select="../mag:bib/mag:holdings/mag:inventory_number" />
                        <xsl:text>/</xsl:text>
                    </xsl:when>
                    <xsl:when test="$document_path = 'mag:bib/mag:holdings/mag:shelfmark'">
                        <xsl:value-of select="../mag:bib/mag:holdings/mag:shelfmark" />
                        <xsl:text>/</xsl:text>
                    </xsl:when>
                </xsl:choose>

                <xsl:value-of select="$document_path_suffix" />

                <xsl:call-template name="cleanFilepathStart">
                    <xsl:with-param name="filepath" select="mag:file/@xlink:href" />
                </xsl:call-template>
            </xsl:attribute>

            <!-- Currently, these values are not used by XmlImport and are
            managed directly by Omeka. -->
            <xsl:attribute name="order">
                <xsl:choose>
                    <xsl:when test="mag:sequence_number != ''">
                        <xsl:value-of select="number(mag:sequence_number)" />
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:value-of select="position()" />
                    </xsl:otherwise>
                </xsl:choose>
            </xsl:attribute>
            <xsl:if test="mag:md5">
                <xsl:attribute name="md5">
                    <xsl:value-of select="mag:md5" />
                </xsl:attribute>
            </xsl:if>
            <xsl:if test="mag:filesize">
                <xsl:attribute name="filesize">
                    <xsl:value-of select="mag:filesize" />
                </xsl:attribute>
            </xsl:if>

            <!-- The title can be used by the Book Reader or the UniversalViewer,
            so it is cleaned for title, but kept for the identifier.
            The nomenclature is not standardized, so it may be different for
            another digitalized collection. -->
            <xsl:if test="mag:nomenclature != '' and $format_file_title != 'none'">
                <xsl:variable name="formatTitle">
                    <xsl:choose>
                        <xsl:when test="$format_file_title = 'cleaned'
                            or $format_file_title = 'nomenclature'">
                            <xsl:value-of select="$format_file_title" />
                        </xsl:when>
                        <xsl:when test="contains(mag:nomenclature, '.') and not(contains(mag:nomenclature, ' '))">
                            <xsl:text>cleaned</xsl:text>
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:text>nomenclature</xsl:text>
                        </xsl:otherwise>
                    </xsl:choose>
                </xsl:variable>

                <dc:title>
                    <xsl:choose>
                        <xsl:when test="$formatTitle = 'cleaned'">
                            <xsl:call-template name="capitalizeFirst">
                                <xsl:with-param name="string" select="substring-before(mag:nomenclature, '.')" />
                            </xsl:call-template>
                            <xsl:text> </xsl:text>
                            <xsl:value-of select="number(substring-after(mag:nomenclature, '.'))" />
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:value-of select="mag:nomenclature" />
                        </xsl:otherwise>
                    </xsl:choose>
                </dc:title>
            </xsl:if>

            <!-- The original size of image can be "cm", "inch", "auto", or none. -->
            <xsl:call-template name="formatFileFormat" />

            <!-- The size in pixels. -->
            <xsl:if test="mag:image_dimensions/niso:imagewidth != ''
                    and mag:image_dimensions/niso:imagewidth != '0'
                    and mag:image_dimensions/niso:imagelength != ''
                    and mag:image_dimensions/niso:imagelength != '0'" >
                <dc:format>
                    <xsl:value-of select="mag:image_dimensions/niso:imagewidth" />
                    <xsl:text> x </xsl:text>
                    <xsl:value-of select="mag:image_dimensions/niso:imagelength" />
                    <xsl:text> pixels</xsl:text>
                </dc:format>
            </xsl:if>

            <!-- The media type is set by Omeka too, but in extra data. -->
            <xsl:if test="mag:format/niso:mime != ''">
                <dc:format>
                    <xsl:value-of select="mag:format/niso:mime" />
                </dc:format>
            </xsl:if>

            <!-- The main identifier of the file. -->
            <xsl:if test="$format_file_identifier != 'none'">
                <dc:identifier>
                    <xsl:choose>
                        <xsl:when test="substring-after($format_file_identifier, '_') = 'order'">
                            <xsl:choose>
                                <xsl:when test="$format_file_identifier = 'documentId_order'">
                                    <xsl:value-of select="../mag:bib/dc:identifier" />
                                    <xsl:text>_</xsl:text>
                                </xsl:when>
                                <xsl:when test="$format_file_identifier = 'inventory_order'">
                                    <xsl:value-of select="../mag:bib/mag:holdings/mag:inventory_number" />
                                    <xsl:text>_</xsl:text>
                                </xsl:when>
                                <xsl:when test="$format_file_identifier = 'shelfmark_order'">
                                    <xsl:value-of select="../mag:bib/mag:holdings/mag:shelfmark" />
                                    <xsl:text>_</xsl:text>
                                </xsl:when>
                            </xsl:choose>
                            <xsl:choose>
                                <xsl:when test="mag:sequence_number != ''">
                                    <xsl:value-of select="mag:sequence_number" />
                                </xsl:when>
                                <xsl:when test="$format_file_identifier_order = 'none'">
                                    <xsl:value-of select="position()" />
                                </xsl:when>
                                <xsl:when test="$format_file_identifier_order = 'auto'">
                                    <xsl:call-template name="addLeadingZero">
                                        <xsl:with-param name="value" select="position()" />
                                        <xsl:with-param name="count" select="count(../mag:img)" />
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
                        <xsl:when test="$format_file_identifier = 'basename'">
                            <xsl:variable name="cleanIdStart">
                                <xsl:call-template name="cleanFilepathStart">
                                    <xsl:with-param name="filepath" select="mag:file/@xlink:href" />
                                </xsl:call-template>
                            </xsl:variable>
                            <xsl:call-template name="removeExtension">
                                <xsl:with-param name="filepath" select="$cleanIdStart" />
                            </xsl:call-template>
                        </xsl:when>
                    </xsl:choose>
                </dc:identifier>
            </xsl:if>

            <!-- The nomenclature is added as a second identifier. -->
            <xsl:if test="mag:nomenclature != ''">
                <dc:identifier>
                    <xsl:value-of select="mag:nomenclature" />
                </dc:identifier>
            </xsl:if>

            <!-- The image metrics are useless in Omeka, because they are
            recomputed. -->

        </xsl:element>
    </xsl:template>

    <!-- Specific templates. -->

    <!-- Set the Dublin Core: format of the file according to param and source. -->
    <xsl:template name="formatFileFormat">
        <xsl:if test="$format_file_image_size != 'none'">
            <xsl:choose>
                <!-- Check if the source size is set. -->
                <xsl:when test="mag:image_dimensions/niso:source_xdimension != ''
                        and mag:image_dimensions/niso:source_xdimension != '0'
                        and mag:image_dimensions/niso:source_ydimension != ''
                        and mag:image_dimensions/niso:source_ydimension != '0'">
                        <xsl:choose>
                            <xsl:when test="$format_file_image_size = 'cm'">
                                <dc:format>
                                    <xsl:call-template name="round">
                                        <xsl:with-param name="value" select="number(mag:image_dimensions/niso:source_xdimension) * 2.54" />
                                    </xsl:call-template>
                                    <xsl:text> x </xsl:text>
                                    <xsl:call-template name="round">
                                        <xsl:with-param name="value" select="number(mag:image_dimensions/niso:source_ydimension) * 2.54" />
                                    </xsl:call-template>
                                    <xsl:text> cm</xsl:text>
                                </dc:format>
                            </xsl:when>
                            <xsl:otherwise>
                                <dc:format>
                                    <xsl:value-of select="number(mag:image_dimensions/niso:source_xdimension)" />
                                    <xsl:text> x </xsl:text>
                                    <xsl:value-of select="number(mag:image_dimensions/niso:source_ydimension)" />
                                    <xsl:text> inches</xsl:text>
                                </dc:format>
                            </xsl:otherwise>
                        </xsl:choose>
                </xsl:when>
                <!-- Check if the size of the image is set. -->
                <xsl:when test="mag:image_dimensions/niso:imagewidth != ''
                        and mag:image_dimensions/niso:imagewidth != '0'
                        and mag:image_dimensions/niso:imagelength != ''
                        and mag:image_dimensions/niso:imagelength != '0'
                        and mag:image_metrics/niso:xsamplingfrequency != ''
                        and mag:image_metrics/niso:xsamplingfrequency != '0'
                        and mag:image_metrics/niso:ysamplingfrequency != ''
                        and mag:image_metrics/niso:ysamplingfrequency != '0'" >
                    <dc:format>
                        <xsl:choose>
                            <xsl:when test="mag:image_metrics/niso:samplingfrequencyunit = 3">
                                <xsl:choose>
                                    <xsl:when test="$format_file_image_size = 'cm'">
                                        <xsl:call-template name="round">
                                            <xsl:with-param name="value" select="
                                                number(mag:image_dimensions/niso:imagewidth)
                                                div number(mag:image_metrics/niso:xsamplingfrequency)" />
                                        </xsl:call-template>
                                        <xsl:text> x </xsl:text>
                                        <xsl:call-template name="round">
                                            <xsl:with-param name="value" select="
                                                number(mag:image_dimensions/niso:imagelength)
                                                div number(mag:image_metrics/niso:ysamplingfrequency)" />
                                        </xsl:call-template>
                                        <xsl:text> cm</xsl:text>
                                    </xsl:when>
                                    <xsl:otherwise>
                                        <xsl:call-template name="round">
                                            <xsl:with-param name="value" select="
                                                number(mag:image_dimensions/niso:imagewidth)
                                                div number(mag:image_metrics/niso:xsamplingfrequency)
                                                div 2.54" />
                                        </xsl:call-template>
                                        <xsl:text> x </xsl:text>
                                        <xsl:call-template name="round">
                                            <xsl:with-param name="value" select="
                                                number(mag:image_dimensions/niso:imagelength)
                                                div number(mag:image_metrics/niso:ysamplingfrequency)
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
                                                number(mag:image_dimensions/niso:imagewidth)
                                                div number(mag:image_metrics/niso:xsamplingfrequency)
                                                * 2.54" />
                                        </xsl:call-template>
                                        <xsl:text> x </xsl:text>
                                        <xsl:call-template name="round">
                                            <xsl:with-param name="value" select="
                                                number(mag:image_dimensions/niso:imagelength)
                                                div number(mag:image_metrics/niso:ysamplingfrequency)
                                                * 2.54" />
                                        </xsl:call-template>
                                        <xsl:text> cm</xsl:text>
                                    </xsl:when>
                                    <xsl:otherwise>
                                        <xsl:call-template name="round">
                                            <xsl:with-param name="value" select="
                                                number(mag:image_dimensions/niso:imagewidth)
                                                div number(mag:image_metrics/niso:xsamplingfrequency)" />
                                        </xsl:call-template>
                                        <xsl:text> x </xsl:text>
                                        <xsl:call-template name="round">
                                            <xsl:with-param name="value" select="
                                                number(mag:image_dimensions/niso:imagelength)
                                                div number(mag:image_metrics/niso:ysamplingfrequency)" />
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

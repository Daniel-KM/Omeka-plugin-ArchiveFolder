<?xml version="1.0" encoding="UTF-8"?>
<!--
    Description : Extract metadata to be used in Omeka from an xml omeka file.

    This sheet convert the omeka file into a simplified document with metadata
    that are used by Omeka and that can be imported by the plugins Archive Folder
    (recommended) or Xml Import.

    Notes
    - Every field can be commented if not wanted.
    - Useless values for Omeka are not extracted, but they can be added.

    @copyright Daniel Berthereau, 2016
    @package Omeka/Plugins/ArchiveFolder
    @license CeCILL v2.1 http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
    @see http://omeka.org/schemas/omeka-xml/v5/omeka-xml-5-0.xsd
-->

<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"

    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:dcterms="http://purl.org/dc/terms/"

    xmlns:omeka="http://omeka.org/schemas/omeka-xml/v5"

    xmlns:xlink="http://www.w3.org/TR/xlink"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"

    exclude-result-prefixes="
        xsl omeka xlink xsi
        "
    >

    <xsl:output method="xml" indent="yes" encoding="UTF-8" />

    <xsl:strip-space elements="*" />

    <!-- Parameters -->

    <!-- The full path of files is not required with standard export of Omeka,
    but may be useful in some cases.
    -->
    <xsl:param name="base_url"></xsl:param>

    <!-- Constants -->

    <!-- Identity template. -->
    <xsl:template match="@*|node()">
        <xsl:copy>
            <xsl:apply-templates select="@*|node()"/>
        </xsl:copy>
    </xsl:template>

    <!-- Main template. -->
    <xsl:template match="/">
        <documents
            xmlns:dc="http://purl.org/dc/elements/1.1/"
            xmlns:dcterms="http://purl.org/dc/terms/"
            >
            <xsl:apply-templates select="//omeka:collection" />
            <xsl:apply-templates select="//omeka:item" />
        </documents>
    </xsl:template>

    <!-- Records. -->

    <xsl:template match="omeka:collectionContainer">
        <xsl:apply-templates select="omeka:collection" />
    </xsl:template>

    <xsl:template match="omeka:collection">
        <xsl:element name="record">
            <xsl:attribute name="name">
                <xsl:value-of select="concat('collections/show/', @collectionId)" />
            </xsl:attribute>
            <xsl:attribute name="recordType">
                <xsl:text>Collection</xsl:text>
            </xsl:attribute>
            <xsl:if test="@public != ''">
                <xsl:attribute name="public">
                    <xsl:value-of select="@public" />
                </xsl:attribute>
            </xsl:if>
            <xsl:if test="@featured != ''">
                <xsl:attribute name="featured">
                    <xsl:value-of select="@featured" />
                </xsl:attribute>
            </xsl:if>
            <xsl:apply-templates select="omeka:elementSetContainer" />
        </xsl:element>
    </xsl:template>

    <xsl:template match="omeka:itemContainer">
        <xsl:apply-templates select="omeka:item" />
    </xsl:template>

    <xsl:template match="omeka:item">
        <xsl:element name="record">
            <xsl:attribute name="name">
                <xsl:value-of select="concat('items/show/', @itemId)" />
            </xsl:attribute>
            <xsl:attribute name="recordType">
                <xsl:text>Item</xsl:text>
            </xsl:attribute>
            <xsl:choose>
                <xsl:when test="../parent::omeka:collection/@collectionId">
                    <xsl:attribute name="collection">
                        <xsl:value-of select="concat('collections/show/', ../parent::omeka:collection/@collectionId)" />
                    </xsl:attribute>
                </xsl:when>
                <xsl:when test="omeka:collection/@collectionId">
                    <xsl:attribute name="collection">
                        <xsl:value-of select="concat('collections/show/', omeka:collection/@collectionId)" />
                    </xsl:attribute>
                </xsl:when>
            </xsl:choose>
            <xsl:if test="omeka:itemType/omeka:name">
                <xsl:attribute name="itemType">
                    <xsl:value-of select="omeka:itemType/omeka:name" />
                </xsl:attribute>
            </xsl:if>
            <xsl:if test="@public != ''">
                <xsl:attribute name="public">
                    <xsl:value-of select="@public" />
                </xsl:attribute>
            </xsl:if>
            <xsl:if test="@featured != ''">
                <xsl:attribute name="featured">
                    <xsl:value-of select="@featured" />
                </xsl:attribute>
            </xsl:if>
            <xsl:apply-templates select="omeka:elementSetContainer" />
            <xsl:apply-templates select="omeka:tagContainer" />
            <xsl:apply-templates select="omeka:fileContainer" />
        </xsl:element>
    </xsl:template>

    <xsl:template match="omeka:fileContainer">
        <xsl:apply-templates select="omeka:file" />
    </xsl:template>

    <xsl:template match="omeka:file">
        <xsl:element name="record">
            <xsl:attribute name="name">
                <xsl:value-of select="concat('files/show/', @fileId)" />
            </xsl:attribute>
            <xsl:attribute name="recordType">
                <xsl:text>File</xsl:text>
            </xsl:attribute>
            <xsl:attribute name="file">
                <xsl:if test="$base_url != ''
                        and not(substring(omeka:src, 1, 1) = '/')
                        and not(substring(omeka:src, 1, 7) = 'http://')
                        and not(substring(omeka:src, 1, 8) = 'https://')
                    ">
                    <xsl:value-of select="$base_url" />
                    <xsl:if test="substring($base_url, string-length($base_url), 1) != '/'">
                        <xsl:text>/</xsl:text>
                    </xsl:if>
                </xsl:if>
                <xsl:value-of select="omeka:src" />
            </xsl:attribute>
            <xsl:if test="@order != ''">
                <xsl:attribute name="order">
                    <xsl:value-of select="@order" />
                </xsl:attribute>
            </xsl:if>
            <xsl:apply-templates select="omeka:elementSetContainer" />
        </xsl:element>
    </xsl:template>

    <!-- Metadata. -->

    <xsl:template match="omeka:elementSetContainer">
        <xsl:apply-templates select="omeka:elementSet" />
    </xsl:template>

    <xsl:template match="omeka:elementSet">
        <xsl:element name="elementSet">
            <xsl:attribute name="name">
                <xsl:value-of select="omeka:name" />
            </xsl:attribute>
            <xsl:apply-templates select="omeka:elementContainer/omeka:element" />
        </xsl:element>
    </xsl:template>

    <xsl:template match="omeka:element">
        <xsl:element name="element">
            <xsl:attribute name="name">
                <xsl:value-of select="omeka:name" />
            </xsl:attribute>
            <xsl:apply-templates select="omeka:elementTextContainer
                    /omeka:elementText/omeka:text" />
        </xsl:element>
    </xsl:template>

    <xsl:template match="omeka:elementText/omeka:text">
        <xsl:element name="data">
            <xsl:apply-templates select="node()" />
        </xsl:element>
    </xsl:template>

    <xsl:template match="omeka:tagContainer">
        <xsl:element name="extra">
            <xsl:apply-templates select="omeka:tag" />
        </xsl:element>
    </xsl:template>

    <xsl:template match="omeka:tag">
        <xsl:element name="data">
            <xsl:attribute name="name">
                <xsl:text>tags</xsl:text>
            </xsl:attribute>
            <xsl:value-of select="omeka:name" />
        </xsl:element>
    </xsl:template>

</xsl:stylesheet>

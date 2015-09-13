<?xml version="1.0" encoding="UTF-8"?>
<!--
    Description : Extract data about process from an alto file.
    Version : 1.0
    Auteur : Daniel Berthereau

    IMPORTANT: You may need to change the alto namespace below.

    @copyright Daniel Berthereau, 2013-2015
    @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
    @package ArchiveFolder
    @see XmlImport
-->
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:alto="http://bibnum.bnf.fr/ns/alto_prod"
    >

    <xsl:output method="text" encoding="UTF-8"/>

    <!-- Parameters -->

    <!-- Character used for the end of line (standard by default). -->
    <xsl:param name="end_of_line"><xsl:text>&#x0A;</xsl:text></xsl:param>

    <!-- Main template. -->
    <xsl:template match="/alto:alto">
        <xsl:for-each select="alto:Description/alto:OCRProcessing[last()]">
            <xsl:if test="@ID">
                <xsl:text>ID: </xsl:text>
                <xsl:value-of select="@ID" />
                <xsl:value-of select="$end_of_line" />
            </xsl:if>
            <xsl:if test="alto:ocrProcessingStep[last()]/alto:processingDateTime">
                <xsl:text>Date: </xsl:text>
                <xsl:value-of select="alto:ocrProcessingStep[last()]/alto:processingDateTime" />
                <xsl:value-of select="$end_of_line" />
            </xsl:if>
            <xsl:for-each select="alto:ocrProcessingStep[last()]/alto:processingStepDescription">
                <xsl:value-of select="." />
                <xsl:value-of select="$end_of_line" />
            </xsl:for-each>
            <xsl:if test="alto:ocrProcessingStep[last()]/alto:processingStepSettings">
                <xsl:text>Settings: </xsl:text>
                <xsl:value-of select="alto:ocrProcessingStep[last()]/alto:processingStepSettings" />
                <xsl:value-of select="$end_of_line" />
            </xsl:if>
        </xsl:for-each>
    </xsl:template>

    <!-- Ignore everything else. -->
    <xsl:template match="text()" />

</xsl:stylesheet>


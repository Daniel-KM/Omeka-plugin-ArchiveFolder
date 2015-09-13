<?xml version="1.0" encoding="UTF-8"?>
<!--
    Description : Extract raw text from an alto file. Keep end of lines if wanted.
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

    <!-- Mode for the end of line and the hyphens: "one line", "reflow" or "original". -->
    <xsl:param name="mode_text">reflow</xsl:param>
    <!-- Character used for a new line (standard by default: \n). -->
    <xsl:param name="new_line"><xsl:text>&#x0A;</xsl:text></xsl:param>

    <!-- Main template -->
    <xsl:template match="/alto:alto">
        <xsl:apply-templates select="alto:Layout/alto:Page//alto:TextBlock" />
    </xsl:template>

    <xsl:template match="alto:TextBlock">
        <xsl:apply-templates select="alto:TextLine" />
        <xsl:if test="$mode_text = 'reflow' or $mode_text = 'original'">
            <xsl:value-of select="$new_line" />
        </xsl:if>
    </xsl:template>

    <xsl:template match="alto:TextLine">
        <xsl:apply-templates select="alto:String" />
        <xsl:if test="$mode_text = 'original'">
            <xsl:value-of select="$new_line" />
        </xsl:if>
    </xsl:template>

    <!-- Process one string. -->
    <xsl:template match="alto:String">
        <xsl:call-template name="alto_string_content" />
    </xsl:template>

    <!-- Return the content of a string, without hyphen, but with the ending
    space if any.
    This should be the same function than the one used for json. -->
    <xsl:template name="alto_string_content">
        <!-- Check if this is an hyphenated string. -->
        <xsl:choose>
            <xsl:when test="@SUBS_CONTENT">
                <xsl:choose>
                    <!-- Lines as in the text. -->
                    <xsl:when test="$mode_text = 'original'">
                        <xsl:value-of select="@CONTENT"/>
                        <xsl:choose>
                            <xsl:when test="@SUBS_TYPE = 'HypPart1'">
                                <xsl:if test="name(following::alto:*[1]) = 'HYP'">
                                    <xsl:value-of select="following::alto:*[1]/@CONTENT" />
                                </xsl:if>
                            </xsl:when>
                            <xsl:when test="@SUBS_TYPE = 'HypPart2'">
                                <xsl:if test="not(name(following::alto:*[1]) = 'String')">
                                    <xsl:text> </xsl:text>
                                </xsl:if>
                            </xsl:when>
                        </xsl:choose>
                    </xsl:when>
                    <!-- One line or reflow. -->
                    <xsl:otherwise>
                        <xsl:choose>
                            <!-- Process for hyphen part 1 is separated, because the second
                            part may be on the next page. -->
                            <xsl:when test="@SUBS_TYPE = 'HypPart1'">
                                <!-- Sub-content is used, because the hyphen may be used
                                in the string (ex: "all-inclusive"). -->
                                <xsl:value-of select="@SUBS_CONTENT"/>
                            </xsl:when>
                            <xsl:when test="@SUBS_TYPE = 'HypPart2'">
                                <xsl:if test="not(name(following::alto:*[1]) = 'String')">
                                    <xsl:text> </xsl:text>
                                </xsl:if>
                            </xsl:when>
                        </xsl:choose>
                    </xsl:otherwise>
                </xsl:choose>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="@CONTENT" />
                <xsl:if test="not(name(following::alto:*[1]) = 'String')">
                    <xsl:text> </xsl:text>
                </xsl:if>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <!-- Ignore everything else. -->
    <xsl:template match="text()" />

</xsl:stylesheet>


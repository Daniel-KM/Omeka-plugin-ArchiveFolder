<?xml version="1.0" encoding="UTF-8"?>
<!--
    Description : Extract data from an alto file.
    Version : 1.0
    Auteur : Daniel Berthereau

    IMPORTANT: You may need to change the alto namespace below.

    @todo Check when the page starts with the second part of an hyphenated string.

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

    <xsl:param name="add_cc_quality">false</xsl:param>
    <!-- Replace "<", ">" and "&" by entities. -->
    <xsl:param name="prepare_for_raw_xml">true</xsl:param>
    <!-- Value used to indicates the end of a line (removed if empty). -->
    <xsl:param name="end_of_line">EOL</xsl:param>
    <!-- This option is used for debugging purpose. -->
    <xsl:param name="feed_by_string">false</xsl:param>
    <!-- Character used for a new line (standard by default: \n). -->
    <xsl:param name="new_line"><xsl:text>&#x0A;</xsl:text></xsl:param>

    <!--
    Data in json for advanced processing (search, highlight, correction...).
    The value is an array of each string, with its position (starting from 0,
    and with count of spaces, as in the one line text), and an array for
    content, position x, y, width, height, and eventually the quality (by
    character). It the content is hyphenated, the values are in sub-arrays.
    {"String":{
        "0":["Omega",[
            ["Ome", 306,307,62,27,"010"],
            ["-",368,307,18,27,"1"],
            ["ga",130,343,45,27,"10"]
        ]],
        "4':"EOL",
        "6":["3",175,343,22,27,"1"],
        "7":["!",197,343,12,27,"1"],
        "8":"EOL"
    }}
    This array is the words "Omega 3!" cut at the end of a line "Ome- ga 3!".
    The matching one line text is "Omega 3!". The position of the word "3"
    is determined directly (6th character, starting from 0). The "EOL"
    indicates the end of line, so either the original or the reflowed text
    can be rebuilt if needed. It is optional, but may be useful.
    -->
    <xsl:template match="/alto:alto">
        <!-- Start json object. -->
        <xsl:text>{"String":{</xsl:text>
        <xsl:if test="$feed_by_string = 'true'">
            <xsl:value-of select="$new_line" />
        </xsl:if>

        <!-- Add each complete string by line. -->
        <xsl:apply-templates select="alto:Layout/alto:Page//alto:TextLine" />

        <!-- End json object. -->
        <xsl:if test="$feed_by_string = 'true'">
            <xsl:value-of select="$new_line" />
        </xsl:if>
        <xsl:text>}}</xsl:text>
    </xsl:template>

    <xsl:template match="alto:TextLine">
        <xsl:apply-templates select="alto:String[not(@SUBS_TYPE = 'HypPart2')]" />

        <xsl:if test="$end_of_line != ''">
            <xsl:text>,</xsl:text>
            <xsl:if test="$feed_by_string = 'true'">
                <xsl:value-of select="$new_line" />
            </xsl:if>
            <xsl:text>"</xsl:text>
            <xsl:variable name="position_last_string">
                <xsl:for-each select="alto:String[last()]">
                    <xsl:call-template name="position_string" />
                </xsl:for-each>
            </xsl:variable>
            <xsl:choose>
                <xsl:when test="local-name(child::alto:*[last()]) = 'HYP'">
                    <xsl:value-of select="$position_last_string
                            + string-length(alto:String[last()]/@CONTENT) + 1" />
                </xsl:when>
                <xsl:otherwise>
                    <xsl:value-of select="$position_last_string
                            + string-length(alto:String[last()]/@CONTENT)" />
                </xsl:otherwise>
            </xsl:choose>
            <xsl:text>":"</xsl:text>
            <xsl:value-of select="$end_of_line" />
            <xsl:text>"</xsl:text>
        </xsl:if>

        <xsl:if test="position() != last()">
            <xsl:text>,</xsl:text>
            <xsl:if test="$feed_by_string = 'true'">
                <xsl:value-of select="$new_line" />
            </xsl:if>
        </xsl:if>
    </xsl:template>

    <!-- Process one string. -->
    <xsl:template match="alto:String">
        <xsl:text>"</xsl:text>
        <xsl:call-template name="position_string" />
        <xsl:text>":</xsl:text>
        <xsl:call-template name="xml_string_to_json" />
        <xsl:if test="position() != last()">
            <xsl:text>,</xsl:text>
            <xsl:if test="$feed_by_string = 'true'">
                <xsl:value-of select="$new_line" />
            </xsl:if>
        </xsl:if>
    </xsl:template>

    <!-- Get the position of the current string, without hyphen and 0-based. -->
    <xsl:template name="position_string">
        <xsl:variable name="concat">
            <xsl:for-each select="preceding::alto:String">
                <xsl:call-template name="alto_string_content" />
            </xsl:for-each>
        </xsl:variable>
        <xsl:value-of select="string-length($concat)" />
    </xsl:template>

    <!-- Return the content of a string, without hyphen, but with the ending
    space if any.
    This should be the same function that extracts the one-line text. -->
    <xsl:template name="alto_string_content">
        <!-- Check if this is an hyphenated string. -->
        <xsl:choose>
            <xsl:when test="@SUBS_CONTENT">
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
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="@CONTENT" />
                <xsl:if test="not(name(following::alto:*[1]) = 'String')">
                    <xsl:text> </xsl:text>
                </xsl:if>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <!-- Prepare data of a string. -->
    <xsl:template name="xml_string_to_json">
        <xsl:choose>
            <!-- Check if the string has an hyphen. -->
            <xsl:when test="@SUBS_CONTENT">
                <xsl:if test="@SUBS_TYPE = 'HypPart1'">
                    <xsl:text>["</xsl:text>
                    <xsl:value-of select="@SUBS_CONTENT" />
                    <xsl:text>",[</xsl:text>
                    <xsl:if test="$feed_by_string = 'true'">
                        <xsl:value-of select="$new_line" />
                    </xsl:if>
                    <xsl:call-template name="string_data" />
                    <xsl:text>,</xsl:text>
                    <xsl:if test="$feed_by_string = 'true'">
                        <xsl:value-of select="$new_line" />
                    </xsl:if>
                    <xsl:call-template name="string_data">
                        <xsl:with-param name="string" select="following::alto:*[1]" />
                    </xsl:call-template>
                    <!-- Check because the HypPart2 can be on the next page. -->
                    <xsl:if test="following::alto:String[1]
                            and following::alto:String[1]/@SUBS_TYPE = 'HypPart2'">
                        <xsl:text>,</xsl:text>
                        <xsl:if test="$feed_by_string = 'true'">
                            <xsl:value-of select="$new_line" />
                        </xsl:if>
                        <xsl:call-template name="string_data">
                            <xsl:with-param name="string" select="following::alto:String[1]" />
                        </xsl:call-template>
                        <xsl:if test="$feed_by_string = 'true'">
                            <xsl:value-of select="$new_line" />
                        </xsl:if>
                    </xsl:if>
                    <xsl:text>]]</xsl:text>
                </xsl:if>
            </xsl:when>
            <!-- Normal string. -->
            <xsl:otherwise>
                <xsl:call-template name="string_data" />
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <!-- Copy data of a string or a hyphen. -->
    <xsl:template name="string_data">
        <xsl:param name="string" select="." />

        <xsl:text>["</xsl:text>
        <xsl:call-template name="escape_string_for_json_or_xml">
            <xsl:with-param name="string" select="$string/@CONTENT"/>
        </xsl:call-template>
        <xsl:text>",</xsl:text>
        <xsl:value-of select="$string/@HPOS" />
        <xsl:text>,</xsl:text>
        <xsl:value-of select="$string/@VPOS" />
        <xsl:text>,</xsl:text>
        <xsl:value-of select="$string/@WIDTH" />
        <xsl:text>,</xsl:text>
        <!-- Height is not used for hyphen. -->
        <xsl:choose>
            <xsl:when test="$string/@HEIGHT">
                <xsl:value-of select="$string/@HEIGHT" />
            </xsl:when>
            <xsl:otherwise>
                <xsl:text>1</xsl:text>
            </xsl:otherwise>
        </xsl:choose>
        <xsl:if test="$add_cc_quality = 'true' and $string/@CC != ''">
            <xsl:text>,"</xsl:text>
            <xsl:value-of select="$string/@CC" />
            <xsl:text>"</xsl:text>
        </xsl:if>
        <xsl:text>]</xsl:text>
    </xsl:template>

    <!-- Escape "\" and """ for json and eventually "<", ">" and "&" for xml. -->
    <xsl:template name="escape_string_for_json_or_xml">
        <xsl:param name="string"/>

        <xsl:variable name="replace_xml">
            <xsl:choose>
                <xsl:when test="$prepare_for_raw_xml = 'true'">
                    <xsl:call-template name="escape_string_for_xml">
                        <xsl:with-param name="string" select="$string" />
                    </xsl:call-template>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:value-of select="$string" />
                </xsl:otherwise>
            </xsl:choose>
        </xsl:variable>

        <xsl:call-template name="escape_string_for_json">
            <xsl:with-param name="string" select="$replace_xml"/>
        </xsl:call-template>
    </xsl:template>

    <!-- Escape "<", ">" and "&" for xml. -->
    <xsl:template name="escape_string_for_xml">
        <xsl:param name="string"/>

        <xsl:variable name="replace_1">
            <xsl:call-template name="str_replace">
                <xsl:with-param name="string" select="$string"/>
                <xsl:with-param name="search">&amp;</xsl:with-param>
                <xsl:with-param name="replace">&amp;amp;</xsl:with-param>
            </xsl:call-template>
        </xsl:variable>
        <xsl:variable name="replace_2">
            <xsl:call-template name="str_replace">
                <xsl:with-param name="string" select="$replace_1"/>
                <xsl:with-param name="search">&lt;</xsl:with-param>
                <xsl:with-param name="replace">&amp;lt;</xsl:with-param>
            </xsl:call-template>
        </xsl:variable>
        <xsl:call-template name="str_replace">
            <xsl:with-param name="string" select="$replace_2"/>
            <xsl:with-param name="search">&gt;</xsl:with-param>
            <xsl:with-param name="replace">&amp;gt;</xsl:with-param>
        </xsl:call-template>
    </xsl:template>

    <!-- Escape "\" and """ for json (""" should be already an entity in alto). -->
    <xsl:template name="escape_string_for_json">
        <xsl:param name="string"/>

        <xsl:variable name="replace_1">
            <xsl:call-template name="str_replace">
                <xsl:with-param name="string" select="$string"/>
                <xsl:with-param name="search">\</xsl:with-param>
                <!-- "\" can be replaced by \\, &amp;#92; or &amp;amp;#92;. -->
                <xsl:with-param name="replace">\\</xsl:with-param>
            </xsl:call-template>
        </xsl:variable>
       <xsl:call-template name="str_replace">
            <xsl:with-param name="string" select="$replace_1"/>
            <xsl:with-param name="search">"</xsl:with-param>
            <!-- """ can be replaced by \", &amp;quot; or &amp;amp;quot;. -->
            <xsl:with-param name="replace">&amp;quot;</xsl:with-param>
        </xsl:call-template>
    </xsl:template>

    <!-- Replace all occurences of a string in a string. -->
    <xsl:template name="str_replace">
        <xsl:param name="string" />
        <xsl:param name="search" />
        <xsl:param name="replace" />

        <xsl:choose>
            <xsl:when test="contains($string, $search)">
                <xsl:value-of select="substring-before($string, $search)"/>
                <xsl:value-of select="$replace"/>
                <xsl:call-template name="str_replace">
                    <xsl:with-param name="string" select="substring-after($string, $search)"/>
                    <xsl:with-param name="search" select="$search" />
                    <xsl:with-param name="replace" select="$replace" />
                </xsl:call-template>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$string"/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <!-- Ignore everything else. -->
    <xsl:template match="text()" />

</xsl:stylesheet>


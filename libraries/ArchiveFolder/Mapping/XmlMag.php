<?php
/**
 * Map Mag xml files into Omeka elements for each item and file via xsl.
 *
 * @package Omeka\Plugins\ArchiveFolder
 */
class ArchiveFolder_Mapping_XmlMag extends ArchiveFolder_Mapping_Abstract
{
    const XML_ROOT = 'metadigit';
    const XML_PREFIX = 'mag';
    const XML_NAMESPACE = 'http://www.iccu.sbn.it/metaAG1.pdf';

    protected $_checkMetadataFile = array('extension', 'validate xml');
    protected $_extension = 'xml';
    protected $_formatXml = self::XML_PREFIX;
    protected $_xmlRoot = self::XML_ROOT;
    protected $_xmlNamespace = self::XML_NAMESPACE;
    protected $_xmlPrefix = self::XML_PREFIX;

    protected $_xslMain = 'libraries/xsl/mag2document.xslt1.xsl';

    public function __construct($uri, $parameters)
    {
        $this->_xslMain = PLUGIN_DIR
            . DIRECTORY_SEPARATOR . 'ArchiveFolder'
            . DIRECTORY_SEPARATOR . $this->_xslMain;

        parent::__construct($uri, $parameters);
    }

    /**
     * Prepare the list of documents set inside the current metadata file.
     */
    protected function _prepareDocuments()
    {
        $this->_prepareXmlDocuments();
    }
}

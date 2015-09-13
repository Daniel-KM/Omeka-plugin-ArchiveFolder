<?php

class ArchiveFolder_Form_Add extends Omeka_Form
{
    public function init()
    {
        parent::init();

        $oaiIdentifiers = $this->_getFiltered('archive_folder_oai_identifiers');
        $mappings = $this->_getFiltered('archive_folder_mappings');
        $formats = $this->_getFiltered('archive_folder_formats', 'prefix');
        $formatsHarvests = $this->_getFilteredMetadataFormats('archive_folder_formats');

        $optionsUpdateMetadata = array(
            'keep' => __('Keep existing'),
            'element' => __('By element'),
            'strict' => __('Strict copy'),
        );
        $defaultUpdateMetadata = 'element';

        $optionsUpdateFiles = array(
            'keep' => __('Keep existing'),
            'deduplicate' => __('Deduplicate'),
            'remove' => __('Remove deleted'),
            'full' => __('Full update'),
        );
        $defaultUpdateFiles = 'full';

        $allowLocalPaths = Zend_Registry::get('archive_folder')->local_folders->allow === '1';

        $this->setAttrib('id', 'archive-folder');
        $this->setMethod('post');

        $this->addElement('text', 'uri', array(
            'label' => __('Base URI'),
            'description' => __('The base url or path of the folder to expose.')
                . ' ' . __('If url, the server should allow directory listing.')
                . ($allowLocalPaths ? '' : ' ' . __('Local paths are forbidden by the administrator.')),
            'required' => true,
            'filters' => array(
                'StringTrim',
                'StripTags',
            ),
            'validators' => array(
                'NotEmpty',
                array(
                    'Callback',
                    true,
                    array(
                        'callback' => function($value) {
                            return Zend_Uri::check($value);
                        }
                    ),
                    'messages' => array(
                        Zend_Validate_Callback::INVALID_VALUE => __('An url or a path is required to add a folder.'),
                    ),
                ),
            ),
        ));

        $this->addElement('radio', 'unreferenced_files', array(
            'label' => __('Unreferenced Files'),
            'description'   => __('This option indicates what to do with files, maybe all of them, that are not referenced inside metadata files ("%s").', implode('", "', array_keys($mappings))),
            'value' => 'by_file',
            'multiOptions' => array(
                'by_file' => __('One item by file'),
                'by_directory' => __('One item by directory'),
                'skip' => __("Skip"),
        )));

        $this->addElement('checkbox', 'records_for_files', array(
            'label' => __('Files Metadata'),
            'description' => __('Create metadata for files, not only for items.')
                . ' ' . __('Metadata for files may be useless if there is only one file by item.')
                . ' ' . __('If not set, the Dublin Core term "Identifier" will be used to link the item and its files.')
                . ' ' . __('If set, the Dublin Core term "Relation" or the Qualified Dublin Core term "Requires" will be used.')
                . ' ' . __('This parameter can be bypassed when a metadata file is directly included in the static repository.'),
            'value' => true,
        ));

        $this->addElement('text', 'exclude_extensions', array(
            'label' => __('File extensions to exclude'),
            'description' => __('A black-list of extensions to exclude from the source, separated by a space or a comma and without the initial dot.')
                . ' ' . __('The white-list is the Omeka one, defined in the security page.'),
        ));

        $this->addElement('text', 'element_delimiter', array(
            'label' => __('Spreadsheet element separator'),
            'description' => __('If metadata are available in a table (Open Document Spreadsheet ods), multiple elements can be set within one cell for the same field.')
                . ' ' . __('This character or this string, for example the pipe "|", can be used to delimite them.')
                . ' ' . __('If the delimiter is empty, then the whole text will be used.')
                . ' ' . __('Anyway, multiple columns can be mapped to the same element and multiple rows can manage multiple values for the same field of the same record.'),
            'value' => '|',
        ));

        if (plugin_is_active('OcrElementSet')) {
            $this->addElement('checkbox', 'fill_ocr_text', array(
                'label' => __('Fill OCR text'),
                'description' => __('If Alto xml files are imported via Mets, fill the field "OCR : text" too.'),
                'value' => true,
            ));
            $this->addElement('checkbox', 'fill_ocr_data', array(
                'label' => __('Fill OCR Data'),
                'description' => __('If Alto xml files are imported via Mets, fill the field "OCR : data" too.')
                    . ' ' . __('This field is needed only if it is reused somewhere else (highlight, correction, search...).')
                    . ' ' . __('Warning: Data can be heavy and they are duplicated by default in the search table of the base.'),
                'value' => true,
            ));
            $this->addElement('checkbox', 'fill_ocr_process', array(
                'label' => __('Fill processing data for OCR'),
                'description' => __('If Alto xml files are imported via Mets, fill the field "OCR : Process" too.'),
                'value' => false,
            ));
        }
        else {
            $this->addElement('hidden', 'fill_ocr_text', array(
                'value' => false,
            ));
            $this->addElement('hidden', 'fill_ocr_data', array(
                'value' => false,
            ));
            $this->addElement('hidden', 'fill_ocr_process', array(
                'value' => false,
            ));
        }

        // Only the "short_name" format of identifier allows to do update,
        // because it's stable and doesn't depend on position in the list of
        // files, it manages files and files defined by metadata files, and it's
        // short, as recommended.
        $this->addElement('hidden', 'oai_identifier_format', array(
            'value' => 'short_name',
        ));

        /*
        $this->addElement('select', 'oai_identifier_format', array(
            'label' => __('Record Identifier'),
            'description' => __('The local identifier of each record should be unique and stable.')
                . ' ' . __('This is used only for internal purposes.')
                . ' ' . __('See readme for more info.'),
            'multiOptions' => label_table_options($oaiIdentifiers),
            'value' => isset($oaiIdentifiers['position_folder']) ? 'position_folder' : null,
        ));
        */

        $values = array(
            '' => __('No default item type'),
            'default' => __('Default type according to first file of each item'),
        ) + get_table_options('ItemType');
        $this->addElement('select', 'item_type_id', array(
            'label' => __('Default Item Type'),
            'description' => __('The default type is defined according to the type '
                . 'of the first file and the number of files.')
                . ' ' . __('("Still image" for an item with a single Image, "Text" for an item with multiple '
                . 'image or a pdf, "Sound" for an audio file, "Moving Image" for a video file and none in all other cases.')
                . ' ' . __('In fact, it only adds the value in the field "Dublin Core : Type", and the harvester may or may not add it as a special value.')
                . ' ' . __('This field can be bypassed by special formats.'),
            'multiOptions' => $values,
            'value' => '',
        ));

        $this->addElement('text', 'repository_name', array(
            'label' => __('Name of the repository'),
            'description' => __('The name is the title of this repository.')
                . ' ' . __('It should be unique.')
                . ' ' . __('If not set, the name will be the uri.'),
        ));

        $this->addElement('text', 'admin_emails', array(
            'label' => __('Administrator(s) Email(s)'),
            // 'validators' => array('EmailAddress'),
            'description' => __('The email(s) of the administrator(s) of this folder will be displayed in repository.'),
        ));

        $this->addElement('multiCheckbox', 'metadata_formats', array(
            'label' => __('Metadata Formats'),
            'description' => __('Metadata formats exposed by the static repository.'),
            'multiOptions' => $formats,
            // Only the required format and the internal document are set true by default.
            'value' => array('oai_dc', 'doc'),
        ));

        $this->addElement('checkbox', 'use_qdc', array(
            'label' => __('Use Qualified Dublin Core if possible'),
            'description' => __('For formats like "Mets", if files metadata are filled, the process can use "Relation" or "Requires" / "Is Required By" to identify the relation between each item and associated files.'),
            'value' => true,
        ));

        $this->addElement('checkbox', 'repository_remote', array(
            'label' => __('Remote Repository'),
            'description' => __('If set, keep original url for the static repository and set Omeka as a simple gateway.')
                . ' ' . __('If not set, or if files are local, or not available via http/https, Omeka will be the static repository.')
                . ' ' . __('This option is important, because it is used to set the base url of the repository and the identifier of each record.')
                . ' ' . __('Local files will be stored in a subfolder of files/repositories.'),
            'value' => false,
        ));

        $this->addElement('text', 'repository_domain', array(
            'label' => __('Domain'),
        ));

        $this->addElement('text', 'repository_path', array(
            'label' => __('Repository Path'),
        ));

        $this->addElement('text', 'repository_identifier', array(
            'label' => __('Repository Identifier'),
            'description' => __('This identifier should be unique and contain only alphanumeric characters, "-" and "_", without space and without extension.'),
        ));

        $this->addElement('checkbox', 'oaipmh_gateway', array(
            'label' => __('Add to OAI-PMH gateway'),
            'description' => __('Make this repository available internally by the OAI-PMH gateway.')
                . (plugin_is_active('OaiPmhGateway') ? '' : ' '
                    . __('This option will be used only if the plugin OAI-PMH Gateway is enabled.')),
            'value' => true,
        ));

        $canHarvest = plugin_is_active('OaiPmhGateway') && plugin_is_active('OaipmhHarvester');
        $descriptionCanHarvest = __('This option will be used only if the plugins OAI-PMH Gateway and OAI-PMH Harvester are enabled.');
        $this->addElement('checkbox', 'oaipmh_harvest', array(
            'label' => __('Harvest via OAI-PMH'),
            'description' => __('Harvest this repository with the OAI-PMH Harvester, via the OAI-PMH Gateway.')
                . ($canHarvest ? '' : ' ' . $descriptionCanHarvest),
            'value' => true,
        ));

        $this->addElement('select', 'oaipmh_harvest_prefix', array(
            'label' => __('Format used to harvest'),
            'description' => __('The "oai_dc" can import only metadata of items.')
                . ' ' . __('Choose an advanced one as "Mets" to harvest files too.')
                . ' ' . __('The default "Documents" format allows to import and to update all standard data, properties and extra data.')
                . ' ' . __('The selected format should be set as a format used by the static repository.')
                . ($canHarvest ? '' : ' ' . $descriptionCanHarvest),
            'multiOptions' => $formatsHarvests,
            'value' => isset($formatsHarvests['doc']) ? 'doc' : 'oai_dc',
        ));

        $this->addElement('select', 'oaipmh_harvest_update_metadata', array(
            'label' => __('Re-harvesting metadata'),
            'description' => __('When metadata are updated, old ones may be kept or removed.')
                . ($canHarvest ? '' : ' ' . $descriptionCanHarvest),
            'multiOptions' => $optionsUpdateMetadata,
            'value' => $defaultUpdateMetadata,
        ));

        $this->addElement('select', 'oaipmh_harvest_update_files', array(
            'label' => __('Re-harvesting files'),
            'description' => __('When files are updated, duplicates and old ones may be kept or removed.')
                . ($canHarvest ? '' : ' ' . $descriptionCanHarvest),
            'multiOptions' => $optionsUpdateFiles,
            'value' => $defaultUpdateFiles,
        ));

        // Parameters for the folder of original files.
        $this->addDisplayGroup(
            array(
                'uri',
            ),
            'archive_folder_folder',
            array(
                'legend' => __('Archive Folder URI'),
        ));

        // Parameters to create each record.
        $this->addDisplayGroup(
            array(
                'unreferenced_files',
                'records_for_files',
                'exclude_extensions',
                'element_delimiter',
                'fill_ocr_text',
                'fill_ocr_data',
                'fill_ocr_process',
                'oai_identifier_format',
                'item_type_id',
            ),
            'archive_folder_records',
            array(
                'legend' => __('Archive Folder Records and files'),
                'description' => __('Set parameters fo create each record from files.')
                    . ' ' . __('Note:')
                    . ' ' . __('An item can have multiple files, and items and files can have different metadata.')
                    . ' ' . __('For example, a record of a book can have each digitalized page attached to it.')
                    . ' ' . __('An object can have multiple pictures under different views or taken by different photographers.')
                    . ' ' . __('In that case, it is recommended to separate the metadata, for example to add data about each page or the different authors of the view.')
                    . ' ' . __("Conversely, an image of a paint, a photography, or a book digitalized as a pdf and e-book files doesn't need to have separate records."),
        ));

        // Parameters to create the static repository.
        $this->addDisplayGroup(
            array(
                'repository_name',
                'admin_emails',
                'metadata_formats',
                'use_qdc'
            ),
            'archive_folder_repository',
            array(
                'legend' => __('Static Repository'),
                'description' => __('Set the generic parameters of the static repository.'),
        ));

        // Parameters to create the url of the static repository.
        $this->addDisplayGroup(
            array(
                'repository_remote',
                'repository_domain',
                'repository_path',
                'repository_identifier',
            ),
            'archive_folder_base_url',
            array(
                'legend' => __('Static Repository Url'),
                'description' => __('These advanced options allow to change the url of the static repository, if wished.')
                    . ' ' . __('This url is: "my_domain.com/my_path/to/my_folder_identifier.xml".')
                    . ' ' . __('If not set, parameters will be determined from the uri.'),
        ));

        // Parameters to harvest.
        $this->addDisplayGroup(
            array(
                'oaipmh_gateway',
                'oaipmh_harvest',
                'oaipmh_harvest_prefix',
                'oaipmh_harvest_update_metadata',
                'oaipmh_harvest_update_files',
            ),
            'archive_folder_harvest',
            array(
                'legend' => __('Static Repository Harvesting'),
                'description' => __('Options for OAI-PMH harvesting (used after the first update).'),
        ));

        $this->applyOmekaStyles();
        $this->setAutoApplyOmekaStyles(false);

        $this->addElement('sessionCsrfToken', 'csrf_token');

        $this->addElement('submit', 'submit', array(
            'label' => __('Add folder'),
            'class' => 'submit submit-medium',
            'decorators' => (array(
                'ViewHelper',
                array('HtmlTag', array('tag' => 'div', 'class' => 'field')))),
        ));
    }

    /**
     * Return the list of available filtered values with a description.
     *
     * @param string $filter Name of the filter to use.
     * @param string $withSecondValue Add a second value as description.
     * @return array Associative array of oai identifiers.
     */
    protected function _getFiltered($filter, $withSecondValue = '')
    {
        $values = apply_filters($filter, array());

        foreach ($values as $name => &$value) {
            if (class_exists($value['class'])) {
                $value = $withSecondValue
                    ? sprintf('%s [%s]', $value['description'], $value[$withSecondValue])
                    : $value['description'];
            }
            else {
                unset($values[$name]);
            }
        }

        return $values;
    }

    /**
     * Return the list of available filtered values with a description.
     *
     * @param string $filter Name of the filter to use.
     * @param string $withSecondValue Add a second value as description.
     * @return array Associative array of oai identifiers.
     */
    protected function _getFilteredMetadataFormats($filter)
    {
        $values = apply_filters($filter, array());

        $result = array();
        foreach ($values as $name => $value) {
            if (class_exists($value['class']) && !isset($result[$value['prefix']])) {
                $result[$value['prefix']] =  sprintf('%s [%s]', $value['description'], $value['prefix']);
            }
        }

        return $result;
    }
}

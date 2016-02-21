<?php

class ArchiveFolder_Form_Add extends Omeka_Form
{
    public function init()
    {
        parent::init();

        $mappings = $this->_getFiltered('archive_folder_mappings');

        $allowLocalPaths = Zend_Registry::get('archive_folder')->local_folders->allow == '1';

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
                        'callback' => array('ArchiveFolder_Form_Validator', 'validateUri'),
                    ),
                    'messages' => array(
                        Zend_Validate_Callback::INVALID_VALUE => __('A url or a path is required to add a folder.'),
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

        /*
        $this->addElement('checkbox', 'records_for_files', array(
            'label' => __('Files Metadata'),
            'description' => __('Create metadata for files, not only for items.')
                . ' ' . __('Metadata for files may be useless if there is only one file by item.')
                . ' ' . __('This parameter may be bypassed when a metadata file is directly included in the folder.'),
            'value' => true,
        ));
        */
        $this->addElement('hidden', 'records_for_files', array(
            'value' => true,
        ));

        $this->addElement('text', 'exclude_extensions', array(
            'label' => __('File extensions to exclude'),
            'description' => __('A black-list of extensions (normal or double) to exclude from the source, separated by a space or a comma and without the initial dot.')
                . ' ' . __('The white-list is the Omeka one, defined in the security page.'),
        ));

        $values = array(
            '' => __('No default item type'),
            'default' => __('Default type according to first file of each item'),
        ) + get_table_options('ItemType');
        $this->addElement('select', 'item_type_id', array(
            'label' => __('Default Item Type'),
            'description' => __('Set  the item type during import as Omeka Item Type and Dublin Core Type.')
                . ' ' . __('For the second option (type of the first file), it can be:')
                . ' ' . __('"Still image" for an item with a single Image, "Text" for an item with multiple image or a pdf, '
                . '"Sound" for an audio file, "Moving Image" for a video file and none in all other cases.'),
            'multiOptions' => $values,
            'value' => '',
        ));

        $this->addElement('checkbox', 'add_relations', array(
            'label' => __('Add unique identifiers'),
            'description' => __('To add unique identifiers allows to link items and files easily and independantly from Omeka.')
                . ' ' . __('Added identifiers are absolute urls.')
                . ' ' . __("This option is only usefull when there aren't such identifiers."),
            'value' => false,
        ));

        $this->addElement('text', 'element_delimiter', array(
            'label' => __('Table/Spreadsheet element separator'),
            'description' => __('If metadata are available in a table (as Open Document Spreadsheet ods), multiple elements can be set within one cell for the same field.')
                . ' ' . __('This character or this string, for example the pipe "|", can be used to delimite them.')
                . ' ' . __('If the delimiter is empty, then the whole text will be used.')
                . ' ' . __('Anyway, multiple columns can be mapped to the same element and multiple rows can manage multiple values for the same field of the same record.'),
            'value' => ArchiveFolder_Mapping_Table::DEFAULT_ELEMENT_DELIMITER,
        ));

        $this->addElement('text', 'empty_value', array(
            'label' => __('Table/Spreadsheet empty value'),
            'description' => __('If metadata are available in a table (as Open Document Spreadsheet ods), an empty cell can be an empty value or no value.')
                . ' ' . __('To distinct these two cases, an empty value can be replaced by this string (case sensitive).'),
            'value' => ArchiveFolder_Mapping_Table::DEFAULT_EMPTY_VALUE,
        ));

        $this->addElement('textarea', 'extra_parameters', array(
            'label' => __('Add specific parameters'),
            'description' => __('Some formats require specific parameters, for example to be used in the xsl sheets.')
                . ' ' . __('You can specify them here, one by line.'),
            'value' => '',
            'required' => false,
            'rows' => 5,
            'placeholder' => __('parameter_1_name = parameter 1 value'),
            'filters' => array(
                'StringTrim',
            ),
            'validators' => array(
                array(
                    'callback',
                    false,
                    array(
                        'callback' => array('ArchiveFolder_Form_Validator', 'validateExtraParameters'),
                    ),
                    'messages' => array(
                        Zend_Validate_Callback::INVALID_VALUE => __('Each extra parameter, one by line, should have a name separated from the value with a "=".'),
                    ),
                ),
            ),
        ));

        $identifierField = get_option('archive_folder_identifier_field');
        if (!empty($identifierField) && $identifierField != ArchiveFolder_Importer::IDFIELD_INTERNAL_ID) {
            $currentIdentifierField = $this->_getElementFromIdentifierField($identifierField);
            if ($currentIdentifierField) {
                $identifierField = $currentIdentifierField->id;
            }
        }
        $values = get_table_options('Element', null, array(
            'record_types' => array('All'),
            'sort' => 'alphaBySet',
        ));
        unset($values['']);
        $values = array(
            ArchiveFolder_Importer::IDFIELD_NONE => __('No default identifier field'),
            ArchiveFolder_Importer::IDFIELD_INTERNAL_ID => __('Internal id'),
            // 'filename' => __('Imported filename (to import files only)'),
            // 'original filename' => __('Original filename (to import files only)'),
        ) + $values;
        $this->addElement('select', 'identifier_field', array(
            'label' => __('Identifier field (required)'),
            'description' => __('The identifier field is used to simplify the update of records.')
                . ' ' . __('This is the link between the files in the folder and the records in the Omeka base.')
                . ' ' . __('The identifier should be set and unique in all records (collections, items, files).')
                . ' ' . __('This is generally the Dublin Core : Identifier or a specific element.'),
            'multiOptions' => $values,
            'value' => $identifierField,
        ));

        $this->addElement('select', 'action', array(
            'label' => __('Action'),
            'multiOptions' => label_table_options(array(
                ArchiveFolder_Importer::ACTION_UPDATE_ELSE_CREATE
                    => __('Update the record if it exists, else create one'),
                ArchiveFolder_Importer::ACTION_CREATE
                    => __('Create a new record'),
                ArchiveFolder_Importer::ACTION_UPDATE
                    => __('Update values of specific fields'),
                ArchiveFolder_Importer::ACTION_ADD
                    => __('Add values to specific fields'),
                ArchiveFolder_Importer::ACTION_REPLACE
                    => __('Replace values of all fields'),
                ArchiveFolder_Importer::ACTION_DELETE
                    => __('Delete the record'),
                ArchiveFolder_Importer::ACTION_SKIP
                    => __('Skip process of the record'),
            ), __('No default action')),
            'description' => __('The action defines how records and metadara are processed.')
                . ' ' . __('The record can be created, updated, deleted or skipped.')
                . ' ' . __('The metadata of an existing record can be updated, appended or replaced.'),
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
        $this->addDisplayGroup(
            array(
                'unreferenced_files',
                'records_for_files',
                'exclude_extensions',
                'item_type_id',
            ),
            'archive_folder_records',
            array(
                'legend' => __('Archive Folder Records and files'),
                'description' => __('Set parameters to create each record from files.')
                    . ' ' . __('Note:')
                    . ' ' . __('An item can have multiple files, and items and files can have different metadata.')
                    . ' ' . __('For example, a record of a book can have each digitalized page attached to it.')
                    . ' ' . __('An object can have multiple pictures under different views or taken by different photographers.')
                    . ' ' . __('In that case, it is recommended to separate the metadata, for example to add data about each page or the different authors of the view.')
                    . ' ' . __("Conversely, an image of a paint, a photography, or a book digitalized as a pdf and e-book files doesn't need to have separate records."),
        ));

        // Parameters to create each record.
        $this->addDisplayGroup(
            array(
                'element_delimiter',
                'empty_value',
            ),
            'archive_folder_table',
            array(
                'legend' => __('Tables'),
                'description' => __('Set specific parameters for table or spreadsheets.'),
        ));

        apply_filters('archive_folder_add_parameters', $this);

        $this->addDisplayGroup(
            array(
                'extra_parameters',
            ),
            'archive_folder_extra_parameters',
            array(
                'legend' => __('Extra Parameters'),
        ));

        // Parameters for the folder of original files.
        $this->addDisplayGroup(
            array(
                'identifier_field',
                'action',
            ),
            'archive_folder_process',
            array(
                'legend' => __('Process'),
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
     * Return the element from an identifier.
     *
     * @return Element|boolean
     */
    private function _getElementFromIdentifierField($identifierField)
    {
        if (strlen($identifierField) > 0) {
            $parts = explode(
                    CsvImport_ColumnMap_MixElement::DEFAULT_COLUMN_NAME_DELIMITER,
                    $identifierField);
            if (count($parts) == 2) {
                $elementSetName = trim($parts[0]);
                $elementName = trim($parts[1]);
                $element = get_db()->getTable('Element')
                    ->findByElementSetNameAndElementName($elementSetName, $elementName);
                if ($element) {
                    return $element;
                }
            }
        }
    }
}

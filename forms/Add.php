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
}

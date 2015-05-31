<?php
define('ARCHIVE_FOLDER_DIR', dirname(dirname(__FILE__)));
define('TEST_FILES_DIR', ARCHIVE_FOLDER_DIR
    . DIRECTORY_SEPARATOR . 'tests'
    . DIRECTORY_SEPARATOR . 'suite'
    . DIRECTORY_SEPARATOR . '_files');
require_once dirname(dirname(ARCHIVE_FOLDER_DIR)) . '/application/tests/bootstrap.php';
require_once 'ArchiveFolder_Test_AppTestCase.php';

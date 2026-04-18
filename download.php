<?php

require_once(__DIR__ . '/../../../config.php');

require_login();
require_capability('tool/stdlogarchiver:view', context_system::instance());

$id = required_param('id', PARAM_INT);
require_sesskey();

$backup = \tool_stdlogarchiver\models\backup::get_record(['id' => $id]);

if (!$backup || $backup->is_deleted()) {
    throw new moodle_exception('invalidbackup', 'tool_stdlogarchiver');
}

// Try local file first, then cache (which may trigger download from external storage).
if ($backup->local_file_exists()) {
    $filepath = $backup->get_local_file_path();
} else {
    try {
        $filepath = $backup->get_path_for_query();
    } catch (\moodle_exception $e) {
        throw new moodle_exception('backupfilenotfound', 'tool_stdlogarchiver');
    }
}

if (!file_exists($filepath)) {
    throw new moodle_exception('backupfilenotfound', 'tool_stdlogarchiver');
}

$filename = $backup->get_filename();
$filesize = filesize($filepath);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: private, max-age=86400');
header('Pragma: private');

readfile($filepath);
exit;

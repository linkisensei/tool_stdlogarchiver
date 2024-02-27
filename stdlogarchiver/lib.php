<?php

function tool_stdlogarchiver_notify_logstore_standard_archive_task_active(){
    $loglifetime = (int)get_config('logstore_standard', 'loglifetime');
    if($loglifetime > 0){
        $data = (object) [
            'configname' => get_string('loglifetime', 'core_admin'),
            'configvalue' => get_string('numdays', '', $loglifetime),            
            'url' => (new moodle_url('/admin/settings.php', ['section' => 'logsettingstandard']))->out(),
        ];
        $message = get_string('warning:logstore_standard_archive_task_active', 'tool_stdlogarchiver', $data);
        \core\notification::warning($message);
    }
}


function tool_stdlogarchiver_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()){
    // Check the contextlevel is as expected
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false; 
    }

    // Make sure the filearea is one of those used by the plugin.
    $plugin_fileareas = [
        \tool_stdlogarchiver\config::BACKUPS_FILEAREA,
    ];
    if (!in_array($filearea, $plugin_fileareas)) {
        return false;
    }

    // Make sure the user is logged
    require_login();

    // Check the relevant capabilities
    if (!has_capability('tool/stdlogarchiver:view', $context)) {
        return false;
    }

    // Leave this line out if you set the itemid to null in make_pluginfile_url (set $itemid to 0 instead).
    $itemid = array_shift($args); // The first item in the $args array.

    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        $filepath = '/'; // $args is empty => the path is '/'
    } else {
        $filepath = '/'.implode('/', $args).'/'; // $args contains elements of the filepath
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'tool_stdlogarchiver', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // We can now send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering. 
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}


// function tool_stdlogarchiver_create_extended_moodle_database() : moodle_database {
//     global $DB;

//     class_alias($DB::class, 'tool_stdlogarchiver\dml\current_moodle_database');

//     class tool_stdlogarchiver_moodle_database extends tool_stdlogarchiver\dml\current_moodle_database{
//         // public function import_record(){
//         //     ... ITS POSSIBLE, BUT NOT RECOMENDED
//         // }
//     }

//     return new tool_stdlogarchiver_moodle_database();
// }

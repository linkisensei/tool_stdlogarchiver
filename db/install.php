<?php

defined('MOODLE_INTERNAL') || die;

function xmldb_tool_stdlogarchiver_install(): bool {
    global $CFG;

    \tool_stdlogarchiver\config::set(\tool_stdlogarchiver\config::CONFIG_MAX_RECORDS_PER_FILE, 200000);
    \tool_stdlogarchiver\config::set(\tool_stdlogarchiver\config::CONFIG_BACKUP_FORMAT, \tool_stdlogarchiver\config::BACKUP_FORMAT_DB);
    \tool_stdlogarchiver\config::set(\tool_stdlogarchiver\config::CONFIG_LOG_LIFETIME, 26 * WEEKSECS);
    \tool_stdlogarchiver\config::set(\tool_stdlogarchiver\config::CONFIG_CACHE_TTL, 86400);

    $backup_dir = $CFG->dataroot . '/tool_stdlogarchiver';
    if (!is_dir($backup_dir)) {
        make_writable_directory($backup_dir);
        file_put_contents($backup_dir . '/.htaccess', "deny from all\n");
    }

    return true;
}

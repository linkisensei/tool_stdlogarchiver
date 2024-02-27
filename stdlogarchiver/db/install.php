<?php

defined('MOODLE_INTERNAL') || die;

function xmldb_tool_stdlogarchiver_install(){
    $config = new tool_stdlogarchiver\config();
    $config::set($config::CONFIG_RECORDS_PER_FILE, 10000);
    $config::set($config::CONFIG_BACKUP_FORMAT, $config::BACKUP_FORMAT_CSV);
    $config::set($config::CONFIG_LOG_LIFETIME, 26 * WEEKSECS);

    return true;
}
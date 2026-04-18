<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'tool_stdlogarchiver\task\archive_task',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '1',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => false,
    ],
    [
        'classname' => 'tool_stdlogarchiver\task\external_migration_task',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '3',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => false,
    ],
    [
        'classname' => 'tool_stdlogarchiver\task\cache_cleanup_task',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '4',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => false,
    ],
    [
        'classname' => 'tool_stdlogarchiver\task\backup_purge_task',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '5',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => false,
    ],
];

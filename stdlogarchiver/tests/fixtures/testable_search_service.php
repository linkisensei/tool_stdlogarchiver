<?php namespace tool_stdlogarchiver\backup\search;

use \coding_exception;
use \moodle_exception;
use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\backup\search\search_service;

class testable_search_service extends search_service{

    public function get_backups_on_interval(int $starttime, int $endtime, int $page = 0) : array {
        return parent::get_backups_on_interval($starttime, $endtime, $page);
    }

    public function get_filtered_logs_from_backup(backup $backup, array $filters) : array {
        return parent::get_filtered_logs_from_backup($backup, $filters);
    }

    public function make_filter_function(array $filters) : callable {
        return parent::make_filter_function($filters);
    }
}
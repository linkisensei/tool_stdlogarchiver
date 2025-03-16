<?php namespace tool_stdlogarchiver\output\tables;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

use \flexible_table;
use \tool_stdlogarchiver\output\renderables\search_results;

class search_info extends flexible_table {

    public function __construct($uniqueid, $baseurl) {
        parent::__construct($uniqueid);

        $columns = ['backupid', 'firstid', 'lastid', 'starttime', 'endtime', 'searched'];
        $headers = [];

        foreach ($columns as $column) {
            $headers[] = get_string("table:$column", 'tool_stdlogarchiver');
        }

        $this->set_attribute('class', 'search-info-table table-striped');
        $this->set_attribute('style', 'opacity:0.7;');
        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->define_baseurl($baseurl);

    }

    /**
     * Displays the table with the given set of templates
     * @param search_results $templates
     */
    public function display(search_results $search_results) {
        global $OUTPUT;

        $this->setup();

        $searched_backups = $search_results->count_searched_backups();
        foreach ($search_results->get_backups() as $index => $backup) {
            $data = [
                $backup->get('id'),
                $backup->get('firstid'),
                $backup->get('lastid'),
                userdate($backup->get('starttime')),
                userdate($backup->get('endtime')),
                ($index < $searched_backups) ? get_string('yes') : get_string('no'),
            ];
            $this->add_data($data);
        }
        
        $this->finish_output();
    }
}
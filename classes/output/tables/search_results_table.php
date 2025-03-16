<?php namespace tool_stdlogarchiver\output\tables;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

use \flexible_table;
use \moodle_url;
use \confirm_action;
use \tool_stdlogarchiver\util\standard_logstore;
use \tool_stdlogarchiver\output\renderables\search_results;

class search_results_table extends flexible_table {

    protected $ordered_columns = [];

    public function __construct($uniqueid, $baseurl) {
        parent::__construct($uniqueid);

        $this->ordered_columns = array_merge(['backupid'], standard_logstore::instance()->get_logstore_columns());

        $this->define_columns($this->ordered_columns);
        $this->define_headers($this->ordered_columns);
        $this->define_baseurl($baseurl);

        $this->column_class('backupid', 'backupid');

        $this->sortable(false);
    }

    /**
     * Displays the table with the given set of templates
     * @param search_results $templates
     */
    public function display(search_results $search_results) {
        global $OUTPUT;
        if (empty($search_results)) {
            echo $OUTPUT->box(get_string('table:no_search_results', 'tool_stdlogarchiver'), 'generalbox boxaligncenter');
            return;
        }

        $this->setup();

        foreach ($search_results as $result) {
            $data = [];
            
            foreach ($this->ordered_columns as $index => $column) {
                $data[] = isset($result->$column) ? $result->$column : '';
            }

            $this->add_data($data);
        }
        $this->finish_output();
    }
}
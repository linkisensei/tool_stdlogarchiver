<?php namespace tool_stdlogarchiver\output\tables;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

use \flexible_table;
use \tool_stdlogarchiver\output\renderables\search_results;
use \tool_stdlogarchiver\util\datetime_helper;

class search_info extends flexible_table {

    public function __construct(string $uniqueid, \moodle_url $baseurl) {
        parent::__construct($uniqueid);

        $columns = ['backupid', 'starttime', 'endtime', 'firstid', 'lastid', 'searched'];
        $headers = array_map(
            fn($col) => get_string("table:$col", 'tool_stdlogarchiver'),
            $columns
        );

        $this->set_attribute('class', 'search-info-table table-striped');
        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->define_baseurl($baseurl);
        $this->sortable(false);
    }

    public function display(search_results $search_results): void {
        $searched_ids    = array_map(fn($b) => $b->get('id'), $search_results->get_searched_backups());
        $pending_ids     = array_map(fn($b) => $b->get('id'), $search_results->get_pending_backups());

        $this->setup();

        foreach ($search_results->get_all_backups() as $backup) {
            $id = $backup->get('id');

            if (in_array($id, $searched_ids, true)) {
                $status = get_string('yes');
            } elseif (in_array($id, $pending_ids, true)) {
                $status = get_string('pending', 'core') ?: 'Pending';
            } else {
                $status = get_string('no');
            }

            $this->add_data([
                $id,
                datetime_helper::format((int) $backup->get('starttime')),
                datetime_helper::format((int) $backup->get('endtime')),
                $backup->get('firstid'),
                $backup->get('lastid'),
                $status,
            ]);
        }

        $this->finish_output();
    }
}

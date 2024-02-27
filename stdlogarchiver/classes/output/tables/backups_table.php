<?php namespace tool_stdlogarchiver\output\tables;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

use \pix_icon;
use \flexible_table;
use \moodle_url;
use \confirm_action;
use \tool_stdlogarchiver\util\standard_logstore;

class backups_table extends flexible_table {

    protected $ordered_columns = [];

    public function __construct($uniqueid, $baseurl) {
        parent::__construct($uniqueid);

        $this->ordered_columns = [
            'id',
            'starttime',
            'endtime',
            'fileformat',
            'timecreated',
            'external_backup',
            'restored',
            'restoring',
            'deleted',
            'actions'
        ];

        $headers = [];
        foreach ($this->ordered_columns as $column) {
            $headers[] = get_string("table:$column", 'tool_stdlogarchiver');
        }

        $this->define_columns($this->ordered_columns);
        $this->define_headers($headers);
        $this->define_baseurl($baseurl);

        $this->sortable(false);
    }

    /**
     * Displays the table with the given set of templates
     * @param array $templates
     */
    public function display($backups) {
        global $OUTPUT;
        if (empty($backups)) {
            echo $OUTPUT->box(get_string('table:no_backups', 'tool_stdlogarchiver'), 'generalbox boxaligncenter');
            return;
        }

        $this->setup();
        
        $str_delete_local = get_string('action:delete_local_backup', 'tool_stdlogarchiver');
        $str_restore_local = get_string('action:restore_local_backup', 'tool_stdlogarchiver');
        $str_download_local = get_string('action:download_local_backup', 'tool_stdlogarchiver');
        $str_unrestore_local = get_string('action:unrestore_local_backup', 'tool_stdlogarchiver');

        $confirm_delete_action = new confirm_action(get_string('confirm:delete_local_backup', 'tool_stdlogarchiver'));
        $confirm_restore_action = new confirm_action(get_string('confirm:restore_local_backup', 'tool_stdlogarchiver'));
        $confirm_unrestore_action = new confirm_action(get_string('confirm:unrestore_local_backup', 'tool_stdlogarchiver'));

        foreach ($backups as $backup) {
            $external_backup = $backup->get_external_backup();
            $restored = $backup->was_restored();
            $restoring = $backup->is_restoring();
            $deleted = (bool) $backup->get('deleted');

            $data = [
                $backup->get('id'),
                userdate($backup->get('starttime')),
                userdate($backup->get('endtime')),
                $backup->get('fileformat'),
                userdate($backup->get('timecreated')),
                $external_backup ? $external_backup->get('service') : get_string('no'),
                $restored ? get_string('yes') : get_string('no'),
                $restoring ? get_string('yes') : get_string('no'),
                $deleted ? get_string('yes') : get_string('no'),
            ];

            // Adding actions
            $actions = [];

            if(!$deleted){
                $actions[] = $OUTPUT->action_icon(
                    $backup->get_download_url(),
                    new pix_icon('i/export', $str_download_local),
                    null,
                    [
                        'title' => $str_download_local,
                    ]
                );
            }

            if(!$restored && !$restoring){
                $actions[] = $OUTPUT->action_icon(
                    new moodle_url(
                        $this->baseurl,
                        [
                            'backupid' => $backup->get('id'),
                            'action' => 'restore',
                            'sesskey' => sesskey()
                        ]
                    ),
                    new pix_icon('a/refresh', $str_restore_local),
                    $confirm_restore_action,
                    [
                        'title' => $str_restore_local,
                    ]
                );
            }

            if($restored){
                $actions[] = $OUTPUT->action_icon(
                    new moodle_url(
                        $this->baseurl,
                        [
                            'backupid' => $backup->get('id'),
                            'action' => 'unrestore',
                            'sesskey' => sesskey()
                        ]
                    ),
                    new pix_icon('t/dockclose', $str_unrestore_local),
                    $confirm_unrestore_action,
                    [
                        'title' => $str_unrestore_local,
                    ]
                );
            }

            if(!$deleted){
                $actions[] = $OUTPUT->action_icon(
                    new moodle_url(
                        $this->baseurl,
                        [
                            'backupid' => $backup->get('id'),
                            'action' => 'delete',
                            'sesskey' => sesskey()
                        ]
                    ),
                    new pix_icon('t/delete', $str_delete_local),
                    $confirm_delete_action,
                    [
                        'title' => $str_delete_local,
                    ]
                );
            }
            
            $data[] = implode('&nbsp;', $actions);
            $this->add_data($data);
        }
        $this->finish_output();
    }
}
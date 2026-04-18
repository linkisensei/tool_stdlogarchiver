<?php namespace tool_stdlogarchiver\output\tables;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

use \pix_icon;
use \flexible_table;
use \moodle_url;
use \confirm_action;
use \tool_stdlogarchiver\util\datetime_helper;

class backups_table extends flexible_table {

    public function __construct(string $uniqueid, moodle_url $baseurl) {
        parent::__construct($uniqueid);

        $columns = [
            'id', 'starttime', 'endtime', 'fileformat', 'timecreated',
            'searchable', 'external', 'restored', 'restoring', 'deleted_at', 'actions',
        ];

        $headers = array_map(
            fn($col) => get_string('table:' . $col, 'tool_stdlogarchiver'),
            $columns
        );

        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->define_baseurl($baseurl);
        $this->sortable(false);
    }

    public function display(iterable $backups): void {
        global $OUTPUT;

        if ($backups instanceof \Traversable) {
            $backups = iterator_to_array($backups);
        }

        if (empty($backups)) {
            echo $OUTPUT->box(get_string('table:no_backups', 'tool_stdlogarchiver'), 'generalbox boxaligncenter');
            return;
        }

        $this->setup();

        $str_delete    = get_string('action:delete_local_backup', 'tool_stdlogarchiver');
        $str_restore   = get_string('action:restore_local_backup', 'tool_stdlogarchiver');
        $str_download  = get_string('action:download_local_backup', 'tool_stdlogarchiver');
        $str_unrestore = get_string('action:unrestore_local_backup', 'tool_stdlogarchiver');

        $confirm_delete    = new confirm_action(get_string('confirm:delete_local_backup', 'tool_stdlogarchiver'));
        $confirm_restore   = new confirm_action(get_string('confirm:restore_local_backup', 'tool_stdlogarchiver'));
        $confirm_unrestore = new confirm_action(get_string('confirm:unrestore_local_backup', 'tool_stdlogarchiver'));

        foreach ($backups as $backup) {
            $is_deleted   = $backup->is_deleted();
            $is_restored  = $backup->was_restored();
            $is_restoring = $backup->is_restoring();
            $has_external = $backup->has_external();

            $row = [
                $backup->get('id'),
                datetime_helper::format((int) $backup->get('starttime')),
                datetime_helper::format((int) $backup->get('endtime')),
                strtoupper($backup->get('fileformat') ?? ''),
                datetime_helper::format((int) $backup->get('timecreated')),
                $backup->is_searchable() ? get_string('yes') : get_string('no'),
                $has_external ? $backup->get('external_service') : get_string('no'),
                $is_restored  ? get_string('yes') : get_string('no'),
                $is_restoring ? get_string('yes') : get_string('no'),
                $is_deleted   ? datetime_helper::format((int) $backup->get('deleted_at')) : get_string('no'),
            ];

            $actions = [];

            if (!$is_deleted && ($backup->local_file_exists() || $backup->is_cached())) {
                $actions[] = $OUTPUT->action_icon(
                    $backup->get_download_url(),
                    new pix_icon('i/export', $str_download),
                    null,
                    ['title' => $str_download]
                );
            }

            if (!$is_deleted && !$is_restored && !$is_restoring) {
                $actions[] = $OUTPUT->action_icon(
                    new moodle_url($this->baseurl, ['backupid' => $backup->get('id'), 'action' => 'restore', 'sesskey' => sesskey()]),
                    new pix_icon('a/refresh', $str_restore),
                    $confirm_restore,
                    ['title' => $str_restore]
                );
            }

            if ($is_restored) {
                $actions[] = $OUTPUT->action_icon(
                    new moodle_url($this->baseurl, ['backupid' => $backup->get('id'), 'action' => 'unrestore', 'sesskey' => sesskey()]),
                    new pix_icon('t/dockclose', $str_unrestore),
                    $confirm_unrestore,
                    ['title' => $str_unrestore]
                );
            }

            if (!$is_deleted) {
                $actions[] = $OUTPUT->action_icon(
                    new moodle_url($this->baseurl, ['backupid' => $backup->get('id'), 'action' => 'delete', 'sesskey' => sesskey()]),
                    new pix_icon('t/delete', $str_delete),
                    $confirm_delete,
                    ['title' => $str_delete]
                );
            }

            $row[] = implode('&nbsp;', $actions);
            $this->add_data($row);
        }

        $this->finish_output();
    }
}

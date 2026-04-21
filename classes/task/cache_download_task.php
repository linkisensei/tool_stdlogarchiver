<?php namespace tool_stdlogarchiver\task;

use \core\task\adhoc_task;
use \tool_stdlogarchiver\models\backup;

defined('MOODLE_INTERNAL') || die();

/**
 * Downloads an external backup file to the local cache for search.
 * Created by search_service when a backup is only available on external storage.
 */
class cache_download_task extends adhoc_task {

    public function execute(): void {
        $data     = (array) $this->get_custom_data();
        $backupid = (int) $data['backupid'];

        $backup = new backup($backupid);

        if ($backup->local_file_exists() || $backup->is_cached()) {
            return;
        }

        $backup->download_to_cache();

        mtrace("tool_stdlogarchiver: backup #{$backupid} cached at {$backup->get_cache_path()}");
    }

    public static function create_and_enqueue(int $backupid): void {
        if (self::is_enqueued($backupid)) {
            return;
        }
        $task = new static();
        $task->set_component('tool_stdlogarchiver');
        $task->set_custom_data(['backupid' => $backupid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    public static function is_enqueued(int $backupid): bool {
        global $DB;
        $task = new static();
        $task->set_component('tool_stdlogarchiver');
        $task->set_custom_data(['backupid' => $backupid]);
        $params = (array) \core\task\manager::record_from_adhoc_task($task);
        $select = 'classname = :classname AND component = :component AND ' .
            $DB->sql_compare_text('customdata', \core_text::strlen($params['customdata']) + 1) .
            ' = :customdata';
        return $DB->record_exists_select('task_adhoc', $select, $params);
    }
}

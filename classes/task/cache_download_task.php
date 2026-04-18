<?php namespace tool_stdlogarchiver\task;

use \core\task\adhoc_task;
use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\util\compression_helper;

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

        // Already locally available — nothing to do.
        if ($backup->local_file_exists() || $backup->is_cached()) {
            return;
        }

        if (!$backup->has_external()) {
            throw new \moodle_exception('noexternalbackup', 'tool_stdlogarchiver');
        }

        $service = config::get_external_backup_service($backup->get('external_service'));
        if (!$service) {
            throw new \moodle_exception('noexternalservice', 'tool_stdlogarchiver');
        }

        $ext_uri = $backup->get('external_uri');
        if (!$service->exists($ext_uri)) {
            throw new \moodle_exception('externalbackupnotavailable', 'tool_stdlogarchiver');
        }

        $cache_path = $backup->get_cache_path();

        // For .db.gz external files, download gz then decompress to .db.
        if (str_ends_with($ext_uri, '.gz')) {
            $gz_tmp = $cache_path . '.gz.tmp';
            $service->download_to_path($ext_uri, $gz_tmp);
            compression_helper::gunzip($gz_tmp, $cache_path);
            @unlink($gz_tmp);
        } else {
            $service->download_to_path($ext_uri, $cache_path);
        }

        chmod($cache_path, 0444);

        mtrace("tool_stdlogarchiver: backup #{$backupid} cached at {$cache_path}");
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

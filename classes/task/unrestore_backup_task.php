<?php namespace tool_stdlogarchiver\task;

use \tool_stdlogarchiver\models\backup;
use \core\task\adhoc_task;

class unrestore_backup_task extends adhoc_task {

    public function execute() {
        try {
            \core_php_time_limit::raise();
            raise_memory_limit(MEMORY_EXTRA);

            $data = (array) $this->get_custom_data();
            mtrace('Restoring backup #' . $data['backupid']);

            $backup = new backup($data['backupid']);
            $backup->undo_restore();

        } catch (\Throwable $th) {
            mtrace_exception($th);
            throw $th;
        }
    }


    public static function create_and_enqueue(int $backupid) : adhoc_task {
        $task = self::create_task($backupid);
        \core\task\manager::queue_adhoc_task($task, true);
        return $task;     
    }

    protected static function create_task(int $backupid) : adhoc_task {
        $task = new static();
        $task->set_component('tool_stdlogarchiver');
        $task->set_blocking(true);
        $task->set_custom_data(array(
            'backupid' => $backupid,
        ));

        return $task;
    }

    public static function is_enqueued(int $backupid) : bool {
        global $DB;

        $task = self::create_task($backupid);
        $params = (array) \core\task\manager::record_from_adhoc_task($task);
        $select = 'classname = :classname AND component = :component AND ' .
            $DB->sql_compare_text('customdata', \core_text::strlen($params['customdata']) + 1) . ' = :customdata';

        return $DB->record_exists_select('task_adhoc', $select, $params);
    }
}
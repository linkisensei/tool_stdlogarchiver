<?php namespace tool_stdlogarchiver\task;

use \moodle_recordset;
use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\util\standard_logstore;
use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\models\external\external_backup;
use \tool_stdlogarchiver\util\logstored_other_trait;

defined('MOODLE_INTERNAL') || die();

class external_backup_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:external_backup', 'tool_stdlogarchiver');
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        global $DB;    

        if(!config::is_enabled()){
            mtrace('Plug-in disabled.'); 
            return;
        }

        raise_memory_limit(MEMORY_HUGE);
        \core_php_time_limit::raise();

        $service = config::get_external_backup_service();
        if(empty($service) || !$service::is_enabled()){
            mtrace('No external backup service enabled.');
            return false;
        }

        foreach ($this->get_backups_with_no_external_backups() as $record) {
            mtrace('Uploading backup #' . $record->id . ' to ' . $service::get_name());

            try {
                $backup = new backup(0, $record);

                $external_backup = $service->upload($backup);
                if(empty($external_backup)){
                    continue;
                }

                $external_backup->save();
            
                if(config::should_delete_local_backup_after_external_backup()){
                    if($file = $backup->get_file()){
                        $file->delete();
                    }
                }
            } catch (\Throwable $th) {
                debugging($th->getMessage(), DEBUG_DEVELOPER, $th->getTrace());
            }
        }

    }

    /**
     * Returns a recordset will all backups that were
     * not backed-up to an external service
     *
     * @return moodle_recordset
     */
    protected function get_backups_with_no_external_backups() : moodle_recordset {
        global $DB;
        
        $backup_table = '{' . backup::TABLE . '}';
        $external_backup_table = '{' . external_backup::TABLE . '}';

        $sql = "SELECT b.*
                FROM $backup_table b
                    LEFT JOIN $external_backup_table e ON (
                        e.backupid = b.id
                    )
                WHERE b.pathnamehash IS NOT NULL
                    AND e.id IS NULL";

        return $DB->get_recordset_sql($sql);
    }


}

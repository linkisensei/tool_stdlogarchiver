<?php namespace tool_stdlogarchiver\task;

use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\util\standard_logstore;
use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\util\logstored_other_trait;

defined('MOODLE_INTERNAL') || die();

class archive_task extends \core\task\scheduled_task {

    use logstored_other_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:cleanup', 'tool_stdlogarchiver');
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

        $max_execution_time = 5 * MINSECS;
        $execution_starttime = time();
        $execution_timelimit = $execution_starttime + $max_execution_time;

        while(true){
            $not_enough_records = !$this->backup_logs_to_file();
            if($not_enough_records || time() > $execution_timelimit){
                break;
            }
        }
    }


    /**
     * Backup a number of log records and then deletes them
     * from the database.
     * 
     * @return boolean false if there are not enough records
     */
    protected function backup_logs_to_file() : bool {
        global $DB;

        $writer_class = config::get_writer_class();
        $writer = new $writer_class();

        $logstore_helper = standard_logstore::instance();
        $table = $logstore_helper->get_logstore_table();
        $limit = config::get_records_per_file();
        $min_quantity = floor($limit/2);

        $params = [
            'max_timecreated' => time() - config::get_log_lifetime(),
            'min_id' => 0,
        ];

        if($previous_backup = backup::get_last_backup()){
            $params['min_id'] = $previous_backup->get('lastid');
        }

        $select = "timecreated < :max_timecreated AND id > :min_id";

        $log_records_count = $DB->count_records_select($table, $select, $params);
        if($log_records_count < $min_quantity){
            return false; // Not enough records to proceed
        }
        
        $records = $DB->get_records_select($table, $select, $params, 'id', '*', 0, $limit);

        $last_record = end($records);
        $first_record = reset($records);

        foreach ($records as $record) {
            $record->other = self::to_json($record->other); // Always encoded as json
            $writer->append($record);
        }

        $file = $writer->to_stored_file();
        $writer->destroy(); // Making sure its destroyed
        
        $raw_backup_record = [
            'firstid' => $first_record->id,
            'starttime' => $first_record->timecreated,
            'lastid' => $last_record->id,
            'endtime' => $last_record->timecreated,
        ];
        $backup_record = backup::create_from($raw_backup_record, $file);

        try {
            $DB->delete_records_select($table, "id BETWEEN :firstid AND :lastid", $raw_backup_record);
            $backup_record->save();

            mtrace('Backup #' . $backup_record->get('id') . ' created');
            mtrace('Logs from ' . $backup_record->get('firstid') . ' to ' . $backup_record->get('lastid') . ' deleted!');
            return true;

        } catch (\Exception $e) {
            $file->delete();
            throw $e;
        }
    }

}

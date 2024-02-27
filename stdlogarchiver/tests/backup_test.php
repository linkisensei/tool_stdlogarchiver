<?php namespace tool_stdlogarchiver;

defined('MOODLE_INTERNAL') || die();

global $CFG;

use \advanced_testcase;
use \testcase;

use \moodle_exception;
use \coding_exception;
use \tool_stdlogarchiver\util\standard_logstore;
use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;

class backup_test extends advanced_testcase{

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
    }

    public static function mock_user_login_failed_log(object $user, int $quantity = 1, int $timecreated = 0){
        global $PAGE, $DB;

        $event = \core\event\user_login_failed::create([
            'userid' => $user->id,
            'other' => [
                'username' => $user->username,
                'reason' => 'mocked reason'
            ],
        ]);

        $entry = $event->get_data();
        if (standard_logstore::uses_json_for_others_column()) {
            $entry['other'] = json_encode($entry['other']);
        } else {
            $entry['other'] = serialize($entry['other']);
        }
        $entry['origin'] = $PAGE->requestorigin;
        $entry['ip'] = $PAGE->requestip;
        $entry['realuserid'] = \core\session\manager::is_loggedinas() ? $GLOBALS['USER']->realuser : null;
        $entry['timecreated'] = $timecreated;

        $event_entries = [];
        for ($i=0; $i < $quantity; $i++) { 
            $event_entries[] = $entry;
        }

        $DB->insert_records('logstore_standard_log', $event_entries);
    }

    /**
     * @group xcurrent
     * @return void
     */
    public function test_archive_task(){
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();

        $max_records_per_file = 500;
        $min_records_per_file = floor($max_records_per_file/2);

        // Setting plugin configs
        config::set(config::CONFIG_ENABLED, true);
        config::set(config::CONFIG_RECORDS_PER_FILE, $max_records_per_file);
        config::set(config::CONFIG_BACKUP_FORMAT, config::BACKUP_FORMAT_CSV);
        config::set(config::CONFIG_LOG_LIFETIME, 10 * WEEKSECS);

        // Setting logstore configs
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);

        // Creating user and log data
        $user = $this->getDataGenerator()->create_user();

        $now = time();
        self::mock_user_login_failed_log($user, 1000, $now - 1 * YEARSECS);
        self::mock_user_login_failed_log($user, 1000, $now - 0.5 * YEARSECS);
        self::mock_user_login_failed_log($user, 1000, $now - 4 * WEEKSECS);

        // Running task
        $task = new \tool_stdlogarchiver\task\archive_task();
        $task->execute();

        // Checking deletions
        $backups = backup::get_records([]);

        $log_table = standard_logstore::instance()->get_logstore_table();
        $select = "id >= :firstid AND id <= :lastid";
        foreach ($backups as $backup) {
            $this->assertEquals(0, $DB->count_records_select($log_table, $select, (array) $backup->to_record()));
        }

        $this->assertTrue($DB->count_records($log_table) > 0);
    }


    /**
     * @group xcurrent
     * @return void
     */
    public function test_backup_restore(){
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();

        $max_records_per_file = 500;
        $min_records_per_file = floor($max_records_per_file/2);

        // Setting plugin configs
        config::set(config::CONFIG_ENABLED, true);
        config::set(config::CONFIG_RECORDS_PER_FILE, $max_records_per_file);
        config::set(config::CONFIG_BACKUP_FORMAT, config::BACKUP_FORMAT_CSV);
        config::set(config::CONFIG_LOG_LIFETIME, 10 * WEEKSECS);

        // Setting logstore configs
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);

        // Creating user and log data
        $user = $this->getDataGenerator()->create_user();

        // Cleaning log table
        $log_table = standard_logstore::instance()->get_logstore_table();
        $DB->execute('TRUNCATE {' . $log_table . '}');

        $now = time();
        self::mock_user_login_failed_log($user, $max_records_per_file, $now - 1 * YEARSECS);

        // Running task
        $task = new \tool_stdlogarchiver\task\archive_task();
        $task->execute();

        
        $this->assertEquals(0, $DB->count_records($log_table));

        // Restoring backup
        $backups = backup::get_records([]);
        $backup = reset($backups);

        $backup->restore();

        $select = "id >= :firstid AND id <= :lastid";
        $this->assertEquals($max_records_per_file, $DB->count_records_select($log_table, $select, (array) $backup->to_record()));
    }


    protected function file_exists_on_s3(string $key) : bool {
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/stdlogarchiver/libs/autoload.php');

        $s3 = new Aws\S3\S3Client( [
            'region'      => config::get_aws_region(),
            'version'     => 'latest',
            'credentials' => config::get_aws_credentials(),
        ]);

        try {
            $result = $s3->headObject([
                'Bucket'     => config::get_s3_bucket(),
                'Key'        => $key,
            ]);

            return true;
        } catch (Aws\Exception\AwsException $e) {
            return false;
        }
    }
}
<?php namespace tool_stdlogarchiver;

defined('MOODLE_INTERNAL') || die();

use \advanced_testcase;
use \moodle_exception;
use \tool_stdlogarchiver\util\standard_logstore;
use \tool_stdlogarchiver\util\log_generator;
use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;

class backup_test extends advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
    }

    protected static function set_default_configs(): void {
        config::set(config::CONFIG_ENABLED, 1);
        config::set(config::CONFIG_MAX_RECORDS_PER_FILE, 500);
        config::set(config::CONFIG_BACKUP_FORMAT, config::BACKUP_FORMAT_DB);
        config::set(config::CONFIG_LOG_LIFETIME, 10 * WEEKSECS);

        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);
    }

    protected static function insert_log_records(object $user, int $quantity, int $timecreated): void {
        log_generator::insert_synthetic_logs($quantity, [
            'userid' => $user->id,
            'starttime' => $timecreated,
            'timestep' => 0,
            'origin' => 'web',
            'other' => ['reason' => 'test'],
        ]);
    }

    /** @group xcurrent */
    public function test_archive_task(): void {
        global $DB;

        self::set_default_configs();

        $user = $this->getDataGenerator()->create_user();
        $now  = time();

        self::insert_log_records($user, 300, $now - YEARSECS);
        self::insert_log_records($user, 300, $now - 26 * WEEKSECS - DAYSECS);
        self::insert_log_records($user, 300, $now - 4 * WEEKSECS); // too recent — should not archive

        $task = new \tool_stdlogarchiver\task\archive_task();
        $task->execute();

        $backups   = backup::get_records([]);
        $log_table = standard_logstore::instance()->get_logstore_table();

        $this->assertNotEmpty($backups, 'Expected at least one backup to be created');

        foreach ($backups as $b) {
            $still_in_db = $DB->count_records_select(
                $log_table,
                'id >= :firstid AND id <= :lastid',
                ['firstid' => $b->get('firstid'), 'lastid' => $b->get('lastid')]
            );
            $this->assertEquals(0, $still_in_db,
                "Backup #{$b->get('id')} records were not deleted from the logstore");
        }

        // Recent records should remain.
        $this->assertGreaterThan(0, $DB->count_records($log_table));
    }

    /** @group xcurrent */
    public function test_archive_task_creates_sqlite_file(): void {
        global $DB;

        self::set_default_configs();
        config::set(config::CONFIG_BACKUP_FORMAT, config::BACKUP_FORMAT_DB);

        $user = $this->getDataGenerator()->create_user();
        $now  = time();

        self::insert_log_records($user, 100, $now - YEARSECS);

        $task = new \tool_stdlogarchiver\task\archive_task();
        $task->execute();

        $backups = backup::get_records([]);
        $this->assertNotEmpty($backups, 'Expected a backup to be created');

        $backup = reset($backups);
        $this->assertEquals('db', $backup->get('fileformat'));
        $this->assertTrue($backup->local_file_exists(), 'SQLite file does not exist on disk');

        // Verify SQLite file is a valid database.
        $path = $backup->get_local_file_path();
        $db = new \SQLite3('file:' . $path . '?immutable=1', SQLITE3_OPEN_READONLY | SQLITE3_OPEN_URI);
        $count = $db->querySingle('SELECT COUNT(*) FROM logs');
        $db->close();

        $this->assertGreaterThan(0, $count, 'SQLite backup file contains no records');
    }

    /** @group xcurrent */
    public function test_backup_restore(): void {
        global $DB;

        self::set_default_configs();

        $user      = $this->getDataGenerator()->create_user();
        $now       = time();
        $log_table = standard_logstore::instance()->get_logstore_table();

        $DB->execute('DELETE FROM {' . $log_table . '}');
        self::insert_log_records($user, 200, $now - YEARSECS);

        $task = new \tool_stdlogarchiver\task\archive_task();
        $task->execute();

        $this->assertEquals(0, $DB->count_records($log_table),
            'All records should have been archived');

        $backups = backup::get_records([]);
        $backup  = reset($backups);
        $this->assertNotFalse($backup, 'Expected a backup record to be created');

        $backup->restore();

        $restored_count = $DB->count_records_select(
            $log_table,
            'id >= :firstid AND id <= :lastid',
            ['firstid' => $backup->get('firstid'), 'lastid' => $backup->get('lastid')]
        );
        $this->assertGreaterThan(0, $restored_count, 'Expected restored records in logstore');
    }

    /** @group xcurrent */
    public function test_archive_task_skips_restored_backups(): void {
        global $DB;

        self::set_default_configs();

        $user      = $this->getDataGenerator()->create_user();
        $now       = time();
        $log_table = standard_logstore::instance()->get_logstore_table();

        $DB->execute('DELETE FROM {' . $log_table . '}');
        self::insert_log_records($user, 100, $now - YEARSECS);

        $task = new \tool_stdlogarchiver\task\archive_task();
        $task->execute();

        $backup = reset(backup::get_records([]));
        $backup->restore();

        // Re-run archive — the restored IDs should not be re-archived.
        $task->execute();

        $restored_count = $DB->count_records_select(
            $log_table,
            'id >= :firstid AND id <= :lastid',
            ['firstid' => $backup->get('firstid'), 'lastid' => $backup->get('lastid')]
        );
        $this->assertGreaterThan(0, $restored_count,
            'Restored records should not be re-archived by the archive task');
    }

    /** @group xcurrent */
    public function test_archive_task_limits_days_per_execution(): void {
        global $DB;

        self::set_default_configs();
        config::set(config::CONFIG_MAX_RECORDS_PER_FILE, 1);

        $user = $this->getDataGenerator()->create_user();
        $now = time();
        $log_table = standard_logstore::instance()->get_logstore_table();

        $DB->execute('DELETE FROM {' . $log_table . '}');
        for ($i = 0; $i < 12; $i++) {
            self::insert_log_records($user, 1, $now - YEARSECS - ($i * DAYSECS));
        }

        $task = new \tool_stdlogarchiver\task\archive_task();
        $task->execute();

        $this->assertCount(
            10,
            backup::get_records([]),
            'Archive task should stop after processing the configured per-run cap of days'
        );

        $this->assertGreaterThan(
            0,
            $DB->count_records($log_table),
            'Some records should remain for the next execution once the per-run day cap is reached'
        );
    }
}

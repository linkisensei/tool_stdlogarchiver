<?php namespace tool_stdlogarchiver;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use tool_stdlogarchiver\backup\external\external_backup_service_interface;
use tool_stdlogarchiver\backup\external\external_s3_backup_service;
use tool_stdlogarchiver\models\backup;

class testable_external_s3_backup_service extends external_s3_backup_service {
    public bool $throw_on_exists = false;
    public bool $exists_result = false;

    protected function load_sdk(): void {
        // Skip loading the AWS SDK in unit tests.
    }

    protected function remote_object_exists(string $bucket, string $key): bool {
        if ($this->throw_on_exists) {
            throw new \RuntimeException('Simulated remote error');
        }
        return $this->exists_result;
    }

    public function expose_autoload_path(): string {
        return $this->get_autoload_path();
    }
}

class fake_external_backup_service implements external_backup_service_interface {
    public static bool $exists_result = false;
    public static int $upload_calls = 0;
    public static int $download_calls = 0;
    public static int $delete_calls = 0;

    public static function reset(): void {
        self::$exists_result = false;
        self::$upload_calls = 0;
        self::$download_calls = 0;
        self::$delete_calls = 0;
    }

    public static function is_enabled(): bool {
        return true;
    }

    public static function get_name(): string {
        return 'fake_s3';
    }

    public function exists(string $external_uri): bool {
        return self::$exists_result;
    }

    public function upload(backup $backup): string {
        self::$upload_calls++;
        return 's3://test-bucket/test-object.db.gz';
    }

    public function download_to_path(string $external_uri, string $dest_path): void {
        self::$download_calls++;
        file_put_contents($dest_path, 'downloaded');
    }

    public function delete(string $external_uri): void {
        self::$delete_calls++;
    }
}

class external_storage_test extends advanced_testcase {

    private function expect_task_trace_output(): void {
        $this->expectOutputRegex('/tool_stdlogarchiver:/');
    }

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        fake_external_backup_service::reset();
        config::set_external_backup_services_override_for_testing([
            fake_external_backup_service::get_name() => fake_external_backup_service::class,
        ]);
    }

    protected function tearDown(): void {
        config::set_external_backup_services_override_for_testing(null);
        parent::tearDown();
    }

    private function create_external_backup_record(array $overrides = []): backup {
        $record = array_merge([
            'firstid' => 1,
            'lastid' => 10,
            'starttime' => 1700000000,
            'endtime' => 1700003600,
            'fileformat' => 'db',
            'local_path' => null,
            'external_service' => fake_external_backup_service::get_name(),
            'external_uri' => 's3://test-bucket/path/test.db.gz',
            'external_customdata' => json_encode(['source' => 'test']),
            'restored' => 0,
            'deleted_at' => 0,
        ], $overrides);

        $backup = new backup(0, (object) $record);
        $backup->save();

        return $backup;
    }

    /** @group xcurrent */
    public function test_external_s3_service_uses_libs_autoload_path(): void {
        global $CFG;

        $service = new testable_external_s3_backup_service();

        $this->assertSame(
            $CFG->dirroot . '/admin/tool/stdlogarchiver/libs/autoload.php',
            $service->expose_autoload_path()
        );
    }

    /** @group xcurrent */
    public function test_external_s3_exists_returns_true_when_remote_object_exists(): void {
        $service = new testable_external_s3_backup_service();
        $service->exists_result = true;

        $this->assertTrue($service->exists('s3://bucket/key.db.gz'));
    }

    /** @group xcurrent */
    public function test_external_s3_exists_returns_false_when_remote_object_is_missing(): void {
        $service = new testable_external_s3_backup_service();
        $service->exists_result = false;

        $this->assertFalse($service->exists('s3://bucket/key.db.gz'));
    }

    /** @group xcurrent */
    public function test_external_s3_exists_returns_false_on_remote_error(): void {
        $service = new testable_external_s3_backup_service();
        $service->throw_on_exists = true;

        $this->assertFalse($service->exists('s3://bucket/key.db.gz'));
    }

    /** @group xcurrent */
    public function test_cache_download_task_does_not_download_when_remote_file_is_unavailable(): void {
        fake_external_backup_service::$exists_result = false;
        $backup = $this->create_external_backup_record();

        $task = new \tool_stdlogarchiver\task\cache_download_task();
        $task->set_custom_data(['backupid' => $backup->get('id')]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('externalbackupnotavailable', 'tool_stdlogarchiver'));

        try {
            $task->execute();
        } finally {
            $this->assertSame(0, fake_external_backup_service::$download_calls);
        }
    }

    /** @group xcurrent */
    public function test_backup_purge_task_preserves_remote_metadata(): void {
        fake_external_backup_service::$exists_result = true;
        $backup = $this->create_external_backup_record([
            'deleted_at' => time() - HOURSECS,
        ]);

        $this->expect_task_trace_output();
        $task = new \tool_stdlogarchiver\task\backup_purge_task();
        $task->execute();

        $backup = new backup($backup->get('id'));

        // External deletion is gated behind config::FEATURE_PURGE_EXTERNAL (currently off).
        // The purge task must never touch remote files or their metadata.
        $this->assertSame(0, fake_external_backup_service::$delete_calls);
        $this->assertSame(fake_external_backup_service::get_name(), $backup->get('external_service'));
        $this->assertNotNull($backup->get('external_uri'));
        $this->assertNotNull($backup->get('external_customdata'));
    }

    /** @group xcurrent */
    public function test_external_migration_task_uploads_immediately_when_delay_is_zero(): void {
        config::set(config::CONFIG_EXTERNAL_BACKUP_SERVICE, fake_external_backup_service::get_name());
        config::set(config::CONFIG_EXTERNAL_MIGRATION_DELAY, 0);

        $filename = 'migration_immediate.db';
        $path = config::get_backup_dir() . '/' . $filename;
        file_put_contents($path, 'sqlite-bytes');

        $backup = new backup(0, (object) [
            'firstid' => 11,
            'lastid' => 20,
            'starttime' => 1700007200,
            'endtime' => 1700010800,
            'fileformat' => 'db',
            'local_path' => $filename,
            'external_service' => null,
            'external_uri' => null,
            'external_customdata' => null,
            'restored' => 0,
            'deleted_at' => 0,
            'timecreated' => time(),
        ]);
        $backup->save();

        $this->expect_task_trace_output();
        $task = new \tool_stdlogarchiver\task\external_migration_task();
        $task->execute();

        $backup = new backup($backup->get('id'));

        $this->assertSame(1, fake_external_backup_service::$upload_calls);
        $this->assertSame(fake_external_backup_service::get_name(), $backup->get('external_service'));
        $this->assertSame('s3://test-bucket/test-object.db.gz', $backup->get('external_uri'));
    }
}

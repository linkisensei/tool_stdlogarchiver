<?php namespace tool_stdlogarchiver;

use \moodle_exception;
use \tool_stdlogarchiver\backup\external\external_s3_backup_service;
use \tool_stdlogarchiver\backup\external\external_backup_service_interface;

class config {
    /** @var array<string, string>|null */
    protected static ?array $external_backup_services_override = null;

    // Config keys.
    const CONFIG_ENABLED                   = 'enabled';
    const CONFIG_MAX_RECORDS_PER_FILE      = 'max_records_per_file';
    const CONFIG_BACKUP_FORMAT             = 'backup_format';
    const CONFIG_LOG_LIFETIME              = 'log_lifetime';
    const CONFIG_BACKUP_DIR                = 'backup_dir';
    const CONFIG_BACKUP_RETENTION_TTL      = 'backup_retention_ttl';
    const CONFIG_CACHE_DIR                 = 'cache_dir';
    const CONFIG_CACHE_TTL                 = 'cache_ttl';
    const CONFIG_EXTERNAL_BACKUP_SERVICE   = 'external_backup_service';
    const CONFIG_EXTERNAL_MIGRATION_DELAY  = 'external_migration_delay';
    const CONFIG_DELETE_LOCAL_AFTER_EXTERNAL = 'delete_local_after_external';
    const CONFIG_ARCHIVE_WATERMARK_TIME    = 'archive_watermark_time';
    const CONFIG_ARCHIVE_WATERMARK_ID      = 'archive_watermark_id';
    const CONFIG_AWS_REGION                = 'aws_region';
    const CONFIG_AWS_KEY                   = 'aws_key';
    const CONFIG_AWS_SECRET                = 'aws_secret';
    const CONFIG_S3_BUCKET                 = 's3_bucket';
    const CONFIG_S3_FOLDER                 = 's3_folder';

    // Feature flags (code-only, no admin setting).
    // Set to true to allow purge tasks to also delete files from external storage.
    // Disabled by default to prevent accidental data loss; infrastructure owns the
    // remote lifecycle until this is explicitly re-enabled.
    const FEATURE_PURGE_EXTERNAL = false;

    // Backup format identifiers.
    const BACKUP_FORMAT_DB  = 'db';
    const BACKUP_FORMAT_CSV = 'csv';

    const FORMAT_WRITER_MAP = [
        self::BACKUP_FORMAT_DB  => \tool_stdlogarchiver\backup\writers\sqlite_writer::class,
        self::BACKUP_FORMAT_CSV => \tool_stdlogarchiver\backup\writers\csv_writer::class,
    ];

    const FORMAT_READER_MAP = [
        self::BACKUP_FORMAT_DB  => \tool_stdlogarchiver\backup\readers\sqlite_reader::class,
        self::BACKUP_FORMAT_CSV => \tool_stdlogarchiver\backup\readers\csv_reader::class,
    ];

    public static function get(string $key, mixed $default = null): mixed {
        $val = get_config('tool_stdlogarchiver', $key);
        return ($val !== false && $val !== null) ? $val : $default;
    }

    public static function set(string $key, mixed $value): void {
        set_config($key, $value, 'tool_stdlogarchiver');
    }

    public static function is_enabled(): bool {
        return (bool) self::get(self::CONFIG_ENABLED);
    }

    public static function get_max_records_per_file(): int {
        return (int) self::get(self::CONFIG_MAX_RECORDS_PER_FILE, 200000);
    }

    public static function get_backup_format(): string {
        return (string) self::get(self::CONFIG_BACKUP_FORMAT, self::BACKUP_FORMAT_DB);
    }

    public static function get_log_lifetime(): int {
        return (int) self::get(self::CONFIG_LOG_LIFETIME, DAYSECS);
    }

    /**
     * How long to keep backup files before auto-purging. 0 = keep forever.
     */
    public static function get_backup_retention_ttl(): int {
        return (int) self::get(self::CONFIG_BACKUP_RETENTION_TTL, 0);
    }

    public static function get_backup_dir(): string {
        global $CFG;
        $dir = trim((string) self::get(self::CONFIG_BACKUP_DIR, ''));
        if (empty($dir)) {
            $dir = $CFG->dataroot . '/tool_stdlogarchiver';
        }
        $dir = rtrim($dir, '/');
        if (!is_dir($dir)) {
            make_writable_directory($dir);
            file_put_contents($dir . '/.htaccess', "deny from all\n");
        }
        return $dir;
    }

    public static function get_cache_dir(): string {
        $dir = trim((string) self::get(self::CONFIG_CACHE_DIR, ''));
        if (empty($dir)) {
            $dir = sys_get_temp_dir() . '/tool_stdlogarchiver_cache';
        }
        $dir = rtrim($dir, '/');
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        return $dir;
    }

    /**
     * How long cached (downloaded from external) SQLite files are kept. Seconds.
     */
    public static function get_cache_ttl(): int {
        return (int) self::get(self::CONFIG_CACHE_TTL, 86400);
    }

    /**
     * How long before a local backup file is migrated to external storage. 0 = disabled.
     */
    public static function get_external_migration_delay(): int {
        return (int) self::get(self::CONFIG_EXTERNAL_MIGRATION_DELAY, 0);
    }

    public static function get_archive_watermark(): object {
        return (object) [
            'time' => (int) self::get(self::CONFIG_ARCHIVE_WATERMARK_TIME, 0),
            'id'   => (int) self::get(self::CONFIG_ARCHIVE_WATERMARK_ID, 0),
        ];
    }

    public static function set_archive_watermark(int $time, int $id): void {
        self::set(self::CONFIG_ARCHIVE_WATERMARK_TIME, $time);
        self::set(self::CONFIG_ARCHIVE_WATERMARK_ID, $id);
    }

    public static function delete_local_after_external(): bool {
        return (bool) self::get(self::CONFIG_DELETE_LOCAL_AFTER_EXTERNAL, false);
    }

    public static function get_aws_region(): string {
        return (string) self::get(self::CONFIG_AWS_REGION, '');
    }

    public static function get_aws_credentials(): array {
        return [
            'key'    => (string) self::get(self::CONFIG_AWS_KEY, ''),
            'secret' => (string) self::get(self::CONFIG_AWS_SECRET, ''),
        ];
    }

    public static function get_s3_bucket(): string {
        return (string) self::get(self::CONFIG_S3_BUCKET, '');
    }

    public static function get_s3_folder(): string {
        return (string) self::get(self::CONFIG_S3_FOLDER, 'backups');
    }

    public static function get_writer_class(): string {
        $format = self::get_backup_format();
        if (!isset(self::FORMAT_WRITER_MAP[$format])) {
            throw new moodle_exception('invalidbackupformat', 'tool_stdlogarchiver');
        }
        return self::FORMAT_WRITER_MAP[$format];
    }

    public static function get_reader_class(string $format): string {
        if (!isset(self::FORMAT_READER_MAP[$format])) {
            throw new moodle_exception('invalidbackupformat', 'tool_stdlogarchiver');
        }
        return self::FORMAT_READER_MAP[$format];
    }

    public static function get_external_backup_services(): array {
        if (self::$external_backup_services_override !== null) {
            return self::$external_backup_services_override;
        }

        return [
            external_s3_backup_service::get_name() => external_s3_backup_service::class,
        ];
    }

    /**
     * Test-only override to replace the external service registry.
     *
     * @param array<string, string>|null $services
     */
    public static function set_external_backup_services_override_for_testing(?array $services): void {
        self::$external_backup_services_override = $services;
    }

    public static function get_external_backup_service(?string $service = null): ?external_backup_service_interface {
        if (empty($service)) {
            $service = self::get(self::CONFIG_EXTERNAL_BACKUP_SERVICE, null);
        }
        $services = self::get_external_backup_services();
        if (empty($services[$service])) {
            return null;
        }
        return new $services[$service]();
    }
}

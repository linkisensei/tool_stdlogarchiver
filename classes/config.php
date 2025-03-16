<?php namespace tool_stdlogarchiver;

use \moodle_exception;
use \tool_stdlogarchiver\backup\external\external_s3_backup_service;
use \tool_stdlogarchiver\backup\external\external_backup_service_interface;

class config{
    const CONFIG_ENABLED = 'enabled';
    const CONFIG_RECORDS_PER_FILE = 'records_per_file';
    const CONFIG_BACKUP_FORMAT = 'backup_format';
    const CONFIG_LOG_LIFETIME = 'log_lifetime';
    const CONFIG_EXTERNAL_BACKUP_SERVICE = 'external_backup_service';
    const CONFIG_DELETE_LOCAL_AFTER_EXTERNAL_BACKUP = 'delete_local_after_external_backup';
    
    const CONFIG_AWS_REGION = 'aws_region';
    const CONFIG_AWS_KEY = 'aws_key';
    const CONFIG_AWS_SECRET = 'aws_secret';
    const CONFIG_S3_BUCKET = 's3_bucket';
    const CONFIG_S3_FOLDER = 's3_folder';

    const BACKUP_FORMAT_CSV = 'csv';
    const BACKUP_FORMAT_SQL = 'sql';

    const FORMAT_WRITER_MAP = [
        self::BACKUP_FORMAT_CSV => \tool_stdlogarchiver\backup\writers\csv_writer::class,
    ];

    const FORMAT_READER_MAP = [
        self::BACKUP_FORMAT_CSV => \tool_stdlogarchiver\backup\readers\csv_reader::class,
    ];

    const BACKUPS_FILEAREA = 'backups';
    
    /**
     * Return a plugin config by key
     *
     * @param string $key
     * @return mixed|null
     */
    public static function get($key, $default = null){
        return get_config('tool_stdlogarchiver', $key) ?: $default;
    }

    /**
     * Sets a value to a config
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, $value){
        set_config($key, $value, 'tool_stdlogarchiver');
    }
    
    public static function is_enabled() : bool {
        return (bool) self::get(self::CONFIG_ENABLED);
    }

    public static function get_records_per_file() : int {
        return (int) self::get(self::CONFIG_RECORDS_PER_FILE, 10000);
    }

    public static function get_aws_region() : string {
        return self::get(self::CONFIG_AWS_REGION, '');
    }

    public static function get_aws_credentials() : array {
        return [
            'key' => self::get(self::CONFIG_AWS_KEY),
            'secret' => self::get(self::CONFIG_AWS_SECRET),
        ];
    }

    public static function get_s3_bucket() : string {
        return self::get(self::CONFIG_S3_BUCKET, '');
    }

    public static function get_s3_folder() : string {
        return self::get(self::CONFIG_S3_FOLDER, '');
    }

    public static function get_backup_format() : string {
        return self::get(self::CONFIG_BACKUP_FORMAT, self::BACKUP_FORMAT_CSV);
    }

    public static function get_log_lifetime() : int {
        return (int) self::get(self::CONFIG_LOG_LIFETIME, DAYSECS);
    }

    public static function should_delete_local_backup_after_external_backup() : bool {
        return (bool) self::get(self::CONFIG_DELETE_LOCAL_AFTER_EXTERNAL_BACKUP, false);
    }
        
    public static function get_writer_class() : string {
        return self::FORMAT_WRITER_MAP[self::get_backup_format()];
    }

    public static function get_reader_class(string $format) : string {
        if(!isset(self::FORMAT_READER_MAP[$format])){
            throw new moodle_exception("Invalid backup format!");
        }
        return self::FORMAT_READER_MAP[$format];
    }


    public static function get_external_backup_services() : array {
        return [
            external_s3_backup_service::get_name() => external_s3_backup_service::class,
        ];
    }


    /**
     * Returns an instance of the selected external
     * backup service.
     *
     * @param string|null $service if not null, returns the given service's class
     * @return external_backup_service_interface|null
     */
    public static function get_external_backup_service(?string $service = null) : ?external_backup_service_interface {
        if(empty($service)){
            $service = self::get(self::CONFIG_EXTERNAL_BACKUP_SERVICE, null);
        }
        
        $services = self::get_external_backup_services();
        if(empty($services[$service])){
            return null;
        }

        $class = $services[$service];
        return new $class();
    }
}
<?php namespace tool_stdlogarchiver\backup\external;

use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\util\compression_helper;

class external_gcs_backup_service implements external_backup_service_interface {

    const CONFIG_GCS_CREDENTIALS = 'gcs_credentials';
    const CONFIG_GCS_BUCKET      = 'gcs_bucket';
    const CONFIG_GCS_FOLDER      = 'gcs_folder';

    public static function get_name(): string {
        return 'gcs';
    }

    public static function is_enabled(): bool {
        if (self::get_name() !== config::get(config::CONFIG_EXTERNAL_BACKUP_SERVICE)) {
            return false;
        }
        return !empty(config::get(self::CONFIG_GCS_CREDENTIALS))
            && !empty(config::get(self::CONFIG_GCS_BUCKET));
    }

    public static function define_settings(): array {
        return [
            new \admin_setting_heading(
                'tool_stdlogarchiver/gcs_header',
                new \lang_string('settings:gcs_header', 'tool_stdlogarchiver'),
                ''
            ),
            new \admin_setting_configtextarea(
                'tool_stdlogarchiver/' . self::CONFIG_GCS_CREDENTIALS,
                new \lang_string('settings:gcs_credentials', 'tool_stdlogarchiver'),
                new \lang_string('settings:gcs_credentials_desc', 'tool_stdlogarchiver'),
                '',
                PARAM_RAW,
                60,
                10
            ),
            new \admin_setting_configtext(
                'tool_stdlogarchiver/' . self::CONFIG_GCS_BUCKET,
                new \lang_string('settings:gcs_bucket', 'tool_stdlogarchiver'),
                '',
                '',
                PARAM_TEXT
            ),
            new \admin_setting_configtext(
                'tool_stdlogarchiver/' . self::CONFIG_GCS_FOLDER,
                new \lang_string('settings:gcs_folder', 'tool_stdlogarchiver'),
                new \lang_string('settings:gcs_folder_desc', 'tool_stdlogarchiver'),
                config::generate_external_folder_name(),
                PARAM_TEXT
            ),
        ];
    }

    public function exists(string $external_uri): bool {
        try {
            $this->load_sdk();
            ['bucket' => $bucket, 'key' => $key] = $this->parse_uri($external_uri);
            return $this->get_client()->bucket($bucket)->object($key)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function upload(backup $backup): string {
        $this->load_sdk();

        $local_path = $backup->get_local_file_path();
        if (!$local_path || !file_exists($local_path)) {
            throw new \moodle_exception('localfilenotfound', 'tool_stdlogarchiver');
        }

        $format   = $backup->get('fileformat');
        $folder   = config::get(self::CONFIG_GCS_FOLDER, config::generate_external_folder_name());
        $bucket   = config::get(self::CONFIG_GCS_BUCKET, '');
        $base_key = $folder . '/' . $backup->get_filename();

        if ($format === config::BACKUP_FORMAT_DB) {
            $gz_tmp        = sys_get_temp_dir() . '/' . $backup->get_filename() . '.gz.tmp';
            compression_helper::gzip($local_path, $gz_tmp);
            $gcs_key       = $base_key . '.gz';
            $upload_source = $gz_tmp;
        } else {
            $gcs_key       = $base_key;
            $upload_source = $local_path;
            $gz_tmp        = null;
        }

        try {
            $handle = fopen($upload_source, 'r');
            $this->get_client()->bucket($bucket)->upload($handle, ['name' => $gcs_key]);
        } finally {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
            if ($gz_tmp && file_exists($gz_tmp)) {
                @unlink($gz_tmp);
            }
        }

        return 'gs://' . $bucket . '/' . $gcs_key;
    }

    public function download_to_path(string $external_uri, string $dest_path): void {
        $this->load_sdk();
        ['bucket' => $bucket, 'key' => $key] = $this->parse_uri($external_uri);
        $this->get_client()->bucket($bucket)->object($key)->downloadToFile($dest_path);
    }

    public function delete(string $external_uri): void {
        $this->load_sdk();
        ['bucket' => $bucket, 'key' => $key] = $this->parse_uri($external_uri);
        $this->get_client()->bucket($bucket)->object($key)->delete();
    }

    protected function load_sdk(): void {
        if (class_exists(\Google\Cloud\Storage\StorageClient::class)) {
            return;
        }
        require_once($this->get_autoload_path());
    }

    protected function get_autoload_path(): string {
        global $CFG;
        return $CFG->dirroot . '/admin/tool/stdlogarchiver/libs/autoload.php';
    }

    protected function get_client(): object {
        $credentials = json_decode(config::get(self::CONFIG_GCS_CREDENTIALS, ''), true);
        return new \Google\Cloud\Storage\StorageClient(['keyFile' => $credentials]);
    }

    protected function parse_uri(string $uri): array {
        $parts = parse_url($uri);
        return [
            'bucket' => $parts['host'],
            'key'    => ltrim($parts['path'], '/'),
        ];
    }
}

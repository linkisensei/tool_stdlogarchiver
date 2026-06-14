<?php namespace tool_stdlogarchiver\backup\external;

use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\util\compression_helper;

class external_s3_backup_service implements external_backup_service_interface {

    const CONFIG_AWS_REGION = 'aws_region';
    const CONFIG_AWS_KEY    = 'aws_key';
    const CONFIG_AWS_SECRET = 'aws_secret';
    const CONFIG_S3_BUCKET  = 's3_bucket';
    const CONFIG_S3_FOLDER  = 's3_folder';

    public static function is_enabled(): bool {
        if (self::get_name() !== config::get(config::CONFIG_EXTERNAL_BACKUP_SERVICE)) {
            return false;
        }
        if (empty(config::get(self::CONFIG_AWS_KEY)) || empty(config::get(self::CONFIG_AWS_SECRET))) {
            return false;
        }
        return !empty(config::get(self::CONFIG_S3_BUCKET));
    }

    public static function get_name(): string {
        return 's3';
    }

    public static function define_settings(): array {
        return [
            new \admin_setting_heading(
                'tool_stdlogarchiver/aws_header',
                new \lang_string('settings:aws_s3_header', 'tool_stdlogarchiver'),
                ''
            ),
            new \admin_setting_configtext(
                'tool_stdlogarchiver/' . self::CONFIG_AWS_REGION,
                new \lang_string('settings:aws_region', 'tool_stdlogarchiver'),
                new \lang_string('settings:aws_region_desc', 'tool_stdlogarchiver'),
                '',
                PARAM_TEXT
            ),
            new \admin_setting_configtext(
                'tool_stdlogarchiver/' . self::CONFIG_AWS_KEY,
                new \lang_string('settings:aws_key', 'tool_stdlogarchiver'),
                '',
                '',
                PARAM_TEXT
            ),
            new \admin_setting_configpasswordunmask(
                'tool_stdlogarchiver/' . self::CONFIG_AWS_SECRET,
                new \lang_string('settings:aws_secret', 'tool_stdlogarchiver'),
                '',
                ''
            ),
            new \admin_setting_configtext(
                'tool_stdlogarchiver/' . self::CONFIG_S3_BUCKET,
                new \lang_string('settings:s3_bucket', 'tool_stdlogarchiver'),
                '',
                '',
                PARAM_TEXT
            ),
            new \admin_setting_configtext(
                'tool_stdlogarchiver/' . self::CONFIG_S3_FOLDER,
                new \lang_string('settings:s3_folder', 'tool_stdlogarchiver'),
                new \lang_string('settings:s3_folder_desc', 'tool_stdlogarchiver'),
                config::generate_external_folder_name(),
                PARAM_TEXT
            ),
        ];
    }

    public function exists(string $external_uri): bool {
        try {
            $this->load_sdk();
            ['bucket' => $bucket, 'key' => $key] = $this->parse_uri($external_uri);

            return $this->remote_object_exists($bucket, $key);
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
        $folder   = config::get(self::CONFIG_S3_FOLDER, config::generate_external_folder_name());
        $bucket   = config::get(self::CONFIG_S3_BUCKET, '');
        $base_key = $folder . '/' . $backup->get_filename();

        if ($format === config::BACKUP_FORMAT_DB) {
            $gz_tmp        = sys_get_temp_dir() . '/' . $backup->get_filename() . '.gz.tmp';
            compression_helper::gzip($local_path, $gz_tmp);
            $s3_key        = $base_key . '.gz';
            $upload_source = $gz_tmp;
        } else {
            $s3_key        = $base_key;
            $upload_source = $local_path;
            $gz_tmp        = null;
        }

        try {
            $s3 = $this->get_client();
            $s3->putObject([
                'Bucket'     => $bucket,
                'Key'        => $s3_key,
                'SourceFile' => $upload_source,
            ]);
        } finally {
            if ($gz_tmp && file_exists($gz_tmp)) {
                @unlink($gz_tmp);
            }
        }

        return 's3://' . $bucket . '/' . $s3_key;
    }

    public function download_to_path(string $external_uri, string $dest_path): void {
        $this->load_sdk();

        ['bucket' => $bucket, 'key' => $key] = $this->parse_uri($external_uri);

        $s3 = $this->get_client();
        $s3->getObject([
            'Bucket' => $bucket,
            'Key'    => $key,
            'SaveAs' => $dest_path,
        ]);
    }

    public function delete(string $external_uri): void {
        $this->load_sdk();

        ['bucket' => $bucket, 'key' => $key] = $this->parse_uri($external_uri);

        $s3 = $this->get_client();
        $s3->deleteObject([
            'Bucket' => $bucket,
            'Key'    => $key,
        ]);
    }

    protected function load_sdk(): void {
        if (class_exists(\Aws\S3\S3Client::class)) {
            return;
        }

        require_once($this->get_autoload_path());
    }

    protected function get_autoload_path(): string {
        global $CFG;
        return $CFG->dirroot . '/admin/tool/stdlogarchiver/libs/autoload.php';
    }

    protected function get_client(): \Aws\S3\S3Client {
        return new \Aws\S3\S3Client([
            'region'      => config::get(self::CONFIG_AWS_REGION, ''),
            'version'     => 'latest',
            'credentials' => [
                'key'    => config::get(self::CONFIG_AWS_KEY, ''),
                'secret' => config::get(self::CONFIG_AWS_SECRET, ''),
            ],
        ]);
    }

    protected function remote_object_exists(string $bucket, string $key): bool {
        $s3 = $this->get_client();

        if (method_exists($s3, 'doesObjectExistV2')) {
            return (bool) $s3->doesObjectExistV2($bucket, $key);
        }

        try {
            $s3->headObject([
                'Bucket' => $bucket,
                'Key'    => $key,
            ]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function parse_uri(string $uri): array {
        $parts = parse_url($uri);
        return [
            'bucket' => $parts['host'],
            'key'    => ltrim($parts['path'], '/'),
        ];
    }
}

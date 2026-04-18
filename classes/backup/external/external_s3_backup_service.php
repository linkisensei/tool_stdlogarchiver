<?php namespace tool_stdlogarchiver\backup\external;

use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\util\compression_helper;

class external_s3_backup_service implements external_backup_service_interface {

    public static function is_enabled(): bool {
        if (self::get_name() !== config::get(config::CONFIG_EXTERNAL_BACKUP_SERVICE)) {
            return false;
        }
        $creds = config::get_aws_credentials();
        if (empty($creds['key']) || empty($creds['secret'])) {
            return false;
        }
        return !empty(config::get_s3_bucket()) && !empty(config::get_s3_folder());
    }

    public static function get_name(): string {
        return 's3';
    }

    public function exists(string $external_uri): bool {
        try {
            $this->load_sdk();
            ['bucket' => $bucket, 'key' => $key] = $this->parse_uri($external_uri);

            return $this->remote_object_exists($bucket, $key);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function upload(backup $backup): string {
        $this->load_sdk();

        $local_path = $backup->get_local_file_path();
        if (!$local_path || !file_exists($local_path)) {
            throw new \moodle_exception('localfilenotfound', 'tool_stdlogarchiver');
        }

        $format = $backup->get('fileformat');
        $base_key = config::get_s3_folder() . '/' . $backup->get_filename();

        if ($format === config::BACKUP_FORMAT_DB) {
            $gz_tmp = sys_get_temp_dir() . '/' . $backup->get_filename() . '.gz.tmp';
            compression_helper::gzip($local_path, $gz_tmp);
            $s3_key = $base_key . '.gz';
            $upload_source = $gz_tmp;
        } else {
            $s3_key = $base_key;
            $upload_source = $local_path;
            $gz_tmp = null;
        }

        try {
            $s3 = $this->get_client();
            $s3->putObject([
                'Bucket'     => config::get_s3_bucket(),
                'Key'        => $s3_key,
                'SourceFile' => $upload_source,
            ]);
        } finally {
            if ($gz_tmp && file_exists($gz_tmp)) {
                @unlink($gz_tmp);
            }
        }

        return 's3://' . config::get_s3_bucket() . '/' . $s3_key;
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
            'region'      => config::get_aws_region(),
            'version'     => 'latest',
            'credentials' => config::get_aws_credentials(),
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
        } catch (\Throwable $e) {
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

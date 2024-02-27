<?php namespace tool_stdlogarchiver\backup\external;

use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\models\external\external_backup;
use \tool_stdlogarchiver\backup\external\external_backup_service_interface;

class external_s3_backup_service implements external_backup_service_interface{

    protected $client_config;

    public function __construct(){
        $this->client_config = [
            'region'      => config::get_aws_region(),
            'version'     => 'latest',
            'credentials' => config::get_aws_credentials(),
        ];
    }

    public static function is_enabled() : bool {
        if(self::get_name() != config::get(config::CONFIG_EXTERNAL_BACKUP_SERVICE)){
            return false;
        }

        if(!config::get(config::CONFIG_AWS_KEY) || !config::get(config::CONFIG_AWS_SECRET)){
            return false;
        }

        if(!config::get_s3_bucket() || !config::get_s3_folder()){
            return false;
        }

        return true;
    }

    public static function get_name() : string {
        return 's3';
    }

    public function upload(backup $backup) : ?external_backup {
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/stdlogarchiver/libs/autoload.php');

        $s3 = new Aws\S3\S3Client($this->client_config);

        try {
            $instance = external_backup::create_from_backup($backup);
            $key = self::generate_key($backup);

            $result = $s3->putObject([
                'Bucket'     => config::get_s3_bucket(),
                'Key'        => $key,
                'SourceFile' => $backup->get_file_local_path(),
            ]);

            $instance->set('service', self::get_name());
            $instance->set('externalpath', $key);
            return $instance;

        } catch (\Throwable $th) {
            debugging($th->getMessage(), DEBUG_DEVELOPER, $th->getTrace());
            return null;
        }
    }

    public function download(external_backup $external_backup) : ?backup {
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/stdlogarchiver/libs/autoload.php');

        $backup = $external_backup->get_backup();

        if($backup->get_file()){
            throw new moodle_exception('exception:cannot_download_local_file_exists', 'tool_stdlogarchiver');
        }

        $s3 = new Aws\S3\S3Client($this->client_config);
        $temp_directory = make_temp_directory('standardlogcleaner');
        $temp_file = tempnam($temp_directory, 'external');

        try {
            $result = $s3->getObject([
                'Bucket'     => config::get_s3_bucket(),
                'Key'        => $external_backup->get('externalpath'),
                'SaveAs'     => $temp_file,
            ]);

            $filename = explode('/', $external_backup->get('externalpath'));
            $filename = end($filename);
            $file_record = backup::generate_backup_file_record_data($filename);

            $file_storage = get_file_storage();
            $file = $file_storage->create_file_from_pathname($file_record, $temp_file);

            $backup->set_stored_file($file);
            $backup->save();

            @unlink($temp_file);
            return $backup;
        } catch (\Exception $e) {
            debugging($e->getMessage(), DEBUG_DEVELOPER, $e->getTrace());

            if(file_exists($temp_file)){
                @unlink($temp_file);
            }
            return null;
        }

    }

    protected static function generate_key(backup $backup) : string {
        $folder = config::get_s3_folder();
        $starttime = $backup->get('starttime');
        $endtime = $backup->get('endtime');
        $filename = $backup->get_file()->get_filename();
        return $folder . '/' . $starttime . '_' . $endtime . '_' . $filename;
    }
}
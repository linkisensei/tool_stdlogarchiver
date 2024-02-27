<?php namespace tool_stdlogarchiver\backup\writers;

use \SplFileObject;
use \stored_file;
use \Exception;
use \context_system;
use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\util\standard_logstore;
use \tool_stdlogarchiver\models\backup;

class csv_writer implements writer_interface{
    const SEPARATOR = ",";

    protected $filename;
    protected $temp_filepath;
    protected $headers = [];
    protected $file;

    public function __construct(){
        $this->filename = $this->generate_filename();

        $temp_directory = make_temp_directory('standardlogcleaner');
        $this->temp_filepath = tempnam($temp_directory, $this->filename);

        $this->headers = standard_logstore::instance()->get_logstore_columns();
        $this->write_to_file($this->headers);
    }

    public function destroy(){
        $this->close_spl_file_object();
        $this->delete_temp_file();
    }

    public function __destruct(){
        $this->destroy();
    }

    public function get_format_name() : string {
        return config::BACKUP_FORMAT_CSV;
    }

    public function append(object $row){
        $row = $this->format_row($row);
        $this->write_to_file($row);
    }
    
    public function to_stored_file() : stored_file {
        try {
            $this->close_spl_file_object();

            $file_storage = get_file_storage();

            $file_record = backup::generate_backup_file_record_data($this->filename);

            $file = $file_storage->create_file_from_string($file_record, file_get_contents($this->temp_filepath));
            $this->delete_temp_file();
            return $file;

        } catch (Exception $e) {
            $this->delete_temp_file();
            throw $e;
        }
    }

    protected function format_row(object $row) : array {
        $formated_row = [];
        foreach($this->headers as $index => $header){
            $formated_row[$index] = isset($row->$header) ? $row->$header : null;
        }
        return $formated_row;
    }

    protected function write_to_file($row){
        $file = $this->get_spl_file_object();
        $file->fputcsv($row, self::SEPARATOR);
    }

    protected function generate_filename() : string {
        return \core\uuid::generate() . '.csv';
    }

    protected function &get_spl_file_object() : SplFileObject {
        if(empty($this->file)){
            $this->file = new SplFileObject($this->temp_filepath, 'w');
        }
        return $this->file;
    }

    protected function close_spl_file_object(){
        unset($this->file);
    }

    protected function delete_temp_file(){
        if($this->temp_filepath && file_exists($this->temp_filepath)){
            @unlink($this->temp_filepath);
        }
    }


}
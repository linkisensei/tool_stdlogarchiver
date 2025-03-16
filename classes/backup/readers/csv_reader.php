<?php namespace tool_stdlogarchiver\backup\readers;

use \stored_file;
use \Generator;
use \SplFileObject;

class csv_reader implements reader_interface{
    protected $file;

    protected function __construct(stored_file $file){
        $this->file = $file;
    }

    public static function create(stored_file $file) : reader_interface {
        return new static($file);
    }

    /**
     * @return object[]
     */
    public function get_contents() : array {
        $file_handle = $this->file->get_content_file_handle();

        if($file_handle === false){
            return [];
        }

        $contents = [];
        $headers = fgetcsv($file_handle);

        while(($line = fgetcsv($file_handle)) !== false){
            $contents[] = (object) array_combine($headers, $line);
        }

        fclose($file_handle);
        return $contents;
    }

    /**
     * @return Generator<object>
     */
    public function get_contents_generator() : Generator {
        $file_handle = $this->file->get_content_file_handle();

        $headers = fgetcsv($file_handle);

        while(($line = fgetcsv($file_handle)) !== false){
            yield (object) array_combine($headers, $line);
        }
        fclose($file_handle);
    }

}
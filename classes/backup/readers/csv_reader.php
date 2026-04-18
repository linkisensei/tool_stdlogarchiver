<?php namespace tool_stdlogarchiver\backup\readers;

use \Generator;

class csv_reader implements reader_interface {

    private string $filepath;

    public function __construct(string $filepath) {
        $this->filepath = $filepath;
    }

    public static function create(string $filepath): reader_interface {
        return new static($filepath);
    }

    public function get_contents(): array {
        $handle  = fopen($this->filepath, 'rb');
        $headers = fgetcsv($handle);
        $rows    = [];
        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = (object) array_combine($headers, $line);
        }
        fclose($handle);
        return $rows;
    }

    public function get_contents_generator(): Generator {
        $handle  = fopen($this->filepath, 'rb');
        $headers = fgetcsv($handle);
        while (($line = fgetcsv($handle)) !== false) {
            yield (object) array_combine($headers, $line);
        }
        fclose($handle);
    }
}

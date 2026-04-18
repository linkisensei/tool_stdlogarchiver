<?php namespace tool_stdlogarchiver\backup\writers;

use \SplFileObject;
use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\util\standard_logstore;

class csv_writer implements writer_interface {

    private const SEPARATOR = ',';

    private string $filepath;
    private array $headers;
    private ?SplFileObject $file = null;

    public function __construct(string $filepath) {
        $this->filepath = $filepath;
        $this->headers  = standard_logstore::instance()->get_logstore_columns();
        $this->write_row($this->headers);
    }

    public function append(object $row): void {
        $this->write_row($this->format_row($row));
    }

    public function finalize(): void {
        $this->close_handle();
    }

    public function destroy(): void {
        $this->close_handle();
        if (file_exists($this->filepath)) {
            @unlink($this->filepath);
        }
    }

    public function get_format_name(): string {
        return config::BACKUP_FORMAT_CSV;
    }

    private function format_row(object $row): array {
        $out = [];
        foreach ($this->headers as $i => $col) {
            $out[$i] = $row->$col ?? null;
        }
        return $out;
    }

    private function write_row(array $row): void {
        $this->get_handle()->fputcsv($row, self::SEPARATOR);
    }

    private function get_handle(): SplFileObject {
        if ($this->file === null) {
            $this->file = new SplFileObject($this->filepath, 'w');
        }
        return $this->file;
    }

    private function close_handle(): void {
        $this->file = null; // SplFileObject closes on unset.
    }

    public function __destruct() {
        $this->close_handle();
    }
}

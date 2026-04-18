<?php namespace tool_stdlogarchiver\backup\readers;

use \Generator;

interface reader_interface {

    public static function create(string $filepath): reader_interface;

    /** @return object[] */
    public function get_contents(): array;

    /** @return Generator<object> */
    public function get_contents_generator(): Generator;
}

<?php namespace tool_stdlogarchiver\backup\readers;

use \stored_file;
use \Generator;

interface reader_interface{
    
    public static function create(stored_file $file) : reader_interface;

    public function get_contents() : array;

    public function get_contents_generator() : Generator;
}
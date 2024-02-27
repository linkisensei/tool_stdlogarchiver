<?php namespace tool_stdlogarchiver\backup\writers;

use \stored_file;

interface writer_interface{

    public function get_format_name() : string;

    public function append(object $row);
    
    public function to_stored_file() : stored_file;

    public function destroy();
}
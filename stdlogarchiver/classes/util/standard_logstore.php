<?php namespace tool_stdlogarchiver\util;

use \core_plugin_manager;
use \xmldb_file;
use \xmldb_table;
use \moodle_exception;

class standard_logstore{

    private $reader;
    private $columns = [];
    protected $component;
    protected static $uses_json = null;

    private static $instance;

    public function __construct(){
        $manager = get_log_manager();        
        foreach ($manager->get_readers() as $component => $reader) {
            if(is_a($reader, \logstore_standard\log\store::class)){
                $this->reader = $reader;
                $this->component = $component;
            }
        }
    }

    public function get_logstore_table() : string {
        return $this->reader->get_internal_log_table_name();
    }

    public function get_logstore_columns() : array {
        if(empty($this->columns)){
            $this->columns = $this->extract_columns_from_db();
        }
        return $this->columns;
    }

    /**
     * Extracts an ordered field list from the moodle database.
     * The order of the columns is not garanteed, but its faster
     *
     * @return array
     */
    protected function extract_columns_from_db() : array {
        global $DB;
        return array_keys($DB->get_columns($this->get_logstore_table(), true));
    }

    /**
     * Extracts an ordered field list from the plugin's install.xml
     *
     * @return array
     */
    protected function extract_columns_from_xmldb() : array {
        $plugin_info = core_plugin_manager::instance()->get_plugin_info($this->component);
        $xmldb_install_filepath = "$plugin_info->rootdir/db/install.xml";
        if(!file_exists($xmldb_install_filepath)){
            return [];
        }

        $xmldb_file = new xmldb_file($xmldb_install_filepath);
        if (!$xmldb_file->loadXMLStructure()) {
            return [];
        }
        
        $table_name = $this->get_logstore_table();
        $xmldb_table = null;
        foreach ($xmldb_file->getStructure()->getTables() as $table) {
            if($table->getName() == $table_name){
                $xmldb_table = $table;
                break;
            }
        }

        $columns = [];
        foreach ($xmldb_table->getFields() as $field) {
            $columns[] = $field->getName();
        }

        return $columns;
    }

    public static function instance() : standard_logstore {
        if(empty(self::$instance)){
            self::$instance = new static();
        }
        return self::$instance;
    }

    public static function uses_json_for_others_column() : bool {
        if(self::$uses_json === null){
            self::$uses_json = (bool) get_config('logstore_standard', 'jsonformat');
        }
        return self::$uses_json;
    }
}
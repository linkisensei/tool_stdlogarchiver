<?php namespace tool_stdlogarchiver\restore;

use \Generator;
use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\util\logstored_other_trait;
use \tool_stdlogarchiver\util\standard_logstore;


class backup_restorer{
    
    use logstored_other_trait;

    protected $backup;
    protected $other_as_json = false;

    public function __construct(backup $backup){
        raise_memory_limit(MEMORY_EXTRA);
        \core_php_time_limit::raise();

        $this->backup = $backup;
        $this->table = standard_logstore::instance()->get_logstore_table();
        $this->other_as_json = standard_logstore::uses_json_for_others_column();
    }

    public function execute(){
        $reader = $this->backup->get_reader();

        /** @var Generator<object> */
        foreach ($reader->get_contents_generator() as $record) {
            $record = $this->format_record($record);
            $this->import_record($record);
        }
    }

    /**
     * Imports a record at time, but its
     * the only way to import the record
     * with the same ID without directly
     * executing SQL or hacking the
     * moodle_database
     *
     * @param object $record
     * @return void
     */
    protected function import_record(object $record){
        global $DB;
        $DB->import_record($this->table, $this->format_record($record));
    }

    /**
     * It is a good aproximation, since
     * the original record came from the
     * same table.
     * 
     * It makes sure the "others" property is
     * encoded accordingly with logstore_standard/jsonformat
     *
     * @param object $record
     * @return object
     */
    protected function format_record(object $record) : object {
        foreach (get_object_vars($record) as $key => $value) {
            if(is_numeric($value)){
                $record->$key = intval($value);
                continue;
            }

            if(empty($value)){
                unset($record->$key);
                continue;
            }
        }

        if(!$this->other_as_json && !empty($record->other)){
            $record->other = self::to_serialized($record->other);
        }

        return $record;
    }
}
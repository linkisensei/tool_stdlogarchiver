<?php namespace tool_stdlogarchiver\models\external;

use \core\persistent;
use \tool_stdlogarchiver\models\backup;
use \invalid_parameter_exception;
use \tool_stdlogarchiver\util\persistent_soft_delete_trait;

class external_backup extends persistent{

    use persistent_soft_delete_trait;

    const TABLE = 'tool_stdlogarchiver_ext_bkps';

    /** @var array The model data. */
    private $data = array();

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return array(
            'backupid' => [
                'type' => PARAM_INT,
            ],
            'service' => [
                'type' => PARAM_TEXT,
            ],
            'externalpath' => [
                'type' => PARAM_RAW,
                'null'    => NULL_ALLOWED,
            ],
            'customdata' => [
                'type' => PARAM_RAW,
                'default' => '{}',
            ],
            'deleted' => [
                'type' => PARAM_BOOL,
                'default' => false
            ],
        );
    }

    public function get_backup() : backup {
        return new backup($this->get('backupid'));
    }

    protected function set_customdata($data){
        if(is_array($data) || is_object($data)){
            $data = (object) $data;
        }else{
            throw new invalid_parameter_exception('$data must be an array or object');
        }
        $customdata = $this->raw_set('customdata', $data);
        return json_decode($customdata, false);
    }

    protected function get_customdata(){
        $customdata = $this->raw_get('customdata') ?: "{}";
        return json_decode($customdata, false);
    }
    
    public static function create_from_backup(backup $backup) : external_backup {
        $instance = new static(0, (object)[
            'backupid' => $backup->get('id'),
        ]);

        return $instance;
    }

    public static function list_from_backup(backup $backup) : array {
        return self::get_records(['backupid' => $backup->get('id')]);
    }

}
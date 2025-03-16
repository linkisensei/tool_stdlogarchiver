<?php namespace tool_stdlogarchiver\models;

use \moodle_url;
use \coding_exception;
use \core\persistent;
use \stored_file;
use \context_system;
use \invalid_parameter_exception;
use \tool_stdlogarchiver\backup\readers\reader_interface;
use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\util\persistent_soft_delete_trait;

use \tool_stdlogarchiver\util\standard_logstore;
use \tool_stdlogarchiver\task\restore_backup_task;
use \tool_stdlogarchiver\models\external\external_backup;

class backup extends persistent{

    use persistent_soft_delete_trait;

    const TABLE = 'tool_stdlogarchiver_backups';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return array(
            'firstid' => [
                'type' => PARAM_INT,
            ],
            'lastid' => [
                'type' => PARAM_INT,
            ],
            'starttime' => [
                'type' => PARAM_INT,
            ],
            'endtime' => [
                'type' => PARAM_INT,
            ],
            'fileformat' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null
            ],
            'pathnamehash' => [
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null
            ],
            'restored' => [
                'type' => PARAM_BOOL,
                'default' => false
            ],
            'deleted' => [
                'type' => PARAM_BOOL,
                'default' => false
            ],
        );
    }

    public function is_deleted() : bool {
        return (bool) $this->get('deleted');
    }

    /**
     * If the backup was restored to the
     * standard logstore
     *
     * @return boolean
     */
    public function was_restored() : bool {
        return (bool) $this->get('restored');
    }

    /**
     * If there is already an adhoc task enqueued
     *
     * @return boolean
     */
    public function is_restoring() : bool {
        if($this->was_restored()){
            return false;
        }

        return restore_backup_task::is_enqueued($this->get('id'));
    }

    /**
     * Create an adhoc task to restore this backup
     *
     * @return void
     */
    public function create_restore_task() : bool {
        if(!$this->get_file()){
            return false;
        }
        restore_backup_task::create_and_enqueue($this->get('id'));
        return true;
    }


    /**
     * Returns the issued certificates file
     *
     * @return stored_file|null
     */
    public function get_file() : ?stored_file {
        $fs = get_file_storage();
        $pathname_hash = $this->raw_get('pathnamehash');
        return $fs->get_file_by_hash($pathname_hash) ?: null;
    }


    /**
     * Deletes the local file if exists
     *
     * @return bool
     */
    public function delete_file() : bool {
        if($file = $this->get_file()){
            $file->delete();
            $this->raw_set('pathnamehash', null);
            $this->raw_set('fileformat', null);
            $this->save();
        }
        return true;
    }

    /**
     * Returns the real path of the local
     * backup file
     *
     * @return string|null
     */
    public function get_file_local_path() : ?string {
        if($file = $this->get_file()){
            $storage = get_file_storage();
            $fs = $storage->get_file_system();
            return $fs->get_local_path_from_storedfile($file);
        }

        return null;
    }


    public static function create_from($data, ?stored_file $file = null) : backup {
        if(!is_array($data) && !is_object($data)){
            throw new invalid_parameter_exception('$data must be an array or object');
        }

        $instance = new static(0, (object) $data);

        if($file){
            $instance->set_stored_file($file);
        }
        
        return $instance;
    }

    public function set_stored_file(stored_file $file){
        $this->set('pathnamehash', $file->get_pathnamehash());
        if(preg_match('/\.([^.]+)$/', $file->get_filename(), $matches)){
            $this->set('fileformat', mb_strtolower($matches[1]));
        }
    }

    /**
     * Returns an instance of the appropriate
     * file reader
     *
     * @return reader_interface
     */
    public function get_reader() : reader_interface {
        $format = $this->raw_get('fileformat');
        $reader_class = config::get_reader_class($format);
        return $reader_class::create($this->get_file());
    }

    /**
     * Restores the backup to the standard logstore
     *
     * @return boolean
     */
    public function restore() : bool {
        $restorer = new \tool_stdlogarchiver\restore\backup_restorer($this);
        $restorer->execute();
        $this->raw_set('restored', true);
        $this->save();
        return true;
    }

    /**
     * Deletes previously restored records
     * from the standard logstore
     *
     * @return boolean
     */
    public function undo_restore() : bool {
        global $DB;

        if(!$this->get('pathnamehash') || !$this->get('restored')){
            return false;
        }

        try {
            $DB->delete_records_select(
                static::TABLE,
                "id BETWEEN :firstid AND :lastid",
                (array) $this->to_record()
            );

            $this->raw_set('restored', false);
            $this->save();
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }


    /**
     * Returns the instance of the last backup record
     *
     * @return static|null
     */
    public static function get_last_backup() : ?static {
        if($records = self::get_records([], 'id', 'DESC', 0, 1)){
            return array_pop($records);
        }
        return null;
    }

    /**
     * Returns the instance of the first backup record
     *
     * @return static|null
     */
    public static function get_first_backup() : ?static {
        if($records = self::get_records([], 'id', 'ASC', 0, 1)){
            return array_pop($records);
        }
        return null;
    }


    protected function before_soft_delete() {
        $this->delete_file();
    }

    /**
     * Returns an array containing a basic record
     * for a stored_file related to this backup.
     *
     * @param string $filename
     * @return array
     */
    public static function generate_backup_file_record_data(string $filename) : array {
        return [
            'component' => 'tool_stdlogarchiver',
            'filearea' => config::BACKUPS_FILEAREA,
            'contextid' => (context_system::instance())->id,
            'itemid' => time(),
            'filename' => $filename,
            'filepath' => '/',
        ];
    }

    /**
     * Returns the instance of the external backup,
     * if exists.
     *
     * @return external_backup|null
     */
    public function get_external_backup() : ?external_backup {
        return external_backup::get_record(['backupid' => $this->get('id')]) ?: null;
    }


    public function get_file_moodle_url(?stored_file $file = null) : ?moodle_url {
        if(!$file){
            return null;
        }

        $url = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            false
        ) ?: null;

        return $url;
    }

    public function get_download_url() : ?moodle_url {
        return $this->get_file_moodle_url($this->get_file());
    }

    /**
     * Short hash of a backup id
     *
     * @param integer $id
     * @return string
     */
    public static function hash_id(int $id) : string {
        return substr(md5($id, true), 0, 12);
    }

    /**
     * Short hash of the ID with
     * no cryptographic purposes,
     * just for display
     *
     * @return string
     */
    public function get_hash_id() : string {
        return self::hash_id((int)$this->get('id'));
    }

    public function __toString() : string {
        $id = $this->get('id');
        $firstid = $this->get('firstid') ?: '?';
        $lastid = $this->get('lastid') ?: '?';
        return "Backup #$id ($firstid:$lastid)";
    }
}
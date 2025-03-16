<?php namespace tool_stdlogarchiver\backup\external;

use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\models\external\external_backup;

interface external_backup_service_interface{

    /**
     * If the service is enabled
     *
     * @return boolean
     */
    public static function is_enabled() : bool;

    /**
     * Return the service identifier
     *
     * @return string
     */
    public static function get_name() : string;

    /**
     * Uploads the backup file to the external service.
     * 
     * @param backup|null $backup
     * @return backup|null
     */
    public function upload(backup $backup) : ?external_backup;


    /**
     * Downloads a external backupt file to the moodle
     * file system and updates the backup record.
     *
     * @param external_backup $external_backup
     * @return backup|null
     */
    public function download(external_backup $external_backup) : ?backup;


    /**
     * Deletes the external backup from the external service.
     *
     * @param external_backup $external_backup
     * @return boolean
     */
    // public function delete(external_backup $external_backup) : bool;

}
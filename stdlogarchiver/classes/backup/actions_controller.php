<?php namespace tool_stdlogarchiver\backup;

use \tool_stdlogarchiver\models\backup;
use \moodle_exception;
use \moodle_url;
use \context_system;

/**
 * @todo Mudar as notificações para redirects
 */
class actions_controller{

    protected $url;

    protected static function get_redirect_url() : moodle_url {
        return new moodle_url('/admin/tool/stdlogarchiver/index.php');
    }

    public static function delete_backup(int $backupid){
        require_sesskey();
        require_capability('tool/stdlogarchiver:delete', context_system::instance());

        try {
            $backup = new backup($backupid);

            if($backup->is_deleted()){
                throw new moodle_exception('exception:backup_deleted', 'tool_stdlogarchiver');
            }

            $backup->delete();

            redirect(
                self::get_redirect_url(),
                get_string('delete_action_success', 'tool_stdlogarchiver'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );

        } catch (\Throwable $th) {
            redirect(
                self::get_redirect_url(),
                $th->getMessage(),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }

    public static function restore_backup(int $backupid){
        require_sesskey();
        require_capability('tool/stdlogarchiver:restore', context_system::instance());

        try {
            $backup = new backup($backupid);

            if(!$backup){
                return;
            }

            if($backup->is_deleted()){
                throw new moodle_exception('exception:backup_deleted', 'tool_stdlogarchiver');
            }
    
            if($backup->is_restoring()){
                throw new moodle_exception('exception:already_restoring', 'tool_stdlogarchiver');
            }
    
            if($backup->was_restored()){
                throw new moodle_exception('exception:already_restored', 'tool_stdlogarchiver');
            }

            \tool_stdlogarchiver\task\restore_backup_task::create_and_enqueue($backupid);

            redirect(
                self::get_redirect_url(),
                get_string('restore_action_success', 'tool_stdlogarchiver'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );

        } catch (\Throwable $th) {
            redirect(
                self::get_redirect_url(),
                $th->getMessage(),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }

    public static function unrestore_backup(int $backupid){
        require_sesskey();
        require_capability('tool/stdlogarchiver:restore', context_system::instance());

        try {
            $backup = new backup($backupid);

            if(!$backup){
                return;
            }

            if($backup->is_restoring()){
                throw new moodle_exception('exception:already_restoring', 'tool_stdlogarchiver');
            }

            if(!$backup->was_restored()){
                throw new moodle_exception('exception:not_yet_restored', 'tool_stdlogarchiver');
            }

            \tool_stdlogarchiver\task\unrestore_backup_task::create_and_enqueue($backupid);

            redirect(
                self::get_redirect_url(),
                get_string('unrestore_action_success', 'tool_stdlogarchiver'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );

        } catch (\Throwable $th) {
            redirect(
                self::get_redirect_url(),
                $th->getMessage(),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }


    public static function route(){
        $backupid = optional_param('backupid', 0, PARAM_INT);
        $action = optional_param('action', null, PARAM_ACTION);

        if(!$backupid){
            return;
        }

        switch ($action) {
            case 'delete':
                self::delete_backup($backupid);
                break;
            case 'restore':
                self::restore_backup($backupid);
                break;
            case 'unrestore':
                self::unrestore_backup($backupid);
                break;
        }
    }
}
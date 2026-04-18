<?php namespace tool_stdlogarchiver\task;

use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;

defined('MOODLE_INTERNAL') || die();

/**
 * Permanently deletes backup files (local + external) for:
 *   1. Backups soft-deleted by the user (deleted_at > 0)
 *   2. Backups older than backup_retention_ttl (auto-purge, if TTL is configured)
 *
 * After physical deletion, local_path and external_service/uri are nullified.
 * The DB record is preserved as audit history.
 */
class backup_purge_task extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task:backup_purge_task_name', 'tool_stdlogarchiver');
    }

    public function execute(): void {
        global $DB;

        // Auto-purge: backups older than retention TTL that have not been soft-deleted yet.
        $retention_ttl = config::get_backup_retention_ttl();
        if ($retention_ttl > 0) {
            $auto_cutoff = time() - $retention_ttl;
            $auto_records = $DB->get_records_sql(
                "SELECT * FROM {tool_stdlogarchiver_backups}
                 WHERE timecreated < :cutoff
                   AND deleted_at = 0
                   AND restored = 0",
                ['cutoff' => $auto_cutoff]
            );

            foreach ($auto_records as $record) {
                $backup = new backup(0, $record);
                // Soft-delete first, then fall through to physical purge below.
                $backup->set('deleted_at', time());
                $backup->save();
                mtrace("tool_stdlogarchiver: auto-soft-deleted backup #{$record->id} (retention TTL)");
            }
        }

        // Physical purge: all soft-deleted backups that still have files.
        $purge_records = $DB->get_records_sql(
            "SELECT * FROM {tool_stdlogarchiver_backups}
             WHERE deleted_at > 0
               AND (local_path IS NOT NULL OR external_service IS NOT NULL)",
            []
        );

        foreach ($purge_records as $record) {
            $backup = new backup(0, $record);
            $this->purge_backup($backup);
        }
    }

    private function purge_backup(backup $backup): void {
        $id = $backup->get('id');

        // Delete local file.
        if ($backup->local_file_exists()) {
            @unlink($backup->get_local_file_path());
            mtrace("tool_stdlogarchiver: backup #{$id} — local file deleted");
        }

        // Delete from external storage.
        if ($backup->has_external()) {
            $service = config::get_external_backup_service($backup->get('external_service'));
            if ($service) {
                $external_uri = $backup->get('external_uri');

                if ($service->exists($external_uri)) {
                    try {
                        $service->delete($external_uri);
                        mtrace("tool_stdlogarchiver: backup #{$id} — external file deleted ({$external_uri})");
                    } catch (\Throwable $e) {
                        mtrace("tool_stdlogarchiver: backup #{$id} — external delete failed: " . $e->getMessage());
                    }
                } else {
                    mtrace("tool_stdlogarchiver: backup #{$id} — external file already missing or unverifiable ({$external_uri})");
                }
            }
        }

        // Nullify file references, keep DB record as audit.
        $backup->set('local_path', null);
        $backup->set('external_service', null);
        $backup->set('external_uri', null);
        $backup->set('external_customdata', null);
        $backup->save();

        mtrace("tool_stdlogarchiver: backup #{$id} fully purged");
    }
}

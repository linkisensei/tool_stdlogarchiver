<?php namespace tool_stdlogarchiver\task;

use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;

defined('MOODLE_INTERNAL') || die();

/**
 * Permanently deletes local backup files for:
 *   1. Backups soft-deleted by the user (deleted_at > 0)
 *   2. Backups older than backup_retention_ttl (auto-purge, if TTL is configured)
 *
 * Remote file deletion is gated behind config::FEATURE_PURGE_EXTERNAL (currently
 * disabled). When disabled, external metadata is preserved in the DB so that
 * infrastructure tooling can reconcile remote storage independently.
 *
 * The DB record itself is always preserved as audit history.
 */
class backup_purge_task extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task:backup_purge_task_name', 'tool_stdlogarchiver');
    }

    public function execute(): void {
        global $DB;

        // Auto-purge: backups older than retention TTL — delete local file only, preserve remote.
        $retention_ttl = config::get_backup_retention_ttl();
        if ($retention_ttl > 0) {
            $auto_cutoff = time() - $retention_ttl;
            $auto_records = $DB->get_records_sql(
                "SELECT * FROM {tool_stdlogarchiver_backups}
                 WHERE timecreated < :cutoff
                   AND deleted_at = 0
                   AND restored = 0
                   AND local_path IS NOT NULL",
                ['cutoff' => $auto_cutoff]
            );

            foreach ($auto_records as $record) {
                $backup = new backup(0, $record);
                $this->purge_local_file($backup);
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

    private function purge_local_file(backup $backup): void {
        $id = $backup->get('id');
        if ($backup->local_file_exists()) {
            @unlink($backup->get_local_file_path());
            mtrace("tool_stdlogarchiver: backup #{$id} — local file deleted (retention TTL)");
        }
        $backup->set('local_path', null);

        if (config::FEATURE_PURGE_EXTERNAL) {
            $this->delete_external($backup);
        }

        $backup->save();
    }

    private function purge_backup(backup $backup): void {
        $id = $backup->get('id');

        if ($backup->local_file_exists()) {
            @unlink($backup->get_local_file_path());
            mtrace("tool_stdlogarchiver: backup #{$id} — local file deleted");
        }
        $backup->set('local_path', null);

        if (config::FEATURE_PURGE_EXTERNAL) {
            $this->delete_external($backup);
        }

        $backup->save();
        mtrace("tool_stdlogarchiver: backup #{$id} fully purged");
    }

    private function delete_external(backup $backup): void {
        if (!$backup->has_external()) {
            return;
        }
        $id = $backup->get('id');
        $service = config::get_external_backup_service($backup->get('external_service'));
        if (!$service) {
            return;
        }
        $uri = $backup->get('external_uri');
        if ($service->exists($uri)) {
            try {
                $service->delete($uri);
                mtrace("tool_stdlogarchiver: backup #{$id} — external file deleted ({$uri})");
            } catch (\Throwable $e) {
                mtrace("tool_stdlogarchiver: backup #{$id} — external delete failed: " . $e->getMessage());
                return;
            }
        } else {
            mtrace("tool_stdlogarchiver: backup #{$id} — external file already missing ({$uri})");
        }
        $backup->set('external_service', null);
        $backup->set('external_uri', null);
        $backup->set('external_customdata', null);
    }
}

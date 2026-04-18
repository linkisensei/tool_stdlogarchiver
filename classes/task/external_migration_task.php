<?php namespace tool_stdlogarchiver\task;

use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;

defined('MOODLE_INTERNAL') || die();

class external_migration_task extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task:external_migration_task_name', 'tool_stdlogarchiver');
    }

    public function execute(): void {
        global $DB;

        $service = config::get_external_backup_service();
        if (!$service || !$service->is_enabled()) {
            mtrace('tool_stdlogarchiver: no external service configured, skipping migration.');
            return;
        }

        // The admin setting documents 0 as "upload immediately", so treat it
        // as a valid delay instead of disabling migration.
        $delay = max(0, config::get_external_migration_delay());

        $cutoff = time() - $delay;

        $records = $DB->get_records_sql(
            "SELECT * FROM {tool_stdlogarchiver_backups}
             WHERE timecreated < :cutoff
               AND deleted_at = 0
               AND external_service IS NULL
               AND local_path IS NOT NULL
             ORDER BY timecreated ASC",
            ['cutoff' => $cutoff]
        );

        foreach ($records as $record) {
            $backup = new backup(0, $record);

            if (!$backup->local_file_exists()) {
                mtrace("tool_stdlogarchiver: backup #{$record->id} — local file missing, skipping.");
                continue;
            }

            try {
                $external_uri = $service->upload($backup);

                $backup->set('external_service', $service::get_name());
                $backup->set('external_uri', $external_uri);
                $backup->encode_external_customdata([
                    'original_size'    => filesize($backup->get_local_file_path()),
                    'service'          => $service::get_name(),
                    'migrated_at'      => time(),
                ]);

                if (config::delete_local_after_external()) {
                    $backup->delete_local_file();
                }

                $backup->save();

                mtrace("tool_stdlogarchiver: backup #{$record->id} → {$external_uri}");

            } catch (\Throwable $e) {
                mtrace("tool_stdlogarchiver: backup #{$record->id} migration failed: " . $e->getMessage());
            }
        }
    }
}

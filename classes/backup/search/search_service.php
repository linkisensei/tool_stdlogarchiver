<?php namespace tool_stdlogarchiver\backup\search;

use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\backup\readers\sqlite_reader;
use \tool_stdlogarchiver\task\cache_download_task;

class search_service {

    public function __construct() {
        raise_memory_limit(MEMORY_EXTRA);
        \core_php_time_limit::raise();
    }

    /**
     * Search archived logs across all SQLite backups in the given time range.
     *
     * For backups that are only available on external storage (no local file, not cached),
     * a cache_download_task is queued and the backup is added to 'pending'.
     * The caller should inform the user to re-run the search after a few minutes.
     *
     * @return array {
     *   results:     object[]   — records found immediately
     *   searched:    backup[]   — backups that were successfully queried
     *   skipped_csv: int        — number of CSV backups in range (not searchable)
     *   pending:     backup[]   — backups queued for remote download
     *   unavailable: backup[]   — backups with no local file and no external storage
     * }
     */
    public function search(int $starttime, int $endtime, array $filters = [], int $page = 0): array {
        $results = [
            'results'     => [],
            'searched'    => [],
            'skipped_csv' => 0,
            'pending'     => [],
            'unavailable' => [],
        ];

        $sqlite_backups = $this->get_sqlite_backups($starttime, $endtime);
        $results['skipped_csv'] = $this->count_csv_backups($starttime, $endtime);

        foreach ($sqlite_backups as $backup) {
            if ($backup->local_file_exists()) {
                $path = $backup->get_local_file_path();
            } elseif ($backup->is_cached()) {
                $path = $backup->get_cache_path();
                touch($path); // Renew TTL.
            } elseif ($backup->has_external()) {
                cache_download_task::create_and_enqueue($backup->get('id'));
                $results['pending'][] = $backup;
                continue;
            } else {
                $results['unavailable'][] = $backup;
                continue;
            }

            try {
                $reader = new sqlite_reader($path);
                foreach ($reader->search($starttime, $endtime, $filters) as $record) {
                    $record->backupid = $backup->get('id');
                    $results['results'][] = $record;
                }
                $results['searched'][] = $backup;
            } catch (\Throwable $e) {
                debugging('tool_stdlogarchiver: search failed for backup #' .
                    $backup->get('id') . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                $results['unavailable'][] = $backup;
            }
        }

        return $results;
    }

    protected function get_sqlite_backups(int $starttime, int $endtime): array {
        return backup::get_records_select(
            "starttime < :endtime AND endtime > :starttime
             AND fileformat = 'db' AND deleted_at = 0 AND restored = 0",
            ['starttime' => $starttime, 'endtime' => $endtime],
            'starttime ASC'
        );
    }

    protected function count_csv_backups(int $starttime, int $endtime): int {
        return backup::count_records_select(
            "starttime < :endtime AND endtime > :starttime
             AND fileformat != 'db' AND deleted_at = 0",
            ['starttime' => $starttime, 'endtime' => $endtime]
        );
    }
}

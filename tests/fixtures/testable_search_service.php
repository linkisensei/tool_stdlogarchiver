<?php namespace tool_stdlogarchiver\backup\search;

use \tool_stdlogarchiver\models\backup;

/**
 * Subclass of search_service that exposes protected helpers for unit testing.
 * All search filtering is now handled by SQLite queries, so this class mainly
 * exists to call protected methods directly in tests.
 */
class testable_search_service extends search_service {

    public function get_sqlite_backups(int $starttime, int $endtime): array {
        return parent::get_sqlite_backups($starttime, $endtime);
    }

    public function count_csv_backups(int $starttime, int $endtime): int {
        return parent::count_csv_backups($starttime, $endtime);
    }
}

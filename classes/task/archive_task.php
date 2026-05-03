<?php namespace tool_stdlogarchiver\task;

use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\util\standard_logstore;
use \tool_stdlogarchiver\util\logstored_other_trait;

defined('MOODLE_INTERNAL') || die();

class archive_task extends \core\task\scheduled_task {

    use logstored_other_trait;

    /**
     * Safety cap to avoid processing too many calendar days in a single run.
     */
    public const MAX_DAYS_PER_RUN = 30;

    public function get_name(): string {
        return get_string('task:archive_task_name', 'tool_stdlogarchiver');
    }

    public function execute(): void {
        global $DB;

        if (!config::is_enabled()) {
            mtrace('tool_stdlogarchiver: plugin disabled, skipping.');
            return;
        }

        raise_memory_limit(MEMORY_HUGE);
        \core_php_time_limit::raise();

        $logstore     = standard_logstore::instance();
        $table        = $logstore->get_logstore_table();
        $cutoff       = time() - config::get_log_lifetime();
        $max_per_file = config::get_max_records_per_file();
        $processed_days = 0;

        // Watermark: composite (timecreated, id) position of the last archived record.
        // Records are selected in (timecreated ASC, id ASC) order and only those strictly
        // after this position are eligible, preventing already-processed records from being
        // revisited and ensuring records with out-of-order IDs are never skipped.
        $wm      = config::get_archive_watermark();
        $wm_time = $wm->time;
        $wm_id   = $wm->id;

        mtrace("tool_stdlogarchiver: starting from watermark time={$wm_time} id={$wm_id}");

        while (true) {
            $min_tc = $DB->get_field_sql(
                "SELECT MIN(timecreated) FROM {{$table}}
                 WHERE (timecreated > :wm_time OR (timecreated = :wm_time2 AND id > :wm_id))
                   AND timecreated < :cutoff",
                ['wm_time' => $wm_time, 'wm_time2' => $wm_time, 'wm_id' => $wm_id, 'cutoff' => $cutoff]
            );

            if (!$min_tc) {
                mtrace('tool_stdlogarchiver: no more records to archive.');
                break;
            }

            $day_start = (int) floor($min_tc / DAYSECS) * DAYSECS;
            $day_end   = $day_start + DAYSECS;
            $processed_days++;

            mtrace('tool_stdlogarchiver: archiving day ' . gmdate('Y-m-d', $day_start));

            // Inner loop: chunks through the day until it is fully archived.
            // Always runs to completion regardless of elapsed time.
            while (true) {
                $records = $DB->get_records_select(
                    $table,
                    "timecreated >= :day_start AND timecreated < :day_end
                     AND (timecreated > :wm_time OR (timecreated = :wm_time2 AND id > :wm_id))
                     AND timecreated < :cutoff",
                    ['day_start' => $day_start, 'day_end' => $day_end,
                     'wm_time' => $wm_time, 'wm_time2' => $wm_time, 'wm_id' => $wm_id, 'cutoff' => $cutoff],
                    'timecreated ASC, id ASC', '*', 0, $max_per_file
                );

                if (empty($records)) {
                    break; // Day fully archived.
                }

                $this->write_chunk($table, $records);

                $last    = end($records);
                $wm_time = (int) $last->timecreated;
                $wm_id   = (int) $last->id;
            }

            if ($processed_days >= self::MAX_DAYS_PER_RUN) {
                mtrace(sprintf(
                    'tool_stdlogarchiver: execution limit reached (%d day(s) processed), stopping for now.',
                    self::MAX_DAYS_PER_RUN
                ));
                return;
            }
        }
    }

    private function write_chunk(string $table, array $records): void {
        global $DB;

        if (empty($records)) {
            return;
        }

        $first  = reset($records);
        $last   = end($records);
        $format = config::get_backup_format();

        // firstid/lastid store the true MIN/MAX id of the chunk — with (timecreated, id)
        // ordering the first and last records are not necessarily the lowest and highest IDs.
        $chunk_ids = array_column($records, 'id');

        // Include firstid in the filename to prevent collisions when multiple chunks
        // share the same starttime (e.g. 200 records at the same timecreated second).
        $filename = sprintf('%d_%d_%d.%s',
            (int) $first->timecreated, (int) $last->timecreated, (int) min($chunk_ids), $format);
        $backupdir = config::get_backup_dir();
        $filepath = $backupdir . '/' . $filename;
        $temppath = $backupdir . '/.' . $filename . '.tmp';

        if (file_exists($filepath)) {
            throw new \RuntimeException("Refusing to overwrite existing backup file: {$filename}");
        }

        if (file_exists($temppath)) {
            @unlink($temppath);
        }

        $writer_class = config::get_writer_class();
        $writer       = new $writer_class($temppath);

        try {
            foreach ($records as $record) {
                $record->other = self::to_json($record->other);
                $writer->append($record);
            }
            $writer->finalize();

            if (!@rename($temppath, $filepath)) {
                @unlink($temppath);
                throw new \RuntimeException("Failed to promote temporary backup file to final path: {$filename}");
            }
        } catch (\Throwable $e) {
            $writer->destroy();
            @unlink($temppath);
            throw $e;
        }

        $backup_record = new backup(0, (object) [
            'firstid'    => (int) min($chunk_ids),
            'lastid'     => (int) max($chunk_ids),
            'starttime'  => (int) $first->timecreated,
            'endtime'    => (int) $last->timecreated,
            'fileformat' => $format,
            'local_path' => $filename, // relative to backup_dir — resolved in backup::get_local_path()
        ]);
        $backup_record->save();

        // DELETE by explicit IDs — avoids BETWEEN race condition.
        $ids = array_column($records, 'id');
        foreach (array_chunk($ids, 1000) as $chunk) {
            [$in_sql, $in_params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'logid');
            $DB->delete_records_select($table, "id $in_sql", $in_params);
        }

        // Persist watermark after successful write+delete so progress survives a crash
        // on the next chunk. Uses the last record in (timecreated, id) order.
        config::set_archive_watermark((int) $last->timecreated, (int) $last->id);

        mtrace(sprintf('tool_stdlogarchiver: archived %d records → %s (Backup #%d)',
            count($records), $filename, $backup_record->get('id')));
    }
}

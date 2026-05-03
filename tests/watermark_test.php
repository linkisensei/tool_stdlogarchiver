<?php namespace tool_stdlogarchiver;

defined('MOODLE_INTERNAL') || die();

use \advanced_testcase;
use \tool_stdlogarchiver\util\standard_logstore;
use \tool_stdlogarchiver\util\log_generator;
use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\backup\writers\sqlite_writer;
use \tool_stdlogarchiver\task\archive_task;

/**
 * Tests for the composite (timecreated, id) watermark introduced to replace
 * the unsafe MAX(lastid) watermark, and for the undo_restore file-based delete.
 */
class watermark_test extends advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected static function base_config(): void {
        config::set(config::CONFIG_ENABLED, 1);
        config::set(config::CONFIG_MAX_RECORDS_PER_FILE, 500);
        config::set(config::CONFIG_BACKUP_FORMAT, config::BACKUP_FORMAT_DB);
        config::set(config::CONFIG_LOG_LIFETIME, 10 * WEEKSECS);

        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);
    }

    protected static function insert_logs(object $user, int $quantity, int $timecreated): void {
        log_generator::insert_synthetic_logs($quantity, [
            'userid'    => $user->id,
            'starttime' => $timecreated,
            'timestep'  => 0,
            'origin'    => 'web',
        ]);
    }

    private function run_archive_task(): void {
        (new archive_task())->execute();
    }

    /**
     * Insert a minimal valid record into the logstore with a specific id.
     * Used to simulate records that should survive undo_restore because they
     * were never part of the backup file.
     */
    private function insert_logstore_record_with_id(int $id, int $timecreated): void {
        global $DB;
        $DB->import_record(
            standard_logstore::instance()->get_logstore_table(),
            (object) [
                'id'                => $id,
                'eventname'         => '\core\event\user_loggedin',
                'component'         => 'core',
                'action'            => 'loggedin',
                'target'            => 'user',
                'objecttable'       => null,
                'objectid'          => null,
                'crud'              => 'r',
                'edulevel'          => 0,
                'contextid'         => 1,
                'contextlevel'      => 10,
                'contextinstanceid' => 0,
                'userid'            => 2,
                'courseid'          => SITEID,
                'relateduserid'     => null,
                'anonymous'         => 0,
                'other'             => null,
                'timecreated'       => $timecreated,
                'ip'                => null,
                'realuserid'        => null,
                'origin'            => 'web',
            ]
        );
    }

    /**
     * Write a SQLite backup file containing exactly the given IDs.
     * Mirrors what archive_task::write_chunk() produces, with all logstore
     * columns present so the reader can parse the file normally.
     */
    private function create_backup_file_with_ids(array $ids, int $timecreated, string $filename): void {
        $columns = standard_logstore::instance()->get_logstore_columns();
        $writer  = new sqlite_writer(config::get_backup_dir() . '/' . $filename);

        foreach ($ids as $id) {
            $rec                    = (object) array_fill_keys($columns, null);
            $rec->id                = $id;
            $rec->eventname         = '\core\event\user_loggedin';
            $rec->component         = 'core';
            $rec->action            = 'loggedin';
            $rec->target            = 'user';
            $rec->crud              = 'r';
            $rec->edulevel          = 0;
            $rec->contextid         = 1;
            $rec->contextlevel      = 10;
            $rec->contextinstanceid = 0;
            $rec->userid            = 2;
            $rec->courseid          = SITEID;
            $rec->anonymous         = 0;
            $rec->timecreated       = $timecreated;
            $rec->origin            = 'web';
            $rec->other             = json_encode([]);
            $writer->append($rec);
        }

        $writer->finalize();
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * Records whose IDs are interleaved with already-archived IDs must still be
     * archived once their timecreated falls below the cutoff.
     *
     * Setup:
     *   Batch A (50, old timecreated)  → low auto-IDs
     *   Batch B (10, recent)           → medium IDs, between A and C
     *   Batch C (50, old timecreated)  → high IDs, above B
     *
     * With the old MAX(lastid) watermark, run 2 could never find batch B because
     * its IDs were below the watermark set by batch C.
     * With the time+id watermark, batch B's timecreated > wm_time so it is found.
     */
    public function test_out_of_order_ids_are_archived_after_becoming_eligible(): void {
        global $DB;

        self::base_config();
        $user      = $this->getDataGenerator()->create_user();
        $log_table = standard_logstore::instance()->get_logstore_table();
        $DB->execute("DELETE FROM {{$log_table}}");

        $now = time();
        $old = $now - YEARSECS;

        self::insert_logs($user, 50, $old);                    // Batch A — low IDs
        self::insert_logs($user, 10, $now - 3 * WEEKSECS);    // Batch B — recent, medium IDs
        self::insert_logs($user, 50, $old);                    // Batch C — old, high IDs

        $this->expectOutputRegex('/tool_stdlogarchiver:/');
        $this->run_archive_task();

        $this->assertEquals(10, $DB->count_records($log_table),
            'Only the 10 recent records (batch B) should remain after the first run');

        // Shorten retention so batch B becomes eligible.
        config::set(config::CONFIG_LOG_LIFETIME, 2 * WEEKSECS);

        // Batch B's IDs fall between batches A and C that are already archived.
        // The old ID watermark would skip them permanently; the time+id watermark finds them.
        $this->run_archive_task();

        $this->assertEquals(0, $DB->count_records($log_table),
            'Batch B must be archived even though its IDs are lower than already-archived batch C records');
    }

    /**
     * On upgrade the watermark is seeded from MAX(endtime) and MAX(lastid) at that
     * endtime. Records at or below that position must not be re-archived.
     */
    public function test_upgrade_watermark_protects_records_at_or_below_it(): void {
        global $DB;

        self::base_config();
        $user      = $this->getDataGenerator()->create_user();
        $log_table = standard_logstore::instance()->get_logstore_table();
        $DB->execute("DELETE FROM {{$log_table}}");

        $old = time() - YEARSECS;
        self::insert_logs($user, 50, $old);

        // Mirror the upgrade block: wm_time = MAX(endtime), wm_id = MAX(lastid at that endtime).
        // All 50 records have the same timecreated, so max id covers them all.
        $max_id = (int) $DB->get_field_sql("SELECT MAX(id) FROM {{$log_table}}");
        config::set_archive_watermark($old, $max_id);

        $this->expectOutputRegex('/tool_stdlogarchiver:/');
        $this->run_archive_task();

        $this->assertEquals(50, $DB->count_records($log_table),
            'Records at or below the upgrade watermark must not be archived');
        $this->assertEmpty(backup::get_records([]),
            'No backup should be created when all records are protected by the upgrade watermark');
    }

    /**
     * When many records share the same timecreated second and exceed max_per_file,
     * the archive_watermark_id tie-breaker must advance through all of them so no
     * record is missed due to hitting the chunk boundary.
     */
    public function test_same_timecreated_records_split_across_chunks_are_all_archived(): void {
        global $DB;

        self::base_config();
        config::set(config::CONFIG_MAX_RECORDS_PER_FILE, 50);

        $user      = $this->getDataGenerator()->create_user();
        $log_table = standard_logstore::instance()->get_logstore_table();
        $DB->execute("DELETE FROM {{$log_table}}");

        $old = time() - YEARSECS;
        self::insert_logs($user, 200, $old); // all share the same timecreated second

        $this->expectOutputRegex('/tool_stdlogarchiver:/');
        $this->run_archive_task();

        $this->assertEquals(0, $DB->count_records($log_table),
            'All 200 records sharing the same timecreated must be archived across multiple chunks');
        $this->assertCount(4, backup::get_records([]),
            '200 records at 50 per file must produce exactly 4 backup files');
    }

    /**
     * undo_restore must delete only the IDs present in the backup file.
     * A record whose ID falls within [firstid, lastid] but was never written to
     * the file must survive the undo operation.
     *
     * The backup file contains IDs [10001, 10002, 10003, 10005] with a deliberate
     * gap at 10004. After undo_restore, 10004 must still be in the logstore.
     */
    public function test_undo_restore_deletes_only_ids_present_in_file(): void {
        global $DB;

        self::base_config();
        $log_table   = standard_logstore::instance()->get_logstore_table();
        $DB->execute("DELETE FROM {{$log_table}}");

        $timecreated = time() - YEARSECS;
        $backup_ids  = [10001, 10002, 10003, 10005];
        $gap_id      = 10004; // in the [firstid, lastid] range but NOT in the file
        $filename    = "{$timecreated}_{$timecreated}_" . min($backup_ids) . ".db";

        $this->create_backup_file_with_ids($backup_ids, $timecreated, $filename);

        $b = new backup(0, (object) [
            'firstid'    => min($backup_ids),
            'lastid'     => max($backup_ids),
            'starttime'  => $timecreated,
            'endtime'    => $timecreated,
            'fileformat' => config::BACKUP_FORMAT_DB,
            'local_path' => $filename,
            'restored'   => 1,
        ]);
        $b->save();

        // Insert all five IDs — including the gap that was never in the backup.
        foreach (array_merge($backup_ids, [$gap_id]) as $id) {
            $this->insert_logstore_record_with_id($id, $timecreated);
        }

        $b->undo_restore();

        $this->assertTrue(
            $DB->record_exists($log_table, ['id' => $gap_id]),
            "Record {$gap_id} was not in the backup file and must not be deleted by undo_restore"
        );

        foreach ($backup_ids as $id) {
            $this->assertFalse(
                $DB->record_exists($log_table, ['id' => $id]),
                "Record {$id} was in the backup file and must be deleted by undo_restore"
            );
        }
    }

    /**
     * After each archived chunk the composite watermark (archive_watermark_time,
     * archive_watermark_id) stored in plugin config must be updated to reflect the
     * last record processed in (timecreated ASC, id ASC) order.
     *
     * For records sharing the same timecreated, the highest id is the last record
     * in that order, so both wm_time and wm_id are predictable.
     */
    public function test_watermark_is_persisted_correctly_after_archiving(): void {
        global $DB;

        self::base_config();
        config::set(config::CONFIG_MAX_RECORDS_PER_FILE, 50);

        $user      = $this->getDataGenerator()->create_user();
        $log_table = standard_logstore::instance()->get_logstore_table();
        $DB->execute("DELETE FROM {{$log_table}}");

        $old = time() - YEARSECS;
        self::insert_logs($user, 100, $old);

        $expected_max_id = (int) $DB->get_field_sql("SELECT MAX(id) FROM {{$log_table}}");

        $this->expectOutputRegex('/tool_stdlogarchiver:/');
        $this->run_archive_task();

        $wm = config::get_archive_watermark();

        $this->assertEquals($old, $wm->time,
            'archive_watermark_time must equal the timecreated of the last archived record');
        $this->assertEquals($expected_max_id, $wm->id,
            'archive_watermark_id must equal the highest id among all archived records');
    }

    /**
     * Full round-trip using a real backup produced by archive_task:
     *   archive → logstore empty
     *   restore → records back in logstore with original IDs
     *   undo_restore → archived records gone, extra record untouched
     *
     * This verifies that the three operations are consistent with each other
     * when the backup file is written and read by the same code path.
     */
    public function test_full_archive_restore_undo_restore_cycle(): void {
        global $DB;

        self::base_config();
        $user      = $this->getDataGenerator()->create_user();
        $log_table = standard_logstore::instance()->get_logstore_table();
        $DB->execute("DELETE FROM {{$log_table}}");

        $old = time() - YEARSECS;
        self::insert_logs($user, 100, $old);
        $original_ids = $DB->get_fieldset_select($log_table, 'id', '');
        sort($original_ids);
        $sample_id = reset($original_ids); // one representative ID to check later

        // Archive.
        $this->expectOutputRegex('/tool_stdlogarchiver:/');
        $this->run_archive_task();

        $this->assertEquals(0, $DB->count_records($log_table),
            'Logstore must be empty after archiving');

        $backups = backup::get_records([]);
        $this->assertNotEmpty($backups, 'At least one backup must be created');

        // Restore all backups.
        foreach ($backups as $b) {
            $b->restore();
        }

        $restored_ids = $DB->get_fieldset_select($log_table, 'id', '');
        sort($restored_ids);

        $this->assertEquals($original_ids, $restored_ids,
            'Restored logstore must contain exactly the same IDs that were archived');

        // Insert an extra record that was never part of any backup.
        // It must survive undo_restore because it is not in any backup file.
        self::insert_logs($user, 1, time() - 2 * WEEKSECS);
        $extra_id = (int) $DB->get_field_sql("SELECT MAX(id) FROM {{$log_table}}");

        // Undo all restores. restore() already set restored=true on each object in memory.
        foreach ($backups as $b) {
            $b->undo_restore();
        }

        $this->assertEquals(1, $DB->count_records($log_table),
            'After undo_restore only the extra non-backup record must remain');
        $this->assertTrue(
            $DB->record_exists($log_table, ['id' => $extra_id]),
            'Record not in any backup must survive undo_restore'
        );
        $this->assertFalse(
            $DB->record_exists($log_table, ['id' => $sample_id]),
            "Archived record {$sample_id} must be deleted by undo_restore"
        );
    }

    /**
     * With ORDER BY timecreated ASC, id ASC the first and last records in a chunk
     * are not necessarily the lowest and highest IDs. The backup must store
     * firstid = MIN(id) and lastid = MAX(id) regardless of processing order.
     *
     * Setup: Group L inserted first (gets low IDs) but has a LATER timecreated.
     *        Group E inserted second (gets high IDs) but has an EARLIER timecreated.
     * Processing order: Group E (earlier tc, high IDs) → Group L (later tc, low IDs).
     * The first record processed has the highest ID; the last has the lowest.
     * firstid/lastid must still reflect MIN/MAX of the whole chunk.
     */
    public function test_firstid_lastid_are_min_max_of_chunk(): void {
        global $DB;

        self::base_config();
        config::set(config::CONFIG_MAX_RECORDS_PER_FILE, 500);

        $user      = $this->getDataGenerator()->create_user();
        $log_table = standard_logstore::instance()->get_logstore_table();
        $DB->execute("DELETE FROM {{$log_table}}");

        $day = (int) floor((time() - YEARSECS) / DAYSECS) * DAYSECS;

        // Group L: later timecreated (02:00), inserted first → low auto-IDs.
        self::insert_logs($user, 50, $day + 7200);

        // Group E: earlier timecreated (01:00), inserted second → high auto-IDs.
        self::insert_logs($user, 50, $day + 3600);

        $expected_min_id = (int) $DB->get_field_sql("SELECT MIN(id) FROM {{$log_table}}");
        $expected_max_id = (int) $DB->get_field_sql("SELECT MAX(id) FROM {{$log_table}}");

        $this->expectOutputRegex('/tool_stdlogarchiver:/');
        $this->run_archive_task();

        $backups = backup::get_records([]);
        $this->assertCount(1, $backups, 'All 100 records must fit in a single chunk');

        $b = reset($backups);
        $this->assertEquals($expected_min_id, $b->get('firstid'),
            'firstid must be MIN(id) of the chunk, not the id of the first record in time order');
        $this->assertEquals($expected_max_id, $b->get('lastid'),
            'lastid must be MAX(id) of the chunk, not the id of the last record in time order');
    }

    /**
     * Records with timecreated >= cutoff must never be archived regardless of their
     * position in the id sequence.
     */
    public function test_records_above_cutoff_are_never_archived(): void {
        global $DB;

        self::base_config();
        $user      = $this->getDataGenerator()->create_user();
        $log_table = standard_logstore::instance()->get_logstore_table();
        $DB->execute("DELETE FROM {{$log_table}}");

        $now = time();
        self::insert_logs($user, 50, $now - YEARSECS);   // archivable
        self::insert_logs($user, 30, $now - 2 * WEEKSECS); // above cutoff (10 weeks retention)
        self::insert_logs($user, 20, $now);               // definitely above cutoff

        $this->expectOutputRegex('/tool_stdlogarchiver:/');
        $this->run_archive_task();

        $this->assertEquals(50, $DB->count_records($log_table),
            'Only the 50 recent records must remain; the 50 old ones should be archived');
        $this->assertEquals(0, $DB->count_records_select(
            $log_table,
            'timecreated < :cutoff',
            ['cutoff' => $now - 10 * WEEKSECS]
        ), 'No record below the cutoff must remain in the logstore');
    }
}

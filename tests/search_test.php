<?php namespace tool_stdlogarchiver;

defined('MOODLE_INTERNAL') || die();

use \advanced_testcase;
use \tool_stdlogarchiver\util\standard_logstore;
use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\backup\readers\sqlite_reader;
use \tool_stdlogarchiver\backup\writers\sqlite_writer;
use \tool_stdlogarchiver\backup\search\search_service;
use \tool_stdlogarchiver\output\renderables\search_results;

class search_test extends advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Creates a SQLite backup file with synthetic log records and saves a backup model.
     *
     * @param int   $starttime  Period start (will also be the timecreated of records)
     * @param int   $endtime    Period end
     * @param array $rows       Additional field overrides applied to every record
     * @param int   $count      Number of records to insert
     */
    protected function create_sqlite_backup(
        int $starttime,
        int $endtime,
        array $rows = [],
        int $count = 10
    ): backup {
        $filename = "{$starttime}_{$endtime}.db";
        $path     = config::get_backup_dir() . '/' . $filename;

        $columns = standard_logstore::instance()->get_logstore_columns();

        $writer = new sqlite_writer($path);

        $base = (object) array_fill_keys($columns, null);
        $base->timecreated = $starttime;
        $base->eventname   = '\\core\\event\\user_loggedin';
        $base->userid      = 1;
        $base->courseid    = 1;
        $base->origin      = 'web';

        foreach ($rows as $k => $v) {
            $base->$k = $v;
        }

        $firstid = 1000000 + $starttime;
        for ($i = 0; $i < $count; $i++) {
            $rec     = clone $base;
            $rec->id = $firstid + $i;
            $writer->append($rec);
        }
        $writer->finalize();

        $b = new backup(0, (object) [
            'firstid'          => $firstid,
            'lastid'           => $firstid + $count - 1,
            'starttime'        => $starttime,
            'endtime'          => $endtime,
            'fileformat'       => 'db',
            'local_path'       => $filename, // relative to backup_dir
            'external_service' => null,
            'external_uri'     => null,
            'external_customdata' => null,
            'restored'         => 0,
            'deleted_at'       => 0,
        ]);
        $b->save();

        return $b;
    }

    /** @group xcurrent */
    public function test_sqlite_writer_and_reader(): void {
        global $CFG;

        $dir  = config::get_backup_dir();
        $path = $dir . '/test_writer.db';
        $cols = standard_logstore::instance()->get_logstore_columns();

        $writer = new sqlite_writer($path);
        for ($i = 1; $i <= 50; $i++) {
            $rec = (object) array_fill_keys($cols, null);
            $rec->id          = $i;
            $rec->timecreated = time() - $i;
            $rec->eventname   = '\\core\\event\\user_loggedin';
            $rec->userid      = $i;
            $rec->courseid    = 1;
            $rec->origin      = 'web';
            $writer->append($rec);
        }
        $writer->finalize();

        $this->assertFileExists($path);

        $reader = new sqlite_reader($path);
        $rows   = $reader->get_contents();
        $this->assertCount(50, $rows);
        $this->assertEquals(1, $rows[0]->id);
        $this->assertEquals(50, $rows[49]->id);

        unlink($path);
    }

    /** @group xcurrent */
    public function test_sqlite_reader_search_filters(): void {
        global $CFG;

        $dir  = config::get_backup_dir();
        $path = $dir . '/test_search.db';
        $cols = standard_logstore::instance()->get_logstore_columns();

        $writer = new sqlite_writer($path);
        $base   = (object) array_fill_keys($cols, null);
        $base->timecreated = 1700000000;
        $base->courseid    = 5;
        $base->origin      = 'web';

        // 3 records matching userid=42 + eventname=webservice
        for ($i = 1; $i <= 3; $i++) {
            $rec            = clone $base;
            $rec->id        = $i;
            $rec->userid    = 42;
            $rec->eventname = '\\core\\event\\webservice_function_called';
            $writer->append($rec);
        }
        // 7 records that should NOT match
        for ($i = 4; $i <= 10; $i++) {
            $rec            = clone $base;
            $rec->id        = $i;
            $rec->userid    = 99;
            $rec->eventname = '\\core\\event\\user_loggedin';
            $writer->append($rec);
        }
        $writer->finalize();

        $reader  = new sqlite_reader($path);
        $filters = ['userid' => 42, 'eventname' => '\\core\\event\\webservice_function_called'];
        $results = iterator_to_array($reader->search(1699000000, 1800000000, $filters));

        $this->assertCount(3, $results);
        foreach ($results as $r) {
            $this->assertEquals(42, $r->userid);
            $this->assertEquals('\\core\\event\\webservice_function_called', $r->eventname);
        }

        unlink($path);
    }

    /** @group xcurrent */
    public function test_search_service_finds_records(): void {
        config::set(config::CONFIG_BACKUP_FORMAT, config::BACKUP_FORMAT_DB);
        config::set(config::CONFIG_ENABLED, 1);

        $starttime = strtotime('2022-01-01 00:00:00');
        $endtime   = $starttime + WEEKSECS;

        $this->create_sqlite_backup($starttime, $endtime, ['userid' => 55, 'courseid' => 10], 20);

        $service = new search_service();
        $result  = $service->search($starttime - 1, $endtime + 1, ['userid' => 55]);

        $this->assertNotEmpty($result['results'], 'Expected search results for userid=55');
        $this->assertEmpty($result['pending']);
        $this->assertEmpty($result['unavailable']);
        $this->assertCount(1, $result['searched']);

        foreach ($result['results'] as $rec) {
            $this->assertEquals(55, $rec->userid);
        }
    }

    /** @group xcurrent */
    public function test_search_service_skips_csv_backups(): void {
        config::set(config::CONFIG_ENABLED, 1);

        $starttime = strtotime('2022-06-01 00:00:00');
        $endtime   = $starttime + WEEKSECS;

        // Insert a CSV backup directly (no actual file needed for the count test).
        $b = new backup(0, (object) [
            'firstid'    => 1,
            'lastid'     => 100,
            'starttime'  => $starttime,
            'endtime'    => $endtime,
            'fileformat' => 'csv',
            'local_path' => 'nonexistent_file.csv', // relative; file won't exist — only tests skipped_csv count
            'external_service'    => null,
            'external_uri'        => null,
            'external_customdata' => null,
            'restored'   => 0,
            'deleted_at' => 0,
        ]);
        $b->save();

        $service = new search_service();
        $result  = $service->search($starttime - 1, $endtime + 1, []);

        $this->assertEquals(1, $result['skipped_csv']);
        $this->assertEmpty($result['results']);
        $this->assertEmpty($result['searched']);
    }

    /** @group xcurrent */
    public function test_search_results_renderable(): void {
        config::set(config::CONFIG_ENABLED, 1);
        config::set(config::CONFIG_BACKUP_FORMAT, config::BACKUP_FORMAT_DB);

        $starttime = strtotime('2023-03-01 00:00:00');
        $endtime   = $starttime + 3 * WEEKSECS;

        for ($i = 0; $i < 3; $i++) {
            $ws = $starttime + $i * WEEKSECS;
            $we = $ws + WEEKSECS;
            $this->create_sqlite_backup($ws, $we, [], 5);
        }

        $filters = ['starttime' => $starttime, 'endtime' => $endtime];
        $renderable = new search_results($filters);

        $this->assertTrue($renderable->has_results());
        $all = $renderable->get_all_backups();
        $this->assertCount(3, $all, 'Expected 3 backups in range');
        $this->assertNotEmpty($renderable->get_searched_backups());
        $this->assertEmpty($renderable->get_pending_backups());
    }
}

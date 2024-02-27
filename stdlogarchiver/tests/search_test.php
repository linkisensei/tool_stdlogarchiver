<?php namespace tool_stdlogarchiver;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once(__DIR__ . '/fixtures/testable_search_service.php');

use \advanced_testcase;
use \testcase;

use \stored_file;
use \moodle_exception;
use \coding_exception;
use \tool_stdlogarchiver\util\standard_logstore;
use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;
use \tool_stdlogarchiver\backup\search\testable_search_service;

use \tool_stdlogarchiver\output\renderables\search_results;


class search_test extends advanced_testcase{

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
    }

    protected function create_mocked_backups_stored_file() : stored_file{
        $path = __DIR__ . '/files/mocked_backup.csv';
        
        $file_storage = get_file_storage();
        
        $file_record = [
            'component' => 'tool_stdlogarchiver',
            'filearea' => config::BACKUPS_FILEAREA,
            'contextid' => (\context_system::instance())->id,
            'itemid' => time(),
            'filename' => \core\uuid::generate() . '.csv',
            'filepath' => '/',
        ];

        return $file_storage->create_file_from_pathname($file_record, $path);
    }

    /**
     * Returns a mocked backup file.
     * Not saved into the database
     *
     * @param array $data
     * @return backup
     */
    protected function create_mocked_backup(array $data = [], bool $save = false) : backup{
        $file = $this->create_mocked_backups_stored_file();
        $mocked_data = [
            'firstid' => 88515073,
            'starttime' => empty($data['starttime']) ? 1705402973 : (int) $data['starttime'],
            'lastid' => 88515422,
            'endtime' => empty($data['endtime']) ? 1705405445 : (int) $data['endtime'],
        ];

        $backup = backup::create_from($mocked_data, $file);

        if($save){
            $backup->save();
        }

        return $backup;
    }

    /**
     * @group xcurrent
     * @return void
     */
    public function test_mocked_backup(){
        $this->resetAfterTest();
        $this->setAdminUser();

        $backup = $this->create_mocked_backup();
        $file = $backup->get_file();
        $this->assertNotEmpty($file);
        $this->assertNotEmpty($file->get_content());
        
        $reader = $backup->get_reader();
        $this->assertNotEmpty($reader->get_contents());
    }

    /**
     * @group xcurrent
     * @return void
     */
    public function test_search_service_filter_function(){
        $this->resetAfterTest();
        $this->setAdminUser();

        // Creating mocked backup
        $backup = $this->create_mocked_backup();

        // Creating testable search service
        $search_service = new testable_search_service();

        // Search Service (eventname and userid)
        $filters = [
            'eventname' => "\\core\\event\\webservice_function_called",
            'userid' => 61382,
        ];

        $filtered = $search_service->get_filtered_logs_from_backup($backup, $filters);
        foreach ($filtered as $record) {
            $this->assertEquals($filters['eventname'], $record->eventname);
            $this->assertEquals($filters['userid'], $record->userid);
        }

        // Search Service (relateduser)
        $filters = [
            'relateduserid' => 56210,
        ];

        $filtered = $search_service->get_filtered_logs_from_backup($backup, $filters);
        foreach ($filtered as $record) {
            $this->assertEquals($filters['relateduserid'], $record->relateduserid);
        }

        // Search Service (courseid)
        $filters = [
            'courseid' => 248,
        ];

        $filtered = $search_service->get_filtered_logs_from_backup($backup, $filters);
        foreach ($filtered as $record) {
            $this->assertEquals($filters['courseid'], $record->courseid);
        }

        // Search Service (origin)
        $filters = [
            'origin' => 'ws',
        ];

        $filtered = $search_service->get_filtered_logs_from_backup($backup, $filters);
        foreach ($filtered as $record) {
            $this->assertEquals($filters['origin'], $record->origin);
        }
    }

    /**
     * @group current
     * @return void
     */
    public function test_search_results(){
        $this->resetAfterTest();
        $this->setAdminUser();


        // Creating mocked backups
        $starttime = strtotime('2012-01-05 12:00:00');
        $endtime = null;
        $number_of_backups = 10;
        $backup_interval = 1 * WEEKSECS;

        $backups = [];
        for ($i=0; $i < $number_of_backups; $i++) {
            $endtime = $starttime + ($i+1) * $backup_interval;
            $backups[] = $this->create_mocked_backup([
                'starttime' => $starttime + $i * $backup_interval,
                'endtime' => $endtime,
            ], $save = true);
        }

        // Searching for 3 weeks, no filter.
        $filters = [
            'starttime' => $starttime,
            'endtime' => $starttime + 3 * WEEKSECS,
        ];
        $search_results = new search_results($filters);

        $this->assertEquals(3, $search_results->count_total_backups());
        $this->assertCount(3, $search_results->get_backups());
        $this->assertNotEmpty($search_results->get_searched_backups());


        // Searching for 3 weeks, with event and user filter.
        $filters = [
            'starttime' => $starttime,
            'endtime' => $starttime + 3 * WEEKSECS,
            'eventname' => "\\core\\event\\webservice_function_called",
            'userid' => 61382,
        ];
        $search_results = new search_results($filters);
        foreach ($search_results as $log) {
            $this->assertEquals($filters['eventname'], $log->eventname);
            $this->assertEquals($filters['userid'], $log->userid);
        }
    }
}
<?php namespace tool_stdlogarchiver\backup\search;

use \coding_exception;
use \moodle_exception;
use \tool_stdlogarchiver\models\backup;

class search_service{

    const MAX_BACKUPS_PER_PAGE = 5;

    public function __construct(){
        raise_memory_limit(MEMORY_EXTRA);
        \core_php_time_limit::raise();
    }
    
    /**
     * @param integer $starttime
     * @param integer $endtime
     * @param array $filters
     * @param integer $page Not implementing pagination at the moment
     * @return array
     */
    public function search(int $starttime, int $endtime, array $filters, int $page = 0) : array {
        $results = [
            'searched' => 0,
            'total' => 0,
            'backups' => [],
            'results' => [],
        ];

        $execution_limit_time = time() + $this->get_time_limit();

        $backups = $this->get_backups_on_interval($starttime, $endtime, $page);
        foreach ($backups as $index => $backup) {
            $results['backups'][] = $backup;

            if(time() >= $execution_limit_time){
                continue;
            }

            $results['results'] = array_merge(
                $results['results'],
                $this->get_filtered_logs_from_backup($backup, $filters)
            );
            $results['searched']++;
        }

        $results['total'] = count($backups);
        return $results;
    }

    /**
     * @param integer $starttime
     * @param integer $endtime
     * @param integer $page Not implementing pagination at the moment
     * @return array
     */
    protected function get_backups_on_interval(int $starttime, int $endtime, int $page = 0) : array {
        $select = "starttime < :endtime AND endtime > :starttime AND deleted = 0 AND restored = 0 AND pathnamehash IS NOT NULL and pathnamehash != ''";

        $params = [
            'starttime' => $starttime,
            'endtime' => $endtime,
        ];
        $skip = self::MAX_BACKUPS_PER_PAGE * $page;
        $limit = self::MAX_BACKUPS_PER_PAGE;
        return backup::get_records_select($select, $params, 'id', '*');
    }

    protected function make_filter_function(array $filters) : callable {
        $functions = [];

        if(!empty($filters['eventname'])){
            $eventname = "\\" . trim($filters['eventname'], "\\");
            $functions[] = function(object $record) use ($eventname){
                return $record->eventname == $eventname;
            };
        }

        if(!empty($filters['userid'])){
            $userid = (int) $filters['userid'];
            $functions[] = function(object $record) use ($userid){
                return $record->userid == $userid;
            };
        }

        if(!empty($filters['courseid'])){
            $courseid = (int) $filters['courseid'];
            $functions[] = function(object $record) use ($courseid){
                return $record->courseid == $courseid;
            };
        }

        if(!empty($filters['relateduserid'])){
            $relateduserid = (int) $filters['relateduserid'];
            $functions[] = function(object $record) use ($relateduserid){
                // if(empty($record->relateduserid)){
                //     return $record->userid == $relateduserid;
                // }
                return $record->relateduserid == $relateduserid;
            };
        }

        if(!empty($filters['origin'])){
            $origin = $filters['origin'];
            $functions[] = function(object $record) use ($origin){
                return $record->origin == $origin;
            };
        }

        return function(object $record) use ($functions){
            foreach ($functions as $filter) {
                if(!$filter($record)){
                    return false;
                }
            }
            return true;
        };
    }

    protected function get_filtered_logs_from_backup(backup $backup, array $filters) : array {
        $filter_function = $this->make_filter_function($filters);
        $reader = $backup->get_reader();

        if(empty($reader)){
            return [];
        }

        $filtered = [];
        $backupid = $backup->get('id');
        // $backupname = (string)$backup;

        $starttime = empty($filters['starttime']) ? 0 : (int) $filters['starttime'];
        $endtime = empty($filters['endtime']) ? INF : (int) $filters['endtime'];
        
        /** @var Generator<object> */
        foreach ($reader->get_contents_generator() as $record) {
            if($record->timecreated < $starttime){
                continue;
            }

            if($record->timecreated > $endtime){
                break;
            }

            if($filter_function($record)){
                $record->backupid = $backupid;
                // $record->backupname = $backupname;
                $filtered[] = $record;
            }
        }
        return $filtered;
    }

    /**
     * Returns a aproximately 60% of the execution time limit.
     * Max of 36 seconds if limitless
     *
     * @return integer
     */
    protected function get_time_limit() : int {
        global $CFG;

        $max_execution_time = (int) ini_get('max_execution_time') ?: 60;
        $max_time_limit = (isset($CFG->maxtimelimit) ? $CFG->maxtimelimit : 0) ?: 60;
        
        return ceil(min($max_time_limit, $max_execution_time)*0.6);
    }
}
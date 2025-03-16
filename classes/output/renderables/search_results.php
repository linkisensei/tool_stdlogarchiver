<?php namespace tool_stdlogarchiver\output\renderables;

use \renderable;
use \moodle_exception;
use \IteratorAggregate;
use \ArrayIterator;
use \Traversable;
use \templatable;

use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\backup\search\search_service;


class search_results implements renderable, IteratorAggregate{

    protected $logs = [];
    protected $backups = [];
    protected $total = 0;
    protected $searched = 0;

    public function __construct($filters = []) {
        $search_service = new search_service();

        if(empty($filters)){
            return; // Do nothing when there is no filters at all
        }

        $filters = (array) $filters;

        if(empty($filters['starttime'])){
            throw new moodle_exception('exception:starttime_is_required', 'tool_stdlogarchiver');
        }

        if(empty($filters['endtime'])){
            throw new moodle_exception('exception:endtime_is_required', 'tool_stdlogarchiver');
        }

        $starttime = intval($filters['starttime']);
        $endtime = intval($filters['endtime']);

        $result = $search_service->search($starttime, $endtime, $filters, $page = 0);

        $this->logs = $result['results'];
        $this->backups = $result['backups'];
        $this->searched = $result['searched'];
        $this->total = $result['total'];
    }

    public function getIterator() : Traversable {
        return new ArrayIterator($this->logs);
    }

    public function count_total_backups() : int {
        return $this->total;
    }

    public function count_searched_backups() : int {
        return $this->searched;
    }

    public function get_backups() : array {
        return $this->backups;
    }

    public function get_searched_backups() : array {
        return array_slice($this->backups, 0, $this->searched);
    }

}
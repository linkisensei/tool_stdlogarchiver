<?php namespace tool_stdlogarchiver\output\renderables;

use \renderable;
use \IteratorAggregate;
use \ArrayIterator;
use \Traversable;

use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\models\backup;

class backup_list implements renderable, IteratorAggregate{

    const PAGE_SIZE = 25;

    protected $backups = [];
    protected $filters = [];
    protected $page = 0;

    public function __construct($filters = [], $sort = 'id', $order = "ASC", $page = 0) {
        $this->filters = $filters;
        $this->page = $page;

        $skip = self::PAGE_SIZE * $page;
        $this->backups = backup::get_records($filters, $sort, $order, $skip, self::PAGE_SIZE);
    }

    public function getIterator() : Traversable{
        return new ArrayIterator($this->backups);
    }

    public function count_total() : int {
        return backup::count_records($this->filters);
    }

    public function get_page_size() : int {
        return self::PAGE_SIZE;
    }

    public function get_current_page() : int {
        return $this->page;
    }
}
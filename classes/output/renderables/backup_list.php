<?php namespace tool_stdlogarchiver\output\renderables;

use \renderable;
use \IteratorAggregate;
use \ArrayIterator;
use \Traversable;
use \tool_stdlogarchiver\models\backup;

class backup_list implements renderable, IteratorAggregate {

    const PAGE_SIZE = 25;

    protected array $backups = [];
    protected int $page;
    protected int $total;

    public function __construct(int $page = 0, string $sort = 'id', string $order = 'DESC') {
        $this->page = $page;
        $skip = self::PAGE_SIZE * $page;

        // Only show active (non-deleted) backups.
        $this->backups = backup::get_records(['deleted_at' => 0], $sort, $order, $skip, self::PAGE_SIZE);
        $this->total   = backup::count_records(['deleted_at' => 0]);
    }

    public function getIterator(): Traversable {
        return new ArrayIterator($this->backups);
    }

    public function count_total(): int {
        return $this->total;
    }

    public function get_page_size(): int {
        return self::PAGE_SIZE;
    }

    public function get_current_page(): int {
        return $this->page;
    }
}

<?php namespace tool_stdlogarchiver\output\renderables;

use \renderable;
use \moodle_exception;
use \IteratorAggregate;
use \ArrayIterator;
use \Traversable;
use \tool_stdlogarchiver\backup\search\search_service;

class search_results implements renderable, IteratorAggregate {

    protected array $results     = [];
    protected array $searched    = [];
    protected array $pending     = [];
    protected array $unavailable = [];
    protected int   $skipped_csv = 0;
    protected bool  $ran_search  = false;

    public function __construct(array $filters = []) {
        if (empty($filters)) {
            return;
        }

        if (empty($filters['starttime'])) {
            throw new moodle_exception('exception:starttime_is_required', 'tool_stdlogarchiver');
        }

        if (empty($filters['endtime'])) {
            throw new moodle_exception('exception:endtime_is_required', 'tool_stdlogarchiver');
        }

        $this->ran_search = true;

        $result = (new search_service())->search(
            (int) $filters['starttime'],
            (int) $filters['endtime'],
            $filters
        );

        $this->results     = $result['results'];
        $this->searched    = $result['searched'];
        $this->pending     = $result['pending'];
        $this->unavailable = $result['unavailable'];
        $this->skipped_csv = $result['skipped_csv'];
    }

    public function getIterator(): Traversable {
        return new ArrayIterator($this->results);
    }

    public function has_results(): bool {
        return $this->ran_search;
    }

    /** Backups that were fully queried and returned results (or were searched with no match). */
    public function get_searched_backups(): array {
        return $this->searched;
    }

    /** Backups queued for remote cache download — results not yet available. */
    public function get_pending_backups(): array {
        return $this->pending;
    }

    /** Backups with no local file and no external storage reference. */
    public function get_unavailable_backups(): array {
        return $this->unavailable;
    }

    /** All backup objects in range (searched + pending + unavailable), for the info table. */
    public function get_all_backups(): array {
        return array_merge($this->searched, $this->pending, $this->unavailable);
    }

    public function get_skipped_csv_count(): int {
        return $this->skipped_csv;
    }

    public function has_pending(): bool {
        return !empty($this->pending);
    }
}

<?php namespace tool_stdlogarchiver\task;

use \tool_stdlogarchiver\config;

defined('MOODLE_INTERNAL') || die();

/**
 * Removes expired SQLite cache files from the cache directory.
 * Cache files are temporary copies of external backups downloaded for search.
 */
class cache_cleanup_task extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('task:cache_cleanup_task_name', 'tool_stdlogarchiver');
    }

    public function execute(): void {
        $cache_dir = config::get_cache_dir();
        $ttl       = config::get_cache_ttl();
        $cutoff    = time() - $ttl;

        $deleted = 0;
        $freed   = 0;

        $iter = new \FilesystemIterator($cache_dir, \FilesystemIterator::SKIP_DOTS);
        foreach ($iter as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if ($file->getMTime() < $cutoff) {
                $freed += $file->getSize();
                @unlink($file->getPathname());
                $deleted++;
            }
        }

        mtrace(sprintf(
            'tool_stdlogarchiver: cache cleanup — %d file(s) deleted, %s freed.',
            $deleted,
            display_size($freed)
        ));
    }
}

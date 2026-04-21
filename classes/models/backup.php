<?php namespace tool_stdlogarchiver\models;

use \moodle_url;
use \core\persistent;
use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\util\standard_logstore;
use \tool_stdlogarchiver\util\compression_helper;
use \tool_stdlogarchiver\backup\readers\reader_interface;
use \tool_stdlogarchiver\task\restore_backup_task;
use \tool_stdlogarchiver\task\unrestore_backup_task;

class backup extends persistent {

    const TABLE = 'tool_stdlogarchiver_backups';

    protected static function define_properties(): array {
        return [
            'firstid' => [
                'type' => PARAM_INT,
            ],
            'lastid' => [
                'type' => PARAM_INT,
            ],
            'starttime' => [
                'type' => PARAM_INT,
            ],
            'endtime' => [
                'type' => PARAM_INT,
            ],
            'fileformat' => [
                'type'    => PARAM_ALPHANUMEXT,
                'null'    => NULL_ALLOWED,
                'default' => null,
            ],
            'local_path' => [
                'type'    => PARAM_RAW,
                'null'    => NULL_ALLOWED,
                'default' => null,
            ],
            'external_service' => [
                'type'    => PARAM_ALPHANUMEXT,
                'null'    => NULL_ALLOWED,
                'default' => null,
            ],
            'external_uri' => [
                'type'    => PARAM_RAW,
                'null'    => NULL_ALLOWED,
                'default' => null,
            ],
            'external_customdata' => [
                'type'    => PARAM_RAW,
                'null'    => NULL_ALLOWED,
                'default' => null,
            ],
            'restored' => [
                'type'    => PARAM_BOOL,
                'default' => false,
            ],
            'deleted_at' => [
                'type'    => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Status checks
    // -------------------------------------------------------------------------

    public function is_deleted(): bool {
        return (int) $this->get('deleted_at') > 0;
    }

    public function was_restored(): bool {
        return (bool) $this->get('restored');
    }

    public function is_restoring(): bool {
        if ($this->was_restored()) {
            return false;
        }
        return restore_backup_task::is_enqueued($this->get('id'));
    }

    public function is_searchable(): bool {
        return $this->get('fileformat') === config::BACKUP_FORMAT_DB;
    }

    public function has_external(): bool {
        return !empty($this->get('external_service'));
    }

    // -------------------------------------------------------------------------
    // File location
    // -------------------------------------------------------------------------

    public function get_filename(): string {
        return sprintf('%d_%d.%s',
            $this->get('starttime'),
            $this->get('endtime'),
            $this->get('fileformat')
        );
    }

    public function get_local_file_path(): ?string {
        $rel = $this->get('local_path');
        if (empty($rel)) {
            return null;
        }
        return rtrim(config::get_backup_dir(), '/') . '/' . $rel;
    }

    public function local_file_exists(): bool {
        $path = $this->get_local_file_path();
        return $path !== null && file_exists($path);
    }

    /**
     * Cache path derived from the external URI (strips .gz extension).
     * Only applicable to SQLite backups stored externally as .db.gz.
     */
    public function get_cache_path(): ?string {
        $uri = $this->get('external_uri');
        if (empty($uri)) {
            return null;
        }
        $filename = basename($uri);                      // e.g. 1704067200_1704153599.db.gz
        $local_name = preg_replace('/\.gz$/', '', $filename); // e.g. 1704067200_1704153599.db
        return config::get_cache_dir() . '/' . $local_name;
    }

    public function is_cached(): bool {
        $path = $this->get_cache_path();
        if ($path === null || !file_exists($path)) {
            return false;
        }
        return (time() - filemtime($path)) < config::get_cache_ttl();
    }

    /**
     * Returns a queryable local path for this backup.
     * Tries: local file → cache file.
     * Throws if neither is available (caller should use async download).
     */
    public function get_path_for_query(): string {
        if ($this->local_file_exists()) {
            return $this->get_local_file_path();
        }

        $cache_path = $this->get_cache_path();
        if ($cache_path !== null && file_exists($cache_path)) {
            if ($this->is_cached()) {
                touch($cache_path); // Renew TTL.
                return $cache_path;
            }
            // Cache expired — delete and fall through.
            @unlink($cache_path);
        }

        throw new \moodle_exception('backupfilenotavailable', 'tool_stdlogarchiver');
    }

    public function decode_external_customdata(): array {
        $raw = $this->get('external_customdata');
        if (empty($raw)) {
            return [];
        }
        return (array) json_decode($raw, true);
    }

    public function encode_external_customdata(array $data): void {
        $this->set('external_customdata', json_encode($data));
    }

    // -------------------------------------------------------------------------
    // File operations
    // -------------------------------------------------------------------------

    public function download_to_cache(): void {
        if (!$this->has_external()) {
            throw new \moodle_exception('noexternalbackup', 'tool_stdlogarchiver');
        }
        $service = config::get_external_backup_service($this->get('external_service'));
        if (!$service) {
            throw new \moodle_exception('noexternalservice', 'tool_stdlogarchiver');
        }
        $ext_uri = $this->get('external_uri');
        if (!$service->exists($ext_uri)) {
            throw new \moodle_exception('externalbackupnotavailable', 'tool_stdlogarchiver');
        }
        $cache_path = $this->get_cache_path();
        if (str_ends_with($ext_uri, '.gz')) {
            $gz_tmp = $cache_path . '.gz.tmp';
            $service->download_to_path($ext_uri, $gz_tmp);
            compression_helper::gunzip($gz_tmp, $cache_path);
            @unlink($gz_tmp);
        } else {
            $service->download_to_path($ext_uri, $cache_path);
        }
        chmod($cache_path, 0444);
    }

    public function delete_local_file(): void {
        $path = $this->get_local_file_path();
        if ($path && file_exists($path)) {
            @unlink($path);
        }
        $this->set('local_path', null);
    }

    public function soft_delete(): void {
        if ($this->is_deleted()) {
            return;
        }
        $this->set('deleted_at', time());
        $this->save();
    }

    // -------------------------------------------------------------------------
    // Reader / restore
    // -------------------------------------------------------------------------

    public function get_reader(): reader_interface {
        $format       = $this->get('fileformat');
        $reader_class = config::get_reader_class($format);
        return $reader_class::create($this->get_path_for_query());
    }

    public function create_restore_task(): bool {
        if (!$this->local_file_exists() && !$this->has_external()) {
            return false;
        }
        restore_backup_task::create_and_enqueue($this->get('id'));
        return true;
    }

    public function create_unrestore_task(): bool {
        if (!$this->was_restored()) {
            return false;
        }
        unrestore_backup_task::create_and_enqueue($this->get('id'));
        return true;
    }

    public function restore(): bool {
        $restorer = new \tool_stdlogarchiver\restore\backup_restorer($this);
        $restorer->execute();
        $this->set('restored', true);
        $this->save();
        return true;
    }

    public function undo_restore(): bool {
        global $DB;

        if (!$this->was_restored()) {
            return false;
        }

        $logstore_table = standard_logstore::instance()->get_logstore_table();
        $DB->delete_records_select(
            $logstore_table,
            'id BETWEEN :firstid AND :lastid',
            ['firstid' => $this->get('firstid'), 'lastid' => $this->get('lastid')]
        );

        $this->set('restored', false);
        $this->save();
        return true;
    }

    // -------------------------------------------------------------------------
    // Download
    // -------------------------------------------------------------------------

    public function get_download_url(): moodle_url {
        return new moodle_url('/admin/tool/stdlogarchiver/download.php', [
            'id'      => $this->get('id'),
            'sesskey' => sesskey(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    public static function get_last_backup(): ?static {
        $records = self::get_records([], 'id', 'DESC', 0, 1);
        return $records ? array_pop($records) : null;
    }

    public static function get_first_backup(): ?static {
        $records = self::get_records([], 'id', 'ASC', 0, 1);
        return $records ? array_pop($records) : null;
    }

    public function get_hash_id(): string {
        return substr(md5((string) $this->get('id')), 0, 8);
    }

    public function __toString(): string {
        $id    = $this->get('id');
        $start = $this->get('starttime');
        $end   = $this->get('endtime');
        return "Backup #$id ({$start}–{$end})";
    }
}

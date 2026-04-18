<?php namespace tool_stdlogarchiver\backup\writers;

use \tool_stdlogarchiver\config;
use \tool_stdlogarchiver\util\standard_logstore;

class sqlite_writer implements writer_interface {

    private const BATCH_SIZE = 10000;

    // Columns stored as INTEGER in the SQLite table.
    private const INTEGER_COLUMNS = [
        'id', 'userid', 'courseid', 'contextid', 'contextlevel', 'contextinstanceid',
        'objectid', 'relateduserid', 'anonymous', 'edulevel', 'timecreated', 'realuserid',
    ];

    private string $filepath;
    private \SQLite3 $db;
    private \SQLite3Stmt $insert_stmt;
    private array $columns;
    private int $insert_count = 0;

    public function __construct(string $filepath) {
        $this->filepath = $filepath;
        $this->columns  = standard_logstore::instance()->get_logstore_columns();
        $this->open();
    }

    private function open(): void {
        $this->db = new \SQLite3($this->filepath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

        // Phase 1: page size MUST be set before creating any tables.
        $this->exec_or_throw('PRAGMA page_size = 8192');

        // Phase 2: speed pragmas for bulk insert.
        $this->exec_or_throw('PRAGMA synchronous = OFF');
        $this->exec_or_throw('PRAGMA journal_mode = MEMORY');
        $this->exec_or_throw('PRAGMA temp_store = MEMORY');
        $this->exec_or_throw('PRAGMA cache_size = -65536'); // 64 MB

        $this->create_table();
        $this->prepare_insert();
        $this->exec_or_throw('BEGIN TRANSACTION');
    }

    private function create_table(): void {
        $col_defs = [];
        foreach ($this->columns as $col) {
            $type       = in_array($col, self::INTEGER_COLUMNS) ? 'INTEGER' : 'TEXT';
            $col_defs[] = '"' . $col . '" ' . $type;
        }
        $this->exec_or_throw('CREATE TABLE IF NOT EXISTS logs (' . implode(', ', $col_defs) . ')');
    }

    private function prepare_insert(): void {
        $cols = implode(', ', array_map(fn($c) => '"' . $c . '"', $this->columns));
        $vals = implode(', ', array_map(fn($c) => ':' . $c, $this->columns));
        $stmt = $this->db->prepare("INSERT INTO logs ($cols) VALUES ($vals)");
        if (!$stmt) {
            throw new \RuntimeException('SQLite prepare failed: ' . $this->db->lastErrorMsg());
        }
        $this->insert_stmt = $stmt;
    }

    public function append(object $row): void {
        if (!$this->insert_stmt->reset()) {
            throw new \RuntimeException('SQLite reset failed: ' . $this->db->lastErrorMsg());
        }

        foreach ($this->columns as $col) {
            $val = $row->$col ?? null;
            if ($val === null) {
                $this->insert_stmt->bindValue(':' . $col, null, SQLITE3_NULL);
            } elseif (in_array($col, self::INTEGER_COLUMNS)) {
                $this->insert_stmt->bindValue(':' . $col, (int) $val, SQLITE3_INTEGER);
            } else {
                $this->insert_stmt->bindValue(':' . $col, (string) $val, SQLITE3_TEXT);
            }
        }

        $result = $this->insert_stmt->execute();
        if ($result === false) {
            throw new \RuntimeException('SQLite insert failed: ' . $this->db->lastErrorMsg());
        }
        if ($result instanceof \SQLite3Result) {
            $result->finalize();
        }

        $this->insert_count++;
        if ($this->insert_count % self::BATCH_SIZE === 0) {
            $this->exec_or_throw('COMMIT');
            $this->exec_or_throw('BEGIN TRANSACTION');
        }
    }

    public function finalize(): void {
        $this->exec_or_throw('COMMIT');

        // Phase 3: create indexes AFTER all inserts (much faster than during).
        $this->exec_or_throw('CREATE INDEX IF NOT EXISTS idx_timecreated   ON logs(timecreated)');
        $this->exec_or_throw('CREATE INDEX IF NOT EXISTS idx_userid        ON logs(userid)');
        $this->exec_or_throw('CREATE INDEX IF NOT EXISTS idx_courseid      ON logs(courseid)');
        $this->exec_or_throw('CREATE INDEX IF NOT EXISTS idx_eventname     ON logs(eventname)');
        $this->exec_or_throw('CREATE INDEX IF NOT EXISTS idx_relateduserid ON logs(relateduserid)');

        // Compact and reorganize B-trees.
        $this->exec_or_throw('VACUUM');

        // Restore safe settings before closing.
        $this->exec_or_throw('PRAGMA journal_mode = DELETE');
        $this->exec_or_throw('PRAGMA synchronous = NORMAL');

        $this->db->close();
        unset($this->db);

        // Make file read-only to prevent accidental modification.
        chmod($this->filepath, 0444);
    }

    public function destroy(): void {
        if (isset($this->db)) {
            try {
                $this->db->exec('ROLLBACK');
            } catch (\Throwable $e) {
                // Ignore.
            }
            $this->db->close();
            unset($this->db);
        }
        if (file_exists($this->filepath)) {
            @unlink($this->filepath);
        }
    }

    public function get_format_name(): string {
        return config::BACKUP_FORMAT_DB;
    }

    public function __destruct() {
        // If finalize() was not called (e.g. exception), clean up.
        if (isset($this->db)) {
            $this->destroy();
        }
    }

    private function exec_or_throw(string $sql): void {
        if (!$this->db->exec($sql)) {
            throw new \RuntimeException('SQLite exec failed: ' . $this->db->lastErrorMsg());
        }
    }
}

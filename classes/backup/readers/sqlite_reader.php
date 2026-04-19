<?php namespace tool_stdlogarchiver\backup\readers;

use \Generator;

class sqlite_reader implements reader_interface {

    private string $filepath;
    private ?\SQLite3 $db = null;

    public function __construct(string $filepath) {
        $this->filepath = $filepath;
    }

    public static function create(string $filepath): reader_interface {
        return new static($filepath);
    }

    private function open(): \SQLite3 {
        if ($this->db === null) {
            $flags = SQLITE3_OPEN_READONLY;

            // immutable=1 with URI open is preferred when supported, but some
            // PHP builds do not expose SQLITE3_OPEN_URI.
            if (defined('SQLITE3_OPEN_URI')) {
                $this->db = new \SQLite3(
                    'file:' . $this->filepath . '?immutable=1',
                    $flags | SQLITE3_OPEN_URI
                );
            } else {
                $this->db = new \SQLite3($this->filepath, $flags);
            }
        }
        return $this->db;
    }

    public function get_contents(): array {
        $db     = $this->open();
        $result = $db->query('SELECT * FROM logs ORDER BY id ASC');
        $rows   = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = (object) $row;
        }
        $result->finalize();
        return $rows;
    }

    public function get_contents_generator(): Generator {
        $db     = $this->open();
        $result = $db->query('SELECT * FROM logs ORDER BY id ASC');
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            yield (object) $row;
        }
        $result->finalize();
    }

    /**
     * Run a filtered SQL query inside the SQLite file.
     * All filters translate directly to SQL — no PHP-level iteration.
     *
     * @param int   $starttime Unix timestamp (inclusive)
     * @param int   $endtime   Unix timestamp (inclusive)
     * @param array $filters   Optional: userid, courseid, eventname, relateduserid, origin
     * @return Generator<object>
     */
    public function search(int $starttime, int $endtime, array $filters = [], ?int $limit = null): Generator {
        $db = $this->open();

        $where    = ['timecreated >= :starttime', 'timecreated <= :endtime'];
        $bindings = [
            ':starttime' => [$starttime, SQLITE3_INTEGER],
            ':endtime'   => [$endtime,   SQLITE3_INTEGER],
        ];

        if (!empty($filters['userid'])) {
            $where[]               = 'userid = :userid';
            $bindings[':userid']   = [(int) $filters['userid'], SQLITE3_INTEGER];
        }
        if (!empty($filters['courseid'])) {
            $where[]                = 'courseid = :courseid';
            $bindings[':courseid']  = [(int) $filters['courseid'], SQLITE3_INTEGER];
        }
        if (!empty($filters['relateduserid'])) {
            $where[]                     = 'relateduserid = :relateduserid';
            $bindings[':relateduserid']  = [(int) $filters['relateduserid'], SQLITE3_INTEGER];
        }
        if (!empty($filters['eventname'])) {
            $eventname = '\\' . ltrim($filters['eventname'], '\\');
            $where[]                  = 'eventname = :eventname';
            $bindings[':eventname']   = [$eventname, SQLITE3_TEXT];
        }
        if (!empty($filters['origin'])) {
            $where[]              = 'origin = :origin';
            $bindings[':origin']  = [$filters['origin'], SQLITE3_TEXT];
        }

        $sql  = 'SELECT * FROM logs WHERE ' . implode(' AND ', $where) . ' ORDER BY timecreated ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(0, (int) $limit);
        }
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return;
        }
        foreach ($bindings as $name => [$value, $type]) {
            $stmt->bindValue($name, $value, $type);
        }

        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            yield (object) $row;
        }
        $result->finalize();
    }

    public function __destruct() {
        if ($this->db !== null) {
            $this->db->close();
            $this->db = null;
        }
    }
}

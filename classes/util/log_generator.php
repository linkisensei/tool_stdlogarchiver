<?php namespace tool_stdlogarchiver\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Utility to create synthetic records directly in the standard logstore table.
 *
 * This is intentionally lower-level than triggering events. It is useful for
 * tests and manual data generation where we need precise timestamps and volume.
 */
class log_generator {

    public const EVENT_TYPE_LOGIN_FAILED = 'login_failed';
    public const EVENT_TYPE_COURSE_VIEWED = 'course_viewed';
    public const EVENT_TYPE_USER_CREATED = 'user_created';
    public const EVENT_TYPE_USER_UPDATED = 'user_updated';

    private const DEFAULT_EVENT_CLASS = '\core\event\user_login_failed';
    private const DEFAULT_BATCH_SIZE = 500;
    private const EVENT_TYPE_MAP = [
        self::EVENT_TYPE_LOGIN_FAILED => self::DEFAULT_EVENT_CLASS,
        self::EVENT_TYPE_COURSE_VIEWED => '\core\event\course_viewed',
        self::EVENT_TYPE_USER_CREATED => '\core\event\user_created',
        self::EVENT_TYPE_USER_UPDATED => '\core\event\user_updated',
    ];

    /**
     * Insert synthetic records into the standard logstore table.
     *
     * Supported options:
     * - eventtype: string preset name
     * - userid: int
     * - starttime: int
     * - timestep: int (seconds added to each subsequent row; may be negative)
     * - eventclass: string
     * - origin: string
     * - courseid: int
     * - relateduserid: int|null
     * - context: \context
     * - other: array
     * - batchsize: int
     * - random: bool
     *
     * @return int Number of inserted rows.
     */
    public static function insert_synthetic_logs(int $count, array $options = []): int {
        global $DB;

        if ($count <= 0) {
            return 0;
        }

        $table = standard_logstore::instance()->get_logstore_table();
        $userid = (int) ($options['userid'] ?? 0);
        $starttime = (int) ($options['starttime'] ?? time());
        $timestep = (int) ($options['timestep'] ?? 0);
        $batchsize = max(1, (int) ($options['batchsize'] ?? self::DEFAULT_BATCH_SIZE));
        $random = !empty($options['random']);
        $randomuserids = $random ? self::get_random_user_ids() : [];
        $randomeventtypes = $random ? array_keys(self::EVENT_TYPE_MAP) : [];

        $template = $random ? null : self::build_template($userid, $options);
        $inserted = 0;

        while ($inserted < $count) {
            $rows = [];
            $remaining = $count - $inserted;
            $currbatch = min($batchsize, $remaining);

            for ($i = 0; $i < $currbatch; $i++) {
                $offset = $inserted + $i;
                if ($random) {
                    $rowoptions = self::build_random_options($options, $randomuserids, $randomeventtypes);
                    $row = (object) self::build_template((int) $rowoptions['userid'], $rowoptions);
                } else {
                    $row = (object) $template;
                }
                $row->timecreated = $starttime + ($offset * $timestep);
                $rows[] = $row;
            }

            $DB->insert_records($table, $rows);
            $inserted += $currbatch;
        }

        return $inserted;
    }

    /**
     * Build a reusable row template based on a real event definition.
     *
     * @return array<string, mixed>
     */
    public static function build_template(int $userid, array $options = []): array {
        $eventclass = self::resolve_event_class($options);
        $courseid = isset($options['courseid']) ? (int) $options['courseid'] : SITEID;
        $targetuserid = (int) ($options['targetuserid'] ?? $userid);
        $relateduserid = array_key_exists('relateduserid', $options)
            ? ($options['relateduserid'] === null ? null : (int) $options['relateduserid'])
            : null;
        $origin = (string) ($options['origin'] ?? 'cli');
        $other = $options['other'] ?? [];

        $eventdata = self::build_event_data($eventclass, $userid, $courseid, $targetuserid, $relateduserid, $other, $options);

        if ($eventclass === self::DEFAULT_EVENT_CLASS) {
            $user = \core_user::get_user($userid, '*', MUST_EXIST);
            $eventdata['other'] = array_merge([
                'username' => $user->username,
                'reason' => 'synthetic-log-generator',
            ], $other);
        } else if (!empty($other)) {
            $eventdata['other'] = $other;
        }

        $event = $eventclass::create($eventdata);
        $entry = $event->get_data();

        $entry['other'] = self::format_other($entry['other'] ?? null);
        $entry['origin'] = $origin;
        $entry['ip'] = $options['ip'] ?? null;
        $entry['realuserid'] = $options['realuserid'] ?? null;
        unset($entry['id']);

        return $entry;
    }

    /**
     * Available event-type presets for synthetic generation.
     *
     * @return array<string, string>
     */
    public static function get_event_type_map(): array {
        return self::EVENT_TYPE_MAP;
    }

    private static function resolve_event_class(array $options): string {
        if (!empty($options['eventclass'])) {
            return (string) $options['eventclass'];
        }

        $eventtype = (string) ($options['eventtype'] ?? self::EVENT_TYPE_LOGIN_FAILED);
        return self::EVENT_TYPE_MAP[$eventtype] ?? self::DEFAULT_EVENT_CLASS;
    }

    /**
     * Build event::create() payload respecting each event class contract.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function build_event_data(
        string $eventclass,
        int $userid,
        int $courseid,
        int $targetuserid,
        ?int $relateduserid,
        array $other,
        array $options
    ): array {
        if ($eventclass === self::DEFAULT_EVENT_CLASS) {
            return [
                'userid' => $userid,
            ];
        }

        if ($eventclass === self::EVENT_TYPE_MAP[self::EVENT_TYPE_COURSE_VIEWED]) {
            return [
                'context' => $options['context'] ?? \context_course::instance($courseid),
                'userid' => $userid,
                'courseid' => $courseid,
                'relateduserid' => $relateduserid,
            ];
        }

        if ($eventclass === self::EVENT_TYPE_MAP[self::EVENT_TYPE_USER_CREATED]
                || $eventclass === self::EVENT_TYPE_MAP[self::EVENT_TYPE_USER_UPDATED]) {
            return [
                'context' => $options['context'] ?? \context_user::instance($targetuserid),
                'userid' => $userid,
                'objectid' => $targetuserid,
                'relateduserid' => $targetuserid,
            ];
        }

        return [
            'context' => $options['context'] ?? \context_system::instance(),
            'userid' => $userid,
            'courseid' => $courseid,
            'relateduserid' => $relateduserid,
        ];
    }

    /**
     * Build a per-row option set for randomized synthetic data generation.
     *
     * @param array<string, mixed> $options
     * @param int[] $userids
     * @param string[] $eventtypes
     * @return array<string, mixed>
     */
    private static function build_random_options(array $options, array $userids, array $eventtypes): array {
        $userid = self::pick_random_value($userids, (int) ($options['userid'] ?? 0));
        $eventtype = self::pick_random_value($eventtypes, self::EVENT_TYPE_LOGIN_FAILED);
        $targetuserid = $userid;

        if ($eventtype === self::EVENT_TYPE_USER_CREATED || $eventtype === self::EVENT_TYPE_USER_UPDATED) {
            $targetuserid = self::pick_random_value($userids, $userid);
        }

        $rowoptions = $options;
        $rowoptions['userid'] = $userid;
        $rowoptions['targetuserid'] = $targetuserid;
        $rowoptions['eventtype'] = $eventtype;
        $rowoptions['eventclass'] = '';

        return $rowoptions;
    }

    /**
     * @return int[]
     */
    private static function get_random_user_ids(): array {
        global $DB;

        $userids = $DB->get_fieldset_select('user', 'id', 'deleted = 0');
        $userids = array_map('intval', $userids);

        if (empty($userids)) {
            $admin = get_admin();
            return [(int) $admin->id];
        }

        return $userids;
    }

    /**
     * @template T
     * @param T[] $values
     * @param T $fallback
     * @return T
     */
    private static function pick_random_value(array $values, mixed $fallback): mixed {
        if (empty($values)) {
            return $fallback;
        }

        return $values[array_rand($values)];
    }

    /**
     * Encode the "other" payload exactly like logstore_standard expects it.
     */
    private static function format_other(mixed $other): mixed {
        if ($other === null || $other === '') {
            return null;
        }

        if (standard_logstore::uses_json_for_others_column()) {
            return is_string($other) ? $other : json_encode($other);
        }

        return is_string($other) ? $other : serialize($other);
    }
}

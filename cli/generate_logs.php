<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Generate synthetic standard logstore rows for manual plugin testing.
 *
 * @package    tool_stdlogarchiver
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$long = [
    'help' => false,
    'random' => false,
    'count' => 100,
    'userid' => null,
    'targetuserid' => null,
    'daysago' => 365,
    'timestep' => 60,
    'origin' => 'cli',
    'courseid' => SITEID,
    'eventtype' => \tool_stdlogarchiver\util\log_generator::EVENT_TYPE_LOGIN_FAILED,
    'eventclass' => '',
    'batchsize' => 500,
];
$short = [
    'h' => 'help',
    'n' => 'count',
    'u' => 'userid',
];

[$options, $unrecognized] = cli_get_params($long, $short);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if (!empty($options['help'])) {
    $help = <<<HELP
Generate synthetic rows directly in logstore_standard_log for manual testing.

Options:
-h, --help              Show this help
    --random           Randomize event presets and users per inserted row
-n, --count             Number of rows to insert (default: 100)
-u, --userid            User id to attribute the logs to (default: site admin)
    --targetuserid      Target user for user_created/user_updated presets (default: same as userid)
    --daysago           Start timestamp offset in days (default: 365)
    --timestep          Seconds between inserted rows (default: 60)
    --origin            Value for the origin column (default: cli)
    --courseid          Course id to attribute to the logs (default: SITEID)
    --eventtype         Preset event type: login_failed, course_viewed, user_created, user_updated
    --eventclass        Event class used as a template (default: \\core\\event\\user_login_failed)
    --batchsize         Insert batch size (default: 500)

Examples:
  php admin/tool/stdlogarchiver/cli/generate_logs.php --count=500 --daysago=400
  php admin/tool/stdlogarchiver/cli/generate_logs.php --count=2000 --daysago=800 --timestep=86400

HELP;
    echo $help;
    exit(0);
}

$userid = empty($options['userid']) ? (int) get_admin()->id : (int) $options['userid'];
$user = \core_user::get_user($userid, 'id,username', MUST_EXIST);
$targetuserid = empty($options['targetuserid']) ? $userid : (int) $options['targetuserid'];
$count = max(0, (int) $options['count']);
$daysago = max(0, (int) $options['daysago']);
$starttime = time() - ($daysago * DAYSECS);
$eventclass = !empty($options['eventclass'])
    ? (string) $options['eventclass']
    : (\tool_stdlogarchiver\util\log_generator::get_event_type_map()[(string) $options['eventtype']]
        ?? '\core\event\user_login_failed');

set_config('enabled_stores', 'logstore_standard', 'tool_log');
set_config('buffersize', 0, 'logstore_standard');
get_log_manager(true);

$inserted = \tool_stdlogarchiver\util\log_generator::insert_synthetic_logs($count, [
    'random' => !empty($options['random']),
    'userid' => $userid,
    'targetuserid' => $targetuserid,
    'starttime' => $starttime,
    'timestep' => (int) $options['timestep'],
    'origin' => (string) $options['origin'],
    'courseid' => (int) $options['courseid'],
    'eventtype' => (string) $options['eventtype'],
    'eventclass' => (string) $options['eventclass'],
    'batchsize' => (int) $options['batchsize'],
]);

echo "Inserted {$inserted} synthetic log row(s).\n";
echo "Randomized: " . (!empty($options['random']) ? 'yes' : 'no') . "\n";
echo "User: {$user->username} (#{$user->id})\n";
echo "Target user id: {$targetuserid}\n";
echo "Start time: " . userdate($starttime, '%Y-%m-%d %H:%M:%S') . "\n";
echo "Time step: " . (int) $options['timestep'] . " second(s)\n";
echo "Event type: " . (string) $options['eventtype'] . "\n";
echo "Event class: {$eventclass}\n";

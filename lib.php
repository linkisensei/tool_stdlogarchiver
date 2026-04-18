<?php

/**
 * Notify the admin if the core logstore_standard has its own log lifetime configured,
 * which would conflict with this plugin's archiving.
 */
function tool_stdlogarchiver_notify_logstore_standard_archive_task_active(): void {
    $loglifetime = (int) get_config('logstore_standard', 'loglifetime');
    if ($loglifetime > 0) {
        $data = (object) [
            'configname'  => get_string('loglifetime', 'core_admin'),
            'configvalue' => get_string('numdays', '', $loglifetime),
            'url'         => (new moodle_url('/admin/settings.php', ['section' => 'logsettingstandard']))->out(),
        ];
        \core\notification::warning(
            get_string('warning:logstore_standard_archive_task_active', 'tool_stdlogarchiver', $data)
        );
    }
}

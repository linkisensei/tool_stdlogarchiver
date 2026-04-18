<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_tool_stdlogarchiver_upgrade(int $oldversion): bool {
    global $DB, $CFG;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024120101) {
        // Drop old ext_bkps table entirely (replaced by columns on backups table).
        $ext_table = new xmldb_table('tool_stdlogarchiver_ext_bkps');
        if ($dbman->table_exists($ext_table)) {
            $dbman->drop_table($ext_table);
        }

        $table = new xmldb_table('tool_stdlogarchiver_backups');

        // Remove pathnamehash (no longer using Moodle file storage).
        $field = new xmldb_field('pathnamehash');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Remove old `deleted` boolean; replace with `deleted_at` timestamp.
        $deleted_field = new xmldb_field('deleted');
        $deleted_at_field = new xmldb_field('deleted_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        if ($dbman->field_exists($table, $deleted_at_field) === false) {
            $dbman->add_field($table, $deleted_at_field);
        }

        // Migrate deleted=1 rows to deleted_at=now (approximate).
        if ($dbman->field_exists($table, $deleted_field)) {
            $DB->execute(
                "UPDATE {tool_stdlogarchiver_backups} SET deleted_at = :ts WHERE deleted = 1 AND deleted_at = 0",
                ['ts' => time()]
            );
            $dbman->drop_field($table, $deleted_field);
        }

        // Add local_path column.
        $local_path = new xmldb_field('local_path', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $local_path)) {
            $dbman->add_field($table, $local_path);
        }

        // Add external_service column.
        $ext_service = new xmldb_field('external_service', XMLDB_TYPE_CHAR, '40', null, null, null, null);
        if (!$dbman->field_exists($table, $ext_service)) {
            $dbman->add_field($table, $ext_service);
        }

        // Add external_uri column.
        $ext_uri = new xmldb_field('external_uri', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $ext_uri)) {
            $dbman->add_field($table, $ext_uri);
        }

        // Add external_customdata column.
        $ext_cd = new xmldb_field('external_customdata', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $ext_cd)) {
            $dbman->add_field($table, $ext_cd);
        }

        // Add indexes.
        $index1 = new xmldb_index('idx_starttime_endtime', XMLDB_INDEX_NOTUNIQUE, ['starttime', 'endtime']);
        if (!$dbman->index_exists($table, $index1)) {
            $dbman->add_index($table, $index1);
        }

        $index2 = new xmldb_index('idx_deleted_at', XMLDB_INDEX_NOTUNIQUE, ['deleted_at']);
        if (!$dbman->index_exists($table, $index2)) {
            $dbman->add_index($table, $index2);
        }

        $index3 = new xmldb_index('idx_fileformat', XMLDB_INDEX_NOTUNIQUE, ['fileformat']);
        if (!$dbman->index_exists($table, $index3)) {
            $dbman->add_index($table, $index3);
        }

        // Create backup directory.
        $backup_dir = $CFG->dataroot . '/tool_stdlogarchiver';
        if (!is_dir($backup_dir)) {
            make_writable_directory($backup_dir);
            file_put_contents($backup_dir . '/.htaccess', "deny from all\n");
        }

        // Migrate old config keys.
        $old_val = (int) get_config('tool_stdlogarchiver', 'records_per_file');
        if ($old_val > 0 && !get_config('tool_stdlogarchiver', 'max_records_per_file')) {
            set_config('max_records_per_file', max($old_val, 100000), 'tool_stdlogarchiver');
        }
        unset_config('records_per_file', 'tool_stdlogarchiver');
        unset_config('delete_local_after_external_backup', 'tool_stdlogarchiver');

        upgrade_plugin_savepoint(true, 2024120101, 'tool', 'stdlogarchiver');
    }

    return true;
}

<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $ADMIN->add('tools', new admin_category(
        'stdlogarchiver',
        new lang_string('settings:settings_category', 'tool_stdlogarchiver')
    ));

    $settingspage = new admin_settingpage(
        'tool_stdlogarchiver_main_settings',
        new lang_string('settings:title', 'tool_stdlogarchiver'),
        'tool/stdlogarchiver:config'
    );

    $ADMIN->add('stdlogarchiver', new admin_externalpage(
        'backups_list',
        new lang_string('listbackups:title', 'tool_stdlogarchiver'),
        $CFG->wwwroot . '/admin/tool/stdlogarchiver/index.php',
        'tool/stdlogarchiver:view',
        false,
        \context_system::instance()
    ));

    $ADMIN->add('stdlogarchiver', new admin_externalpage(
        'search_backups',
        new lang_string('searchbackups:title', 'tool_stdlogarchiver'),
        $CFG->wwwroot . '/admin/tool/stdlogarchiver/search/index.php',
        'tool/stdlogarchiver:view',
        false,
        \context_system::instance()
    ));

    require_once(__DIR__ . '/lib.php');
    tool_stdlogarchiver_notify_logstore_standard_archive_task_active();

    if ($ADMIN->fulltree) {

        // ── General ─────────────────────────────────────────────────────────

        $settingspage->add(new admin_setting_heading(
            'tool_stdlogarchiver/general_header',
            new lang_string('settings:general_header', 'tool_stdlogarchiver'),
            ''
        ));

        $settingspage->add(new admin_setting_configcheckbox(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_ENABLED,
            new lang_string('enable'),
            '',
            1
        ));

        $settingspage->add(new admin_setting_configselect(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_BACKUP_FORMAT,
            new lang_string('settings:backup_format', 'tool_stdlogarchiver'),
            new lang_string('settings:backup_format_desc', 'tool_stdlogarchiver'),
            \tool_stdlogarchiver\config::BACKUP_FORMAT_DB,
            [
                \tool_stdlogarchiver\config::BACKUP_FORMAT_DB  => 'SQLite (.db) — searchable',
                \tool_stdlogarchiver\config::BACKUP_FORMAT_CSV => 'CSV (.csv) — archive only',
            ]
        ));

        $settingspage->add(new admin_setting_configselect(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_MAX_RECORDS_PER_FILE,
            new lang_string('settings:max_records_per_file', 'tool_stdlogarchiver'),
            new lang_string('settings:max_records_per_file_desc', 'tool_stdlogarchiver'),
            200000,
            [
                50000  => '50,000',
                100000 => '100,000',
                200000 => '200,000',
                500000 => '500,000',
            ]
        ));

        $settingspage->add(new admin_setting_configduration(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_LOG_LIFETIME,
            new lang_string('settings:log_lifetime', 'tool_stdlogarchiver'),
            new lang_string('settings:log_lifetime_desc', 'tool_stdlogarchiver'),
            26 * WEEKSECS,
            WEEKSECS
        ));

        // ── Storage paths ────────────────────────────────────────────────────

        $settingspage->add(new admin_setting_heading(
            'tool_stdlogarchiver/storage_header',
            new lang_string('settings:storage_header', 'tool_stdlogarchiver'),
            ''
        ));

        $settingspage->add(new admin_setting_configtext(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_BACKUP_DIR,
            new lang_string('settings:backup_dir', 'tool_stdlogarchiver'),
            new lang_string('settings:backup_dir_desc', 'tool_stdlogarchiver'),
            '',
            PARAM_TEXT
        ));

        $settingspage->add(new admin_setting_configtext(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_CACHE_DIR,
            new lang_string('settings:cache_dir', 'tool_stdlogarchiver'),
            new lang_string('settings:cache_dir_desc', 'tool_stdlogarchiver'),
            '',
            PARAM_TEXT
        ));

        $settingspage->add(new admin_setting_configduration(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_CACHE_TTL,
            new lang_string('settings:cache_ttl', 'tool_stdlogarchiver'),
            new lang_string('settings:cache_ttl_desc', 'tool_stdlogarchiver'),
            86400,
            3600
        ));

        // ── Retention & purge ────────────────────────────────────────────────

        $settingspage->add(new admin_setting_heading(
            'tool_stdlogarchiver/retention_header',
            new lang_string('settings:retention_header', 'tool_stdlogarchiver'),
            ''
        ));

        $settingspage->add(new admin_setting_configduration(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_BACKUP_RETENTION_TTL,
            new lang_string('settings:backup_retention_ttl', 'tool_stdlogarchiver'),
            new lang_string('settings:backup_retention_ttl_desc', 'tool_stdlogarchiver'),
            0,
            WEEKSECS
        ));

        // ── External storage ─────────────────────────────────────────────────

        $settingspage->add(new admin_setting_heading(
            'tool_stdlogarchiver/external_header',
            new lang_string('settings:external_header', 'tool_stdlogarchiver'),
            ''
        ));

        $options = ['' => new lang_string('none')];
        foreach (\tool_stdlogarchiver\config::get_external_backup_services() as $key => $class) {
            $options[$key] = new lang_string('external_service:' . $key, 'tool_stdlogarchiver');
        }

        $settingspage->add(new admin_setting_configselect(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_EXTERNAL_BACKUP_SERVICE,
            new lang_string('settings:external_backup_service', 'tool_stdlogarchiver'),
            new lang_string('settings:external_backup_service_desc', 'tool_stdlogarchiver'),
            '',
            $options
        ));

        $settingspage->add(new admin_setting_configduration(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_EXTERNAL_MIGRATION_DELAY,
            new lang_string('settings:external_migration_delay', 'tool_stdlogarchiver'),
            new lang_string('settings:external_migration_delay_desc', 'tool_stdlogarchiver'),
            0,
            DAYSECS
        ));

        $settingspage->add(new admin_setting_configcheckbox(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_DELETE_LOCAL_AFTER_EXTERNAL,
            new lang_string('settings:delete_local_after_external', 'tool_stdlogarchiver'),
            new lang_string('settings:delete_local_after_external_desc', 'tool_stdlogarchiver'),
            0
        ));

        // ── AWS / S3 ─────────────────────────────────────────────────────────

        $settingspage->add(new admin_setting_heading(
            'tool_stdlogarchiver/aws_header',
            new lang_string('settings:aws_s3_header', 'tool_stdlogarchiver'),
            ''
        ));

        $settingspage->add(new admin_setting_configtext(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_AWS_REGION,
            new lang_string('settings:aws_region', 'tool_stdlogarchiver'),
            '',
            '',
            PARAM_TEXT
        ));

        $settingspage->add(new admin_setting_configtext(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_AWS_KEY,
            new lang_string('settings:aws_key', 'tool_stdlogarchiver'),
            '',
            '',
            PARAM_TEXT
        ));

        $settingspage->add(new admin_setting_configpasswordunmask(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_AWS_SECRET,
            new lang_string('settings:aws_secret', 'tool_stdlogarchiver'),
            '',
            ''
        ));

        $settingspage->add(new admin_setting_configtext(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_S3_BUCKET,
            new lang_string('settings:s3_bucket', 'tool_stdlogarchiver'),
            '',
            '',
            PARAM_TEXT
        ));

        $settingspage->add(new admin_setting_configtext(
            'tool_stdlogarchiver/' . \tool_stdlogarchiver\config::CONFIG_S3_FOLDER,
            new lang_string('settings:s3_folder', 'tool_stdlogarchiver'),
            '',
            'backups',
            PARAM_TEXT
        ));
    }

    $ADMIN->add('stdlogarchiver', $settingspage);
}

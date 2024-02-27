<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    // Creating a new category
    $ADMIN->add(
        'tools',
        new admin_category(
            'stdlogarchiver',
            new lang_string('settings:settings_category', 'tool_stdlogarchiver')
        )
    );

    // Main settings page
    $settingspage = new admin_settingpage(
        'tool_stdlogarchiver_main_settings',
        new lang_string('settings:title', 'tool_stdlogarchiver'),
        'tool/stdlogarchiver:config'
    );

    // List of backups
    $listbackups = new admin_externalpage(
        'backups_list',
        new lang_string('listbackups:title', 'tool_stdlogarchiver'),
        $CFG->wwwroot . '/admin/tool/stdlogarchiver/index.php',
        'local/trails:config',
        false,
        \context_system::instance()
    );
    $ADMIN->add('stdlogarchiver', $listbackups);

    // Search on backups
    $searchbackups = new admin_externalpage(
        'search_backups',
        new lang_string('searchbackups:title', 'tool_stdlogarchiver'),
        $CFG->wwwroot . '/admin/tool/stdlogarchiver/search/index.php',
        'local/trails:config',
        false,
        \context_system::instance()
    );
    $ADMIN->add('stdlogarchiver', $searchbackups);
    

    require_once(__DIR__ . '/lib.php');
    tool_stdlogarchiver_notify_logstore_standard_archive_task_active();


    if ($ADMIN->fulltree) {
        // Enabled
        $setting_name = \tool_stdlogarchiver\config::CONFIG_ENABLED;
        $settingspage->add(new admin_setting_configcheckbox(
            "tool_stdlogarchiver/$setting_name",
            new lang_string('enable'),
            "",
            1
        ));

        // Records per file
        $setting_name = \tool_stdlogarchiver\config::CONFIG_RECORDS_PER_FILE;
        $setting = new admin_setting_configselect(
            "tool_stdlogarchiver/$setting_name",
            new lang_string("settings:$setting_name", 'tool_stdlogarchiver'),
            new lang_string("settings:$setting_name" . '_desc', 'tool_stdlogarchiver'),
            25000,
            [
                5000 => "5000",
                10000 => "10000",
                25000 => "25000",
                50000 => "50000",
                100000 => "100000",
            ]
        );
        $settingspage->add($setting);

        // Backup format
        $setting_name = \tool_stdlogarchiver\config::CONFIG_BACKUP_FORMAT;
        $setting = new admin_setting_configselect(
            "tool_stdlogarchiver/$setting_name",
            new lang_string("settings:$setting_name", 'tool_stdlogarchiver'),
            new lang_string("settings:$setting_name" . '_desc', 'tool_stdlogarchiver'),
            \tool_stdlogarchiver\config::BACKUP_FORMAT_CSV,
            [
                \tool_stdlogarchiver\config::BACKUP_FORMAT_CSV => "CSV",
            ]
        );
        $settingspage->add($setting);

        // Log Lifetime
        $setting_name = \tool_stdlogarchiver\config::CONFIG_LOG_LIFETIME;
        $setting = new admin_setting_configduration(
            "tool_stdlogarchiver/$setting_name",
            new lang_string("settings:$setting_name", 'tool_stdlogarchiver'),
            new lang_string("settings:$setting_name" . '_desc', 'tool_stdlogarchiver'),
            26 * WEEKSECS,
            WEEKSECS
        );
        $settingspage->add($setting);

        // External backup service
        $options = [null => new lang_string('none')];
        foreach (\tool_stdlogarchiver\config::get_external_backup_services() as $key => $value) {
            $options[$key] = new lang_string("external_service:$key", 'tool_stdlogarchiver');
        }

        $setting_name = \tool_stdlogarchiver\config::CONFIG_EXTERNAL_BACKUP_SERVICE;
        $setting = new admin_setting_configselect(
            "tool_stdlogarchiver/$setting_name",
            new lang_string("settings:$setting_name", 'tool_stdlogarchiver'),
            new lang_string("settings:$setting_name" . '_desc', 'tool_stdlogarchiver'),
            null,
            $options
        );
        $settingspage->add($setting);

        // Delete local files after uploading to the external service
        $setting_name = \tool_stdlogarchiver\config::CONFIG_DELETE_LOCAL_AFTER_EXTERNAL_BACKUP;
        $settingspage->add(new admin_setting_configcheckbox(
            "tool_stdlogarchiver/$setting_name",
            new lang_string("settings:$setting_name", 'tool_stdlogarchiver'),
            new lang_string("settings:$setting_name" . '_desc', 'tool_stdlogarchiver'),
            0
        ));

        /* AWS Header */
        $settingspage->add(
            new admin_setting_heading(
                'tool_stdlogarchiver/tool_stdlogarchiver_aws_header',
                new lang_string('settings:aws_s3_header', 'tool_stdlogarchiver'),
                ''
            )
        );

        // AWS Region
        $setting_name = \tool_stdlogarchiver\config::CONFIG_AWS_REGION;
        $setting = new admin_setting_configtext(
            "tool_stdlogarchiver/$setting_name",
            new lang_string("settings:$setting_name", 'tool_stdlogarchiver'),
            '',
            '',
            PARAM_TEXT
        );
        $settingspage->add($setting);

        // AWS Key
        $setting_name = \tool_stdlogarchiver\config::CONFIG_AWS_KEY;
        $setting = new admin_setting_configtext(
            "tool_stdlogarchiver/$setting_name",
            new lang_string("settings:$setting_name", 'tool_stdlogarchiver'),
            '',
            '',
            PARAM_TEXT
        );
        $settingspage->add($setting);

        // AWS Secret
        $setting_name = \tool_stdlogarchiver\config::CONFIG_AWS_SECRET;
        $setting = new admin_setting_configtext(
            "tool_stdlogarchiver/$setting_name",
            new lang_string("settings:$setting_name", 'tool_stdlogarchiver'),
            '',
            '',
            PARAM_TEXT
        );
        $settingspage->add($setting);

        // AWS S3 Bucket
        $setting_name = \tool_stdlogarchiver\config::CONFIG_S3_BUCKET;
        $setting = new admin_setting_configtext(
            "tool_stdlogarchiver/$setting_name",
            new lang_string("settings:$setting_name", 'tool_stdlogarchiver'),
            '',
            '',
            PARAM_TEXT
        );
        $settingspage->add($setting);

        // AWS S3 Folder
        $setting_name = \tool_stdlogarchiver\config::CONFIG_S3_FOLDER;
        $setting = new admin_setting_configtext(
            "tool_stdlogarchiver/$setting_name",
            new lang_string("settings:$setting_name", 'tool_stdlogarchiver'),
            '',
            'backups',
            PARAM_TEXT
        );
        $settingspage->add($setting);

    }
    
    $ADMIN->add('stdlogarchiver', $settingspage);
}

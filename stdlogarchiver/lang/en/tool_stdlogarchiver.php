<?php

$string['pluginname'] = "Logstore Archiver";
$string['settings:title'] = "Logstore Archiver settings";
$string['settings:records_per_file'] = "Records per file";
$string['settings:backup_format'] = "Backup format";
$string['settings:log_lifetime'] = "Lifetime";
$string['settings:aws_region'] = "AWS Region";
$string['settings:aws_key'] = "Key";
$string['settings:aws_secret'] = "Secret";
$string['settings:s3_bucket'] = "S3 Bucket";
$string['settings:s3_folder'] = "Directory";
$string['settings:external_backup_service'] = "External backup service";
$string['settings:delete_local_after_external_backup'] = "Keep external backup only";


$string['settings:records_per_file_desc'] = "Maximum number of log records per file";
$string['settings:backup_format_desc'] = "";
$string['settings:log_lifetime_desc'] = "Time until a record is removed from the table stored in the backup file.";
$string['settings:aws_s3_header'] = "AWS Settings";
$string['settings:aws_region_desc'] = "";
$string['settings:aws_key_desc'] = "";
$string['settings:aws_secret_desc'] = "";
$string['settings:s3_bucket_desc'] = "";
$string['settings:s3_folder_desc'] = "";
$string['settings:external_backup_service_desc'] = "";
$string['settings:delete_local_after_external_backup_desc'] = "If enabled, delete the local backup after performing the external backup.";
$string['settings:settings_category'] = "Logstore Archiver";

$string['privacy:metadata:core_files'] = 'Stores backups of records from the standard logstore in moodle data.';
$string['privacy:metadata:s3_bucket'] = 'Backups can be stored in an S3 Bucket on AWS';
$string['privacy:metadata:s3_bucket:userid'] = 'The userid is part of the log records stored in the backup.';

$string['warning:logstore_standard_archive_task_active'] = '"{$a->configname}" is set to "{$a->configvalue}". This may disrupt the operation of the "Logstore Cleaner" plugin. <a href="{$a->url}">Change settings here</a>';

$string['task:cleanup'] = "Backup and log cleaning task";
$string['task:external_backup'] = "External backup synchronization task";

$string['external_service:s3'] = "Amazon S3";

$string['exception:cannot_download_local_file_exists'] = "Unable to download the external file, as the local file already exists";
$string['exception:starttime_is_required'] = "Starttime is required";
$string['exception:endtime_is_required'] = "The end time is required";
$string['exception:search_interval_is_too_long'] = "The interval is too long for the filter. Try filtering a single month";
$string['exception:endtime_lesser_than_starttime'] = "The end date must be later than the start";


$string['searchbackups:title'] = "Search for log records";
$string['listbackups:title'] = "Logstore Backups";

$string['filter:origin'] = "Origin";
$string['filter:userid'] = "User ID";
$string['filter:relateduserid'] = "Related user ID";
$string['filter:courseid'] = "Course ID";

$string['table:id'] = "ID";
$string['table:starttime'] = "Start date";
$string['table:endtime'] = "End date";
$string['table:fileformat'] = "Backup format";
$string['table:timecreated'] = "Date created";
$string['table:external_backup'] = "External backup";
$string['table:restored'] = "Restored";
$string['table:restoring'] = "Restoring in progress";
$string['table:deleted'] = "Deleted";
$string['table:actions'] = "Actions";

$string['action:delete_local_backup'] = "Delete local backup";
$string['action:restore_local_backup'] = "Restore backup";
$string['action:download_local_backup'] = "Download backup";
$string['action:unrestore_local_backup'] = "Delete restored logs";

$string['confirm:delete_local_backup'] = "Are you sure you want to delete this backup? This action is irreversible!";
$string['confirm:restore_local_backup'] = "Are you sure you want to restore this backup? This action can be reversed.";
$string['confirm:unrestore_local_backup'] = "Do you want to reverse restore this backup? The restored data will be deleted.";

$string['exception:backup_not_found'] = "Backup not found!";
$string['exception:backup_deleted'] = "Backup not found!";
$string['delete_action_success'] = "Backup deleted!";
$string['exception:already_restoring'] = "The backup is already being restored.";
$string['exception:already_restored'] = "The backup has already been restored";
$string['restore_action_success'] = "The backup is being restored.";
$string['exception:not_yet_restored'] = "The backup has not yet been restored.";
$string['unrestore_action_success'] = "Backup restore rollback has started.";

$string['search:search_info_title'] = "Information about searched backups";
$string['search:search_info_desc'] = "To avoid timeouts, not all backups found are used in the search. Below is a list of the backups found that were or were not used.";

$string['table:backupid'] = "Backup";
$string['table:firstid'] = "First ID";
$string['table:lastid'] = "Last ID";
$string['table:searched'] = "Searched?";
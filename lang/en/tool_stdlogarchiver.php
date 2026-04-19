<?php

// ── Plugin ────────────────────────────────────────────────────────────────────
$string['pluginname']                   = 'Logstore Archiver';
$string['settings:settings_category']  = 'Logstore Archiver';
$string['settings:title']              = 'Logstore Archiver settings';

// ── Capabilities ──────────────────────────────────────────────────────────────
$string['stdlogarchiver:view']    = 'View logstore backups';
$string['stdlogarchiver:delete']  = 'Delete logstore backups';
$string['stdlogarchiver:restore'] = 'Restore logstore backups';
$string['stdlogarchiver:config']  = 'Configure logstore archiver';

// ── Scheduled task names ──────────────────────────────────────────────────────
$string['task:archive_task_name']           = 'Archive logstore records to files';
$string['task:external_migration_task_name'] = 'Upload local backups to external storage';
$string['task:cache_cleanup_task_name']     = 'Clean up expired local cache files';
$string['task:backup_purge_task_name']      = 'Permanently purge deleted backups';

// ── Settings: General ─────────────────────────────────────────────────────────
$string['settings:general_header']        = 'General';
$string['settings:backup_format']         = 'Backup format';
$string['settings:backup_format_desc']    = 'SQLite (.db) supports full-text search. CSV (.csv) is archive-only.';
$string['settings:max_records_per_file']      = 'Records per file';
$string['settings:max_records_per_file_desc'] = 'Maximum number of log records written to a single backup file. Larger values reduce file count but increase memory usage during archiving.';
$string['settings:log_lifetime']          = 'Log retention period';
$string['settings:log_lifetime_desc']     = 'Records older than this value are archived and removed from the live logstore table.';

// ── Settings: Storage paths ───────────────────────────────────────────────────
$string['settings:storage_header']   = 'Storage paths';
$string['settings:backup_dir']       = 'Backup directory';
$string['settings:backup_dir_desc']  = 'Absolute path to store backup files. Leave empty to use the default directory inside moodledata. Must be writable by the web server.';
$string['settings:cache_dir']        = 'Cache directory';
$string['settings:cache_dir_desc']   = 'Absolute path for temporary local copies of remote backup files. Leave empty to use the default directory inside moodledata.';
$string['settings:cache_ttl']        = 'Cache TTL';
$string['settings:cache_ttl_desc']   = 'How long a locally cached copy of a remote backup file is kept before being deleted by the cache cleanup task.';

// ── Settings: Retention & purge ───────────────────────────────────────────────
$string['settings:retention_header']          = 'Retention & purge';
$string['settings:backup_retention_ttl']      = 'Backup retention period';
$string['settings:backup_retention_ttl_desc'] = 'Backups older than this value are automatically soft-deleted and then permanently purged by the purge task. Set to 0 to disable automatic deletion.';

// ── Settings: External storage ────────────────────────────────────────────────
$string['settings:external_header']                  = 'External storage';
$string['settings:external_backup_service']          = 'External storage service';
$string['settings:external_backup_service_desc']     = 'Upload local backups to an external object storage service after archiving.';
$string['settings:external_migration_delay']         = 'Migration delay';
$string['settings:external_migration_delay_desc']    = 'Minimum age a backup must have before being uploaded to external storage. Set to 0 to upload immediately.';
$string['settings:delete_local_after_external']      = 'Delete local file after upload';
$string['settings:delete_local_after_external_desc'] = 'If enabled, the local backup file is deleted once successfully uploaded to external storage.';

// ── Settings: AWS / S3 ────────────────────────────────────────────────────────
$string['settings:aws_s3_header'] = 'AWS / S3 settings';
$string['settings:aws_region']    = 'AWS Region';
$string['settings:aws_region_desc'] = 'e.g. us-east-1';
$string['settings:aws_key']       = 'Access Key ID';
$string['settings:aws_key_desc']  = '';
$string['settings:aws_secret']    = 'Secret Access Key';
$string['settings:aws_secret_desc'] = '';
$string['settings:s3_bucket']     = 'S3 Bucket';
$string['settings:s3_bucket_desc'] = '';
$string['settings:s3_folder']     = 'S3 Folder';
$string['settings:s3_folder_desc'] = 'Key prefix (folder path) inside the bucket.';

// ── External services ─────────────────────────────────────────────────────────
$string['external_service:s3'] = 'Amazon S3';

// ── Page titles ───────────────────────────────────────────────────────────────
$string['listbackups:title']   = 'Logstore Backups';
$string['searchbackups:title'] = 'Search archived log records';

// ── Backup list table ─────────────────────────────────────────────────────────
$string['table:id']         = 'ID';
$string['table:starttime']  = 'Period start';
$string['table:endtime']    = 'Period end';
$string['table:fileformat'] = 'Format';
$string['table:timecreated'] = 'Created';
$string['table:searchable'] = 'Searchable';
$string['table:external']   = 'External';
$string['table:restored']   = 'Restored';
$string['table:restoring']  = 'Restoring';
$string['table:deleted_at'] = 'Deleted at';
$string['table:actions']    = 'Actions';
$string['table:no_backups'] = 'No backups found.';

// ── Search results table ──────────────────────────────────────────────────────
$string['table:backupid']   = 'Backup';
$string['table:firstid']    = 'First ID';
$string['table:lastid']     = 'Last ID';
$string['table:searched']   = 'Searched?';
$string['table:no_search_results'] = 'No log records found matching the given filters.';

// ── Search filters ────────────────────────────────────────────────────────────
$string['filter:userid']        = 'User ID';
$string['filter:relateduserid'] = 'Related User ID';
$string['filter:courseid']      = 'Course ID';
$string['filter:origin']        = 'Origin';

// ── Search info ───────────────────────────────────────────────────────────────
$string['search:search_info_title'] = 'Searched backups';
$string['search:search_info_desc']  = 'The table below shows which backup files were queried for the given time range.';
$string['search:pending_notice']    = 'Some backups are stored only on external storage and are being downloaded in the background. Please re-run the search in a few minutes to include their results.';
$string['search:skipped_csv_notice'] = '{$a} backup(s) in this time range use the CSV format and cannot be searched. Only SQLite (.db) backups are searchable.';
$string['search:truncated_notice'] = 'Results were limited for {$a} backup(s) to keep the search responsive. Narrow the filters to see more matches.';

// ── Actions ───────────────────────────────────────────────────────────────────
$string['action:delete_local_backup']   = 'Delete backup';
$string['action:restore_local_backup']  = 'Restore backup';
$string['action:download_local_backup'] = 'Download backup';
$string['action:unrestore_local_backup'] = 'Undo restore';

// ── Confirmations ─────────────────────────────────────────────────────────────
$string['confirm:delete_local_backup']   = 'Are you sure you want to delete this backup? The file will be permanently removed.';
$string['confirm:restore_local_backup']  = 'Are you sure you want to restore this backup? The records will be re-inserted into the live logstore.';
$string['confirm:unrestore_local_backup'] = 'Are you sure you want to undo the restore? The previously restored records will be deleted from the logstore.';

// ── Action result messages ────────────────────────────────────────────────────
$string['delete_action_success']    = 'Backup deleted.';
$string['restore_action_success']   = 'Restore queued. The backup will be restored in the background.';
$string['unrestore_action_success'] = 'Undo restore queued. The restored records will be removed in the background.';

// ── Exceptions ────────────────────────────────────────────────────────────────
$string['exception:backup_not_found']               = 'Backup not found.';
$string['exception:backup_deleted']                  = 'This backup has been deleted.';
$string['exception:already_restoring']               = 'This backup is already being restored.';
$string['exception:already_restored']                = 'This backup has already been restored.';
$string['exception:not_yet_restored']                = 'This backup has not been restored yet.';
$string['exception:starttime_is_required']           = 'Start time is required.';
$string['exception:endtime_is_required']             = 'End time is required.';
$string['exception:endtime_lesser_than_starttime']   = 'End time must be later than start time.';
$string['exception:search_interval_is_too_long']     = 'The search interval is too long. Try filtering a shorter period.';
$string['exception:cannot_download_local_file_exists'] = 'Cannot download the external file: a local file already exists.';

$string['backupfilenotavailable'] = 'The backup file is not available locally or in cache. It may need to be downloaded from external storage first.';
$string['noexternalbackup']       = 'This backup has no external storage reference.';
$string['noexternalservice']      = 'No external storage service is configured or enabled.';
$string['externalbackupnotavailable'] = 'The external backup file is not available or could not be verified remotely.';
$string['invalidbackupformat']    = 'Unsupported backup file format.';
$string['localfilenotfound']      = 'The local backup file could not be found on disk.';

// ── Privacy / GDPR ────────────────────────────────────────────────────────────
$string['privacy:metadata:external_storage']        = 'Backup files may be stored in an external object storage service such as Amazon S3.';
$string['privacy:metadata:external_storage:userid'] = 'Log records inside backup files contain the userid of the user who triggered each event.';

// ── Warnings ──────────────────────────────────────────────────────────────────
$string['warning:logstore_standard_archive_task_active'] = '"{$a->configname}" is set to "{$a->configvalue}". This may conflict with the Logstore Archiver plugin. <a href="{$a->url}">Change settings</a>';

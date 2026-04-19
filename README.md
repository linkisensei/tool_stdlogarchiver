# Logstore Archiver

`tool_stdlogarchiver` is a Moodle admin tool that archives old records from `logstore_standard_log` into files stored outside the database. It is designed to keep the main log table smaller without giving up restoration, download, or search capabilities.

The plugin currently supports two output formats:

- `db`: SQLite backups, intended for search and long-term retention
- `csv`: plain CSV backups, intended for archival only

SQLite is the recommended format because it supports indexed search directly inside the backup file.

## Current Scope

- Archives old `logstore_standard_log` records into files grouped by calendar day
- Splits large days into multiple files according to the configured records-per-file limit
- Deletes archived rows from the database after the backup file is successfully written
- Stores backup metadata in `tool_stdlogarchiver_backups`
- Allows restoring archived logs back into `logstore_standard_log`
- Supports searching SQLite backups by common log fields
- Supports optional migration of backups to Amazon S3
- Supports local cache download for remote SQLite backups used in search
- Supports download and soft-delete from the admin interface

## Requirements

- Moodle 4.2 or later
- PHP 8.1 or later
- `logstore_standard` enabled
- SQLite support in PHP for SQLite backup/search features

## Installation

1. Copy the plugin folder to `admin/tool/stdlogarchiver`
2. Visit the Moodle notifications page as an administrator
3. Complete the installation
4. If you plan to use S3, you will have to configure it properly on the plugin settings

## Configuration

After installation, go to:

`Site administration > Tools > Logstore Archiver`

Main settings:

- Enable plugin
- Backup format: `db` or `csv`
- Max records per file
- Log lifetime
- Backup directory
- Cache directory
- Cache TTL
- Backup retention TTL

External storage settings:

- External backup service
- External migration delay
- Delete local backup after external migration
- AWS region
- AWS key
- AWS secret
- S3 bucket
- S3 folder

Important notes:

- `external_migration_delay = 0` means upload immediately
- Search works only for SQLite backups (`db`)
- CSV backups are not searchable
- If a SQLite backup exists only in S3, the plugin can download it to a local cache before searching

## Scheduled Tasks

The plugin uses scheduled and adhoc tasks for its lifecycle:

- `archive_task`: archives old logs into local backup files
- `external_migration_task`: uploads eligible local backups to external storage
- `backup_purge_task`: removes files from local and external storage after soft-delete or retention expiry
- `cache_cleanup_task`: deletes expired cached remote files
- `cache_download_task`: downloads a remote backup into cache for search
- `restore_backup_task`: restores a backup into `logstore_standard_log`
- `unrestore_backup_task`: removes previously restored records from `logstore_standard_log`

## Search

The search interface is available from the plugin area and works against SQLite backups.

You can filter by fields such as:

- time range
- event name
- action
- target
- origin
- CRUD
- user ID
- related user ID
- course ID
- context ID
- object ID

Search behavior:

- only SQLite backups are queried
- each SQLite backup is searched independently
- results are limited per backup to avoid one large file dominating the whole search
- if a backup is remote-only, the plugin can queue an async cache download and return partial results meanwhile

## Backup Files

Files are stored directly in the filesystem, not in Moodle file storage.

Default locations:

- backups: `{moodledata}/tool_stdlogarchiver/`
- cache: `{moodledata}/tool_stdlogarchiver_cache/`

Typical filenames:

- `1704067200_1704153599.db`
- `1704067200_1704153599.csv`

For S3 migration:

- SQLite backups are uploaded as `.db.gz`
- CSV backups are uploaded as `.csv`

## Restoration

Backups can be restored from the admin interface. Restoration reinserts the archived records into `logstore_standard_log` with their original IDs.

The plugin also tracks restored backups so they are not re-archived in later runs.

## Development and Testing

The plugin includes PHPUnit coverage for archiving, restoration, search, and external storage flows.

There is also a CLI helper for generating synthetic logs for manual testing:

```bash
php admin/tool/stdlogarchiver/cli/generate_logs.php --help
```

Examples:

```bash
php admin/tool/stdlogarchiver/cli/generate_logs.php --count=500 --daysago=400
php admin/tool/stdlogarchiver/cli/generate_logs.php --count=500 --daysago=400 --eventtype=course_viewed --courseid=2
php admin/tool/stdlogarchiver/cli/generate_logs.php --count=1000 --daysago=800 --random
```

Supported event presets in the generator:

- `login_failed`
- `course_viewed`
- `user_created`
- `user_updated`

## Notes on AWS SDK

The plugin uses `aws/aws-sdk-php`, but the packaged dependency set is intentionally reduced to keep the plugin smaller. The Composer setup removes unused AWS services and keeps only what is required for S3 and its internal dependencies.

## Status

This plugin is currently marked as `BETA` in `version.php`.

## License

This plugin is distributed under the MIT license.

## Credits

Developer: Lucas Barreto

<?php namespace tool_stdlogarchiver\backup\external;

use \tool_stdlogarchiver\models\backup;

interface external_backup_service_interface {

    public static function is_enabled(): bool;

    public static function get_name(): string;

    /**
     * Best-effort existence check for a remote backup file.
     *
     * Returns false both when the object does not exist and when the check
     * cannot be completed due to remote or credential errors.
     *
     * @param string $external_uri URI returned by upload()
     */
    public function exists(string $external_uri): bool;

    /**
     * Upload a backup file to external storage.
     * SQLite (.db) files must be compressed to .db.gz before uploading.
     *
     * @return string Full URI of the uploaded file (e.g. s3://bucket/path/file.db.gz)
     */
    public function upload(backup $backup): string;

    /**
     * Download a file from external storage to a local path.
     *
     * @param string $external_uri URI returned by upload()
     * @param string $dest_path    Local filesystem path to write to
     */
    public function download_to_path(string $external_uri, string $dest_path): void;

    /**
     * Permanently delete a file from external storage.
     *
     * @param string $external_uri URI of the file to delete
     */
    public function delete(string $external_uri): void;
}

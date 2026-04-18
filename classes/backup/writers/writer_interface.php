<?php namespace tool_stdlogarchiver\backup\writers;

interface writer_interface {

    public function get_format_name(): string;

    public function append(object $row): void;

    /**
     * Finalize and persist the backup file.
     * Called on success — file stays on disk.
     */
    public function finalize(): void;

    /**
     * Abort and clean up. Called on error — deletes the file.
     */
    public function destroy(): void;
}

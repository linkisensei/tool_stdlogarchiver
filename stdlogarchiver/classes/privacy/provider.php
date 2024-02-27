<?php namespace tool_stdlogarchiver\privacy;

defined('MOODLE_INTERNAL') || die();

use \core_privacy\local\metadata\collection;

class provider implements \core_privacy\local\metadata\provider {

    public static function get_metadata(collection $collection): collection {

        $collection->add_subsystem_link(
            'core_files',
            [],
            'privacy:metadata:core_files'
        );

        $collection->add_external_location_link('s3_bucket', [
            'userid' => 'privacy:metadata:s3_bucket:userid',
        ], 'privacy:metadata:s3_bucket');
    
        return $collection;
    }
}
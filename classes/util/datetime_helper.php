<?php namespace tool_stdlogarchiver\util;

defined('MOODLE_INTERNAL') || die();

class datetime_helper {
    public const DISPLAY_FORMAT = '%d/%m/%Y %H:%M:%S';

    public static function format(int $timestamp): string {
        return userdate($timestamp, self::DISPLAY_FORMAT);
    }
}

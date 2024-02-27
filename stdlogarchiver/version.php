<?php

/**
 * @package  Logstore Cleaner
 * @author   Lucas Barreto <lucas.b.fisica@gmail.com>
 * @license  MIT
 */

defined('MOODLE_INTERNAL') || die();
$plugin->version   = 2024011302;
$plugin->requires  = 2020061500; // Moodle 3.9.0
$plugin->component = 'tool_stdlogarchiver';
$plugin->maturity  = MATURITY_BETA;
$plugin->dependencies = [
    'logstore_standard' => ANY_VERSION,
];
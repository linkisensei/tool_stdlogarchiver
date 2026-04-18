<?php

/**
 * @package  tool_stdlogarchiver
 * @author   Lucas Barreto <lucas.barreto@revvo.com.br>
 * @license  MIT
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2024120101;
$plugin->requires  = 2022041900; // Moodle 4.2.0
$plugin->component = 'tool_stdlogarchiver';
$plugin->maturity  = MATURITY_BETA;
$plugin->dependencies = [
    'logstore_standard' => ANY_VERSION,
];

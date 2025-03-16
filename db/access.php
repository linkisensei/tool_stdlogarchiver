<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'tool/stdlogarchiver:view' => [
        'riskbitmask'   => RISK_PERSONAL,
        'captype'       => 'read',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes'    => [
            'manager'   => CAP_ALLOW,
        ],
    ],
    'tool/stdlogarchiver:delete' => [
        'riskbitmask'   => RISK_DATALOSS,
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes'    => [
            'manager'   => CAP_ALLOW,
        ],
    ],
    'tool/stdlogarchiver:config' => [
        'riskbitmask'   => RISK_CONFIG,
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes'    => [
            'manager'   => CAP_ALLOW,
        ],
    ],
    'tool/stdlogarchiver:restore' => [
        'riskbitmask'   => RISK_MANAGETRUST,
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes'    => [
            'manager'   => CAP_ALLOW,
        ],
    ],
];

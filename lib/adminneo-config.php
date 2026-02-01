<?php

defined('MOODLE_INTERNAL') || die;

return [
    'navigationMode' => 'dual',
    'preferSelection' => true,
    'versionVerification' => false,
    'relationLinks' => get_config('local_adminer', 'showrelationlinks') ? 1: 0,
];

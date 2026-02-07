<?php

defined('MOODLE_INTERNAL') || die;

return [
    new \AdminNeo\FrameSupportPlugin(),
    new \local_adminer\adminer_plugins\mdl_design(),
    new \AdminNeo\ZipOutputPlugin(),
    new \local_adminer\adminer_plugins\mdl_foreign_key(),
];

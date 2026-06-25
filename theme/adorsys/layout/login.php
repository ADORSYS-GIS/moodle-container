<?php

defined('MOODLE_INTERNAL') || die();

$bodyattributes = $OUTPUT->body_attributes();

$leftinstructions = !empty($CFG->auth_instructions)
    ? format_text($CFG->auth_instructions, FORMAT_MOODLE, ['context' => context_system::instance()])
    : null;

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'leftinstructions' => $leftinstructions,
    'videourl' => $CFG->wwwroot . '/theme/adorsys/pix/background-website.mp4',
];

echo $OUTPUT->render_from_template('theme_boost/login', $templatecontext);

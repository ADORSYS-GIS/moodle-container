<?php

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');

$THEME->name = 'adorsys';
$THEME->parents = ['boost'];
$THEME->sheets = [];
$THEME->usefallback = true;

$THEME->scss = function($theme) {
    return theme_adorsys_get_main_scss_content($theme);
};

$THEME->layouts = [
    'login' => [
        'file' => 'login.php',
        'regions' => [],
        'options' => ['langmenu' => true],
    ],
];

$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->extrascsscallback = 'theme_adorsys_get_extra_scss';
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->activityheaderconfig = ['notitle' => true];
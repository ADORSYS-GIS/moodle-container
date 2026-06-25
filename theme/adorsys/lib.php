<?php

defined('MOODLE_INTERNAL') || die();

function theme_adorsys_get_main_scss_content($theme) {
    global $CFG;

    $scss = theme_boost_get_main_scss_content($theme);

    $scssfile = $CFG->dirroot . '/theme/adorsys/scss/adorsys.scss';
    if (file_exists($scssfile)) {
        $scss .= file_get_contents($scssfile);
    }

    return $scss;
}

function theme_adorsys_get_extra_scss($theme) {
    return theme_boost_get_extra_scss($theme);
}

function theme_adorsys_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel == CONTEXT_SYSTEM) {
        $theme = theme_config::load('adorsys');
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    send_file_not_found();
}
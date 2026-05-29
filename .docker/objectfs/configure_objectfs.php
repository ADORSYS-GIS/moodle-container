#!/usr/bin/env php
<?php

define('CLI_SCRIPT', true);

require_once('/moodleroot/moodle/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/admin/tool/objectfs/lib.php');

function env_bool(string $name, bool $default = false): bool {
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }
    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

function env_value(string $name, ?string $default = null): ?string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

$pluginmanager = core_plugin_manager::instance();
$plugininfo = $pluginmanager->get_plugin_info('tool_objectfs');

if (!$plugininfo) {
    cli_error('tool_objectfs plugin files are not available in this Moodle instance.');
}

if (!$plugininfo->is_installed_and_upgraded()) {
    cli_error('tool_objectfs is present but not installed/upgraded yet.');
}

set_config('filesystem', '\\tool_objectfs\\s3_file_system', 'tool_objectfs');
set_config('enabletasks', env_bool('OBJECTFS_ENABLE_TASKS', true) ? 1 : 0, 'tool_objectfs');
set_config('preferexternal', env_bool('OBJECTFS_PREFER_EXTERNAL', false) ? 1 : 0, 'tool_objectfs');
set_config('sizethreshold', (int)env_value('OBJECTFS_SIZE_THRESHOLD', '0'), 'tool_objectfs');
set_config('minimumage', (int)env_value('OBJECTFS_MINIMUM_AGE', '0'), 'tool_objectfs');
set_config('deletelocal', env_bool('OBJECTFS_DELETE_LOCAL', true) ? 1 : 0, 'tool_objectfs');
set_config('consistencydelay', (int)env_value('OBJECTFS_CONSISTENCY_DELAY', '60'), 'tool_objectfs');
set_config('deleteexternal', (int)env_value('OBJECTFS_DELETE_EXTERNAL', (string)TOOL_OBJECTFS_DELETE_EXTERNAL_FULL), 'tool_objectfs');
set_config('enablepresignedurls', env_bool('OBJECTFS_ENABLE_PRESIGNED_URLS', false) ? 1 : 0, 'tool_objectfs');
set_config('presignedminfilesize', (int)env_value('OBJECTFS_PRESIGNED_MIN_FILE_SIZE', '0'), 'tool_objectfs');
set_config('expirationtime', (int)env_value('OBJECTFS_PRESIGNED_EXPIRATION', '86400'), 'tool_objectfs');
set_config('signingwhitelist', env_value('OBJECTFS_PRESIGNED_WHITELIST', ''), 'tool_objectfs');
set_config('signingmethod', env_value('OBJECTFS_SIGNING_METHOD', 's3'), 'tool_objectfs');
set_config('s3_key', env_value('OBJECTFS_S3_KEY', ''), 'tool_objectfs');
set_config('s3_secret', env_value('OBJECTFS_S3_SECRET', ''), 'tool_objectfs');
set_config('s3_bucket', env_value('OBJECTFS_S3_BUCKET', ''), 'tool_objectfs');
set_config('s3_bucket_acl', env_value('OBJECTFS_BUCKET_ACL', 'private'), 'tool_objectfs');
set_config('s3_region', env_value('OBJECTFS_S3_REGION', 'us-east-1'), 'tool_objectfs');
set_config('s3_base_url', env_value('OBJECTFS_S3_BASE_URL', ''), 'tool_objectfs');
set_config('key_prefix', env_value('OBJECTFS_KEY_PREFIX', ''), 'tool_objectfs');

mtrace('tool_objectfs configuration has been applied.');

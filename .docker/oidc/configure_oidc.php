#!/usr/bin/env php
<?php

define('CLI_SCRIPT', true);

require_once('/moodleroot/moodle/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');

function env_value(string $name, ?string $default = null): ?string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function env_bool(string $name, bool $default = false): bool {
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }
    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

$pluginmanager = core_plugin_manager::instance();
$plugininfo = $pluginmanager->get_plugin_info('auth_oidc');

if (!$plugininfo) {
    cli_error('auth_oidc plugin files are not available in this Moodle instance.');
}

if (!$plugininfo->is_installed_and_upgraded()) {
    cli_error('auth_oidc is present but not installed/upgraded yet.');
}

$keycloak_url = env_value('KEYCLOAK_URL', 'http://keycloak:8080');
$keycloak_realm = env_value('KEYCLOAK_REALM', 'moodle');
$keycloak_client_id = env_value('OIDC_CLIENT_ID', 'moodle-oidc');

$client_secret = env_value('OIDC_CLIENT_SECRET');

if (empty($client_secret)) {
    // No client secret provided — fetch it from Keycloak Admin API.
    $keycloak_admin_user = env_value('KEYCLOAK_ADMIN_USER', 'admin');
    $keycloak_admin_password = env_value('KEYCLOAK_ADMIN_PASSWORD', 'admin');

    $token_url = $keycloak_url . '/realms/master/protocol/openid-connect/token';
    $token_post_fields = 'username=' . urlencode($keycloak_admin_user) . '&password=' . urlencode($keycloak_admin_password) . '&grant_type=password&client_id=admin-cli';

    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $token_post_fields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $token_response = curl_exec($ch);
    $token_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($token_http_code !== 200) {
        cli_error('Failed to authenticate with Keycloak Admin API. HTTP code: ' . $token_http_code . ' Response: ' . $token_response);
    }

    $token_data = json_decode($token_response, true);
    if (!isset($token_data['access_token'])) {
        cli_error('Failed to obtain Keycloak access token.');
    }
    $access_token = $token_data['access_token'];

    $clients_url = $keycloak_url . '/admin/realms/' . $keycloak_realm . '/clients?clientId=' . urlencode($keycloak_client_id);
    $ch = curl_init($clients_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
    $clients_response = curl_exec($ch);
    $clients_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($clients_http_code !== 200) {
        cli_error('Failed to query Keycloak clients. HTTP code: ' . $clients_http_code);
    }

    $clients = json_decode($clients_response, true);
    $client_uuid = null;
    foreach ($clients as $client) {
        if ($client['clientId'] === $keycloak_client_id) {
            $client_uuid = $client['id'];
            break;
        }
    }

    if (!$client_uuid) {
        cli_error('Could not find Keycloak client "' . $keycloak_client_id . '" in realm "' . $keycloak_realm . '".');
    }

    $secret_url = $keycloak_url . '/admin/realms/' . $keycloak_realm . '/clients/' . $client_uuid . '/client-secret';
    $ch = curl_init($secret_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
    $secret_response = curl_exec($ch);
    curl_close($ch);

    $secret_data = json_decode($secret_response, true);
    $client_secret = $secret_data['value'] ?? null;

    if (empty($client_secret)) {
        cli_error('Could not retrieve client secret for Keycloak client "' . $keycloak_client_id . '".');
    }

    mtrace('Successfully retrieved Keycloak client secret from Admin API.');
} else {
    mtrace('Using OIDC_CLIENT_SECRET from environment.');
}

$keycloak_external_url = env_value('KEYCLOAK_EXTERNAL_URL', $keycloak_url);
mtrace("KEYCLOAK_EXTERNAL_URL = " . ($keycloak_external_url ?: '(not set, using internal URL)'));
$idp_type = env_value('OIDC_IDP_TYPE', '3');
$auth_endpoint = env_value('OIDC_AUTH_ENDPOINT', $keycloak_external_url . '/realms/' . $keycloak_realm . '/protocol/openid-connect/auth');
$token_endpoint = env_value('OIDC_TOKEN_ENDPOINT', $keycloak_url . '/realms/' . $keycloak_realm . '/protocol/openid-connect/token');
$logout_endpoint = env_value('OIDC_LOGOUT_ENDPOINT', $keycloak_external_url . '/realms/' . $keycloak_realm . '/protocol/openid-connect/logout');
$scope = env_value('OIDC_SCOPE', 'openid profile email');
$resource = env_value('OIDC_RESOURCE', $keycloak_client_id);
$binding_username_claim = env_value('OIDC_BINDING_USERNAME_CLAIM', 'email');
$force_redirect = env_bool('OIDC_FORCE_REDIRECT', false) ? '1' : '0';
$silent_login_mode = env_bool('OIDC_SILENT_LOGIN_MODE', false) ? '1' : '0';
$single_sign_off = env_bool('OIDC_SINGLE_SIGN_OFF', true) ? '1' : '0';
$provider_name = env_value('OIDC_PROVIDER_NAME', 'Adorsys SSO');
$custom_claims = env_value('OIDC_CUSTOM_CLAIMS', '');

set_config('idptype', $idp_type, 'auth_oidc');
set_config('clientid', $keycloak_client_id, 'auth_oidc');
set_config('clientauthmethod', '1', 'auth_oidc');
set_config('clientsecret', $client_secret, 'auth_oidc');
set_config('authendpoint', $auth_endpoint, 'auth_oidc');
set_config('tokenendpoint', $token_endpoint, 'auth_oidc');
set_config('oidcresource', $resource, 'auth_oidc');
set_config('oidcscope', $scope, 'auth_oidc');
set_config('customclaims', $custom_claims, 'auth_oidc');
set_config('bindingusernameclaim', $binding_username_claim, 'auth_oidc');

set_config('forceredirect', $force_redirect, 'auth_oidc');
set_config('silentloginmode', $silent_login_mode, 'auth_oidc');
set_config('single_sign_off', $single_sign_off, 'auth_oidc');
set_config('logouturi', $logout_endpoint, 'auth_oidc');
set_config('opname', $provider_name, 'auth_oidc');
set_config('loginflow', 'authcode', 'auth_oidc');
set_config('debugmode', '0', 'auth_oidc');
set_config('set_pix', '1', 'auth_oidc');

set_config('field_map_email', 'mail', 'auth_oidc');
set_config('field_updatelocal_email', 'always', 'auth_oidc');
set_config('field_lock_email', 'unlocked', 'auth_oidc');

set_config('field_map_firstname', 'givenName', 'auth_oidc');
set_config('field_updatelocal_firstname', 'always', 'auth_oidc');
set_config('field_lock_firstname', 'unlocked', 'auth_oidc');

set_config('field_map_lastname', 'surname', 'auth_oidc');
set_config('field_updatelocal_lastname', 'always', 'auth_oidc');
set_config('field_lock_lastname', 'unlocked', 'auth_oidc');

mtrace('Verifying auth_oidc settings ...');
$verify_fields = [
    'authendpoint' => $auth_endpoint,
    'tokenendpoint' => $token_endpoint,
    'logouturi' => $logout_endpoint,
    'clientid' => $keycloak_client_id,
    'idptype' => $idp_type,
    'bindingusernameclaim' => $binding_username_claim,
    'forceredirect' => $force_redirect,
    'opname' => $provider_name,
];
foreach ($verify_fields as $name => $expected) {
    $actual = get_config('auth_oidc', $name);
    $status = ($actual === $expected) ? 'OK' : 'MISMATCH';
    mtrace("  {$status}: auth_oidc/{$name} = {$actual}" . ($actual !== $expected ? " (expected: {$expected})" : ''));
}

mtrace('auth_oidc configuration has been applied.');

// Enable guest login button on the login page.
// In Moodle 5.2, guestloginbutton defaults to '0' (hidden), so we must explicitly enable it.
$guestloginbutton = get_config('core', 'guestloginbutton');
if (empty($guestloginbutton)) {
    set_config('guestloginbutton', '1');
    mtrace('Enabled guest login button on the login page.');
}

// Ensure auth_oidc is in the active authentication plugins list.
$current_auth = get_config('core', 'auth');
if (strpos($current_auth, 'oidc') === false) {
    $new_auth = 'oidc,' . $current_auth;
    set_config('auth', $new_auth);
    mtrace("Added oidc to auth plugins: {$new_auth}");
} else {
    mtrace("auth_oidc already in auth plugins list.");
}
// Ensure manual and email are also present.
if (strpos($new_auth ?? $current_auth, 'manual') === false) {
    set_config('auth', 'manual,' . ($new_auth ?? $current_auth));
}
if (strpos(get_config('core', 'auth'), 'email') === false) {
    set_config('auth', get_config('core', 'auth') . ',email');
}
mtrace('Final auth plugins: ' . get_config('core', 'auth'));
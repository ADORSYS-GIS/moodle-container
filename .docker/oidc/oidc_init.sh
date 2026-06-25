#!/bin/sh
set -eu

if [ ! -d "${MOODLE_PATH}/public/auth/oidc" ]; then
  echo "auth_oidc plugin files are not mounted, skipping OIDC init ..."
  exit 0
fi

if [ -f "${MOODLE_DATAROOT_PATH}/.oidc-initialized" ]; then
  echo "OIDC already initialized, skipping ..."
  exit 0
fi

echo "Running Moodle upgrade to register auth_oidc plugin ..."
sudo -u www php84 -d max_input_vars=10000 \
  "${MOODLE_PATH}/admin/cli/upgrade.php" \
  --non-interactive \
  --allow-unstable

echo "Purging caches to refresh plugin state ..."
sudo -u www php84 -d max_input_vars=10000 \
  "${MOODLE_PATH}/admin/cli/purge_caches.php"

echo "Configuring auth_oidc ..."
sudo -E -u www php84 -d max_input_vars=10000 \
  /scripts/configure_oidc.php

echo "Enabling auth_oidc in authentication plugins list ..."
current_auth=$(sudo -u www php84 -d max_input_vars=10000 \
  "${MOODLE_PATH}/admin/cli/cfg.php" --name=auth)

if ! echo "$current_auth" | grep -qw "manual"; then
  current_auth="manual,${current_auth}"
fi

if echo "$current_auth" | grep -qw "oidc"; then
  echo "auth_oidc already enabled."
else
  current_auth="oidc,${current_auth}"
  echo "auth_oidc added to authentication plugins list."
fi

sudo -u www php84 -d max_input_vars=10000 \
  "${MOODLE_PATH}/admin/cli/cfg.php" --name=auth --set="$current_auth"

echo "Purging caches after enabling auth_oidc ..."
sudo -u www php84 -d max_input_vars=10000 \
  "${MOODLE_PATH}/admin/cli/purge_caches.php"

echo "Verifying auth plugins ..."
final_auth=$(sudo -u www php84 -d max_input_vars=10000 \
  "${MOODLE_PATH}/admin/cli/cfg.php" --name=auth)
echo "auth = ${final_auth}"

sudo -u www touch "${MOODLE_DATAROOT_PATH}/.oidc-initialized"
echo "OIDC init completed."
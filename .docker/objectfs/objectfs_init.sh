#!/bin/sh
set -eu

if [ -f "${MOODLE_PATH}/config.php" ] && [ -d "${MOODLE_PATH}/admin/tool/objectfs" ]; then
  if ! grep -q "alternative_file_system_class = '\\\\tool_objectfs\\\\s3_file_system'" "${MOODLE_PATH}/config.php"; then
    echo "Injecting ObjectFS alternative file system into config.php ..."
    sed -i "/require_once/i \$CFG->alternative_file_system_class = '\\\\tool_objectfs\\\\s3_file_system';" "${MOODLE_PATH}/config.php"
  else
    echo "ObjectFS alternative file system already present in config.php ..."
  fi

  if ! grep -q "disablebyteserving = true" "${MOODLE_PATH}/config.php"; then
    echo "Disabling xsendfile and byte-serving acceleration for ObjectFS ..."
    sed -i "/require_once/i \$CFG->disablebyteserving = true;" "${MOODLE_PATH}/config.php"
    sed -i "/require_once/i unset(\$CFG->xsendfilealiases);" "${MOODLE_PATH}/config.php"
    sed -i "/require_once/i \$CFG->xsendfile = null;" "${MOODLE_PATH}/config.php"
  else
    echo "ObjectFS byte-serving overrides already present in config.php ..."
  fi
fi

if [ ! -d "${MOODLE_PATH}/admin/tool/objectfs" ]; then
  echo "tool_objectfs plugin files are not mounted, skipping ObjectFS init ..."
  exit 0
fi

echo "Installing or upgrading tool_objectfs ..."
sudo -u www php82 -d max_input_vars=10000 \
  "${MOODLE_PATH}/admin/cli/upgrade.php" \
  --non-interactive \
  --allow-unstable

echo "Applying tool_objectfs settings for MinIO ..."
php82 /scripts/configure_objectfs.php

echo "Trying initial ObjectFS transfer tasks ..."
sudo -u www php82 -d max_input_vars=10000 \
  "${MOODLE_PATH}/admin/cli/scheduled_task.php" \
  --execute='\tool_objectfs\task\push_objects_to_storage' || \
  echo "ObjectFS push task did not complete during init; Moodle cron can still process it later."

sudo -u www php82 -d max_input_vars=10000 \
  "${MOODLE_PATH}/admin/cli/scheduled_task.php" \
  --execute='\tool_objectfs\task\delete_local_objects' || \
  echo "ObjectFS local-delete task did not complete during init; Moodle cron can still process it later."

echo "ObjectFS init completed."

#!/bin/sh
set -eu

shared_config_dir="${SHARED_CONFIG_DIR:-/shared-config}"
shared_config_file="${shared_config_dir}/config.php"

mkdir -p "$shared_config_dir"

if [ -f "$shared_config_file" ] && [ ! -f "${MOODLE_PATH}/config.php" ]; then
  echo "Restoring shared config.php before backup restore ..."
  cp "$shared_config_file" "${MOODLE_PATH}/config.php"
  chown www:www "${MOODLE_PATH}/config.php"
fi

echo "Preparing Moodle in backup-restorer container ..."
/opt/setup_moodle.sh

backup_dir="${MOODLE_BACKUP_IMPORT_DIR:-/moodleroot/backups}"
restore_category_id="${MOODLE_BACKUP_RESTORE_CATEGORY_ID:-1}"
restored_markers_dir="${MOODLE_DATAROOT_PATH}/.restored-backups"

if [ ! -d "$backup_dir" ]; then
  echo "No backup directory found at $backup_dir, skipping course restore ..."
  exit 0
fi

mkdir -p "$restored_markers_dir"
chown -R www:www "$restored_markers_dir"

backup_count="$(find "$backup_dir" -maxdepth 1 -type f -name '*.mbz' | wc -l | tr -d ' ')"
if [ "$backup_count" = "0" ]; then
  echo "No .mbz course backups found in $backup_dir ..."
  exit 0
fi

echo "Restoring $backup_count Moodle backup(s) from $backup_dir ..."

find "$backup_dir" -maxdepth 1 -type f -name '*.mbz' | sort | while IFS= read -r backup_file; do
  backup_hash="$(sha1sum "$backup_file" | awk '{print $1}')"
  marker_file="$restored_markers_dir/$backup_hash.done"

  if [ -f "$marker_file" ]; then
    echo "Backup already restored, skipping $(basename "$backup_file") ..."
    continue
  fi

  echo "Importing $(basename "$backup_file") into Moodle category $restore_category_id ..."
  restore_output="$(sudo -u www php84 -d max_input_vars=10000 \
    "$MOODLE_PATH/admin/cli/restore_backup.php" \
    --file="$backup_file" \
    --categoryid="$restore_category_id" 2>&1)" || {
    echo "$restore_output"
    echo "Restore failed for $(basename "$backup_file") ..."
    exit 1
  }

  echo "$restore_output"

  if ! printf '%s\n' "$restore_output" | grep -q "== Restored course ID:"; then
    echo "Restore did not complete successfully for $(basename "$backup_file") ..."
    exit 1
  fi

  touch "$marker_file"
  chown www:www "$marker_file"
  echo "Finished restoring $(basename "$backup_file") ..."
done

echo "Backup restore completed."

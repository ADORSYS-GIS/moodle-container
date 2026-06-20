#!/bin/sh
set -eu

plugin_dir="${PLUGIN_DIR:-/plugin_downloads}"
plugin_repo="${PLUGIN_REPO:-https://github.com/catalyst/moodle-tool_objectfs.git}"
plugin_branch="${PLUGIN_BRANCH:-MOODLE_404_STABLE}"

if [ -d "$plugin_dir/.git" ]; then
  echo "Updating tool_objectfs plugin in $plugin_dir ..."
  git -C "$plugin_dir" fetch --depth 1 origin "$plugin_branch"
  git -C "$plugin_dir" checkout -f FETCH_HEAD
else
  tmpdir="$(mktemp -d)"
  echo "Cloning tool_objectfs plugin into $plugin_dir ..."
  git clone --depth 1 --branch "$plugin_branch" "$plugin_repo" "$tmpdir/objectfs"
  mkdir -p "$plugin_dir"
  find "$plugin_dir" -mindepth 1 -maxdepth 1 -exec rm -rf {} \;
  cp -R "$tmpdir/objectfs"/. "$plugin_dir"/
  rm -rf "$tmpdir"
fi

echo "tool_objectfs plugin is ready."

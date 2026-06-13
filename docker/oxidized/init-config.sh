#!/bin/sh
set -eu

config_dir=/home/oxidized/.config/oxidized
mkdir -p "$config_dir/output"

sed \
    -e "s|__OXIDIZED_USERNAME__|${OXIDIZED_USERNAME:?OXIDIZED_USERNAME is required}|g" \
    -e "s|__OXIDIZED_PASSWORD__|${OXIDIZED_PASSWORD:?OXIDIZED_PASSWORD is required}|g" \
    -e "s|__OXIDIZED_API_TOKEN__|${OXIDIZED_API_TOKEN:?OXIDIZED_API_TOKEN is required}|g" \
    /etc/oxidized/config.template > "$config_dir/config"

chown -R oxidized:oxidized "$config_dir"
chmod 600 "$config_dir/config"

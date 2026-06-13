#!/usr/bin/env sh
set -eu

if [ -f /opt/campus/startup-config.cfg ]; then
    rm -rf /config/startup-config.cfg
    cp /opt/campus/startup-config.cfg /config/startup-config.cfg
fi

exec /launch.py "$@"

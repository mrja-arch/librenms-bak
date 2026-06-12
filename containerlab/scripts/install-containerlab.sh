#!/usr/bin/env bash
set -euo pipefail

CLAB_VERSION="${CLAB_VERSION:-0.68.0}"

sudo apt-get update
sudo apt-get install -y ca-certificates curl docker.io jq make git
curl -sL https://containerlab.dev/setup | sudo -E bash -s "all" "${CLAB_VERSION}"

containerlab version
docker version


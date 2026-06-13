#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
"$ROOT/scripts/clab.sh" inspect --topo "$ROOT/campus.clab.yml"
docker ps --filter label=containerlab=huawei-campus --format 'table {{.Names}}\t{{.Status}}\t{{.Networks}}'

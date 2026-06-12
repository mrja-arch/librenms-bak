#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
containerlab inspect --topo "$ROOT/campus.clab.yml"
docker ps --filter label=containerlab=huawei-campus --format 'table {{.Names}}\t{{.Status}}\t{{.Networks}}'


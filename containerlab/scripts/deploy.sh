#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

docker build -t "${ACCESS_IMAGE:-mrja/campus-access:1.0}" access-node
docker build -t "${AGG_IMAGE:-mrja/campus-aggregation:1.0}" agg-node
containerlab deploy --topo campus.clab.yml --reconfigure

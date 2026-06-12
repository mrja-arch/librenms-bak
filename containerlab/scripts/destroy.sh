#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
containerlab destroy --topo "$ROOT/campus.clab.yml" --cleanup


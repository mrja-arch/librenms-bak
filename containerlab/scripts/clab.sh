#!/usr/bin/env bash
set -euo pipefail

LAB_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO_ROOT="$(cd "$LAB_ROOT/.." && pwd)"
CLAB_IMAGE="${CLAB_IMAGE:-ghcr.io/srl-labs/clab:0.68.0}"

if docker info --format '{{.OperatingSystem}}' 2>/dev/null | grep -qi 'docker desktop'; then
    case "$REPO_ROOT" in
        /mnt/[a-zA-Z]/*)
            drive="${REPO_ROOT:5:1}"
            relative="${REPO_ROOT:7}"
            engine_root="/run/desktop/mnt/host/${drive,,}/$relative"
            ;;
        *)
            echo "ERROR: Docker Desktop mode expects the repository under /mnt/<drive>/." >&2
            exit 1
            ;;
    esac

    exec docker run --rm --privileged --network host --pid host \
        -v /var/run/docker.sock:/var/run/docker.sock \
        -v "$REPO_ROOT:$engine_root" \
        -w "$engine_root/containerlab" \
        -e "CORE_IMAGE=${CORE_IMAGE:-mrja/campus-core01:ce12800-8.180}" \
        -e "AGG_IMAGE=${AGG_IMAGE:-mrja/campus-agg01:ce12800-8.180}" \
        -e "ACCESS11_IMAGE=${ACCESS11_IMAGE:-mrja/campus-access11:ce12800-8.180}" \
        -e "ACCESS12_IMAGE=${ACCESS12_IMAGE:-mrja/campus-access12:ce12800-8.180}" \
        "$CLAB_IMAGE" containerlab "$@"
fi

command -v containerlab >/dev/null || {
    echo "ERROR: containerlab is not installed." >&2
    exit 1
}
exec containerlab "$@"

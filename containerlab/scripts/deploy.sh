#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

CE_IMAGE="${CE_IMAGE:-windddkz/huawei_vrp:ce12800-8.180}"

if [[ ! -e /dev/kvm ]]; then
    echo "ERROR: /dev/kvm is unavailable. Enable virtualization and WSL2 nested virtualization before starting CE12800." >&2
    exit 1
fi

docker image inspect "$CE_IMAGE" >/dev/null 2>&1 || docker pull "$CE_IMAGE"
docker image inspect "${CLAB_IMAGE:-ghcr.io/srl-labs/clab:0.68.0}" >/dev/null 2>&1 ||
    docker pull "${CLAB_IMAGE:-ghcr.io/srl-labs/clab:0.68.0}"
for node in core01 agg01 access11 access12; do
    case "$node" in
        core01)
            image_var="CORE_IMAGE"
            default_image="mrja/campus-core01:ce12800-8.180"
            ;;
        agg01)
            image_var="AGG_IMAGE"
            default_image="mrja/campus-agg01:ce12800-8.180"
            ;;
        access11)
            image_var="ACCESS11_IMAGE"
            default_image="mrja/campus-access11:ce12800-8.180"
            ;;
        access12)
            image_var="ACCESS12_IMAGE"
            default_image="mrja/campus-access12:ce12800-8.180"
            ;;
    esac
    eval "node_image=\${$image_var:-$default_image}"
    docker build \
        --build-arg "CE_IMAGE=$CE_IMAGE" \
        --build-arg "STARTUP_CONFIG=configs/$node.cfg" \
        -t "$node_image" \
        -f vrp-node/Dockerfile .
    export "$image_var=$node_image"
done

"$ROOT/scripts/clab.sh" deploy --topo campus.clab.yml --reconfigure

#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMAGE_FILE="$(find "$ROOT/images" -maxdepth 1 -type f -name 'huawei_ce12800-V800R023*.qcow2' | head -n 1)"
VRNETLAB_REF="${VRNETLAB_REF:-7c3c7e6246af0ce3d0da0bb97a59da8834663ea9}"

if [[ -z "$IMAGE_FILE" ]]; then
    echo "Place a legally obtained huawei_ce12800-V800R023*.qcow2 file in $ROOT/images." >&2
    exit 2
fi

rm -rf "$ROOT/vrnetlab"
git clone https://github.com/srl-labs/vrnetlab.git "$ROOT/vrnetlab"
git -C "$ROOT/vrnetlab" checkout "$VRNETLAB_REF"
cp "$IMAGE_FILE" "$ROOT/vrnetlab/huawei/huawei_vrp/"

(
    cd "$ROOT/vrnetlab/huawei/huawei_vrp"
    make
)

sha256sum "$IMAGE_FILE" | tee "$ROOT/images/CE12800.sha256"
docker images 'vrnetlab/huawei_vrp:ce12800-*'

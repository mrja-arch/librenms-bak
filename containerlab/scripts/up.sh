#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
LAB_ROOT="$ROOT/containerlab"
COMPOSE=(docker compose -f "$ROOT/docker/compose.yml" -f "$LAB_ROOT/compose.override.yml")

command -v docker >/dev/null || {
    echo "ERROR: docker is not installed or not available in this WSL distribution." >&2
    exit 1
}
"$LAB_ROOT/scripts/deploy.sh"
"${COMPOSE[@]}" up -d

echo "Waiting for SNMP agents..."
for ip in 172.31.255.11 172.31.255.21 172.31.255.31 172.31.255.32; do
    ready=0
    for _ in $(seq 1 60); do
        if "${COMPOSE[@]}" exec -T librenms \
            snmpget -v2c -c librenms-lab -t 2 -r 0 "$ip" \
            SNMPv2-MIB::sysName.0 >/dev/null 2>&1; then
            ready=1
            break
        fi
        sleep 5
    done
    if [[ "$ready" -eq 1 ]]; then
        name="$("${COMPOSE[@]}" exec -T librenms \
            snmpget -v2c -c librenms-lab -Oqv "$ip" SNMPv2-MIB::sysName.0)"
        printf 'SNMP OK  %-15s %s\n' "$ip" "$name"
    else
        printf 'SNMP WAIT %-15s not ready yet\n' "$ip" >&2
    fi
done

"$LAB_ROOT/scripts/status.sh"

echo "Published host ports:"
docker ps --filter label=containerlab=huawei-campus \
    --format 'table {{.Names}}\t{{.Ports}}'

echo "LibreNMS: http://localhost:${WEB_PORT:-8000}/"

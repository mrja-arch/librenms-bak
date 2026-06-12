#!/usr/bin/env bash
set -euo pipefail

NODE_NAME="${NODE_NAME:-access}"
ACCESS_VLAN="${ACCESS_VLAN:-100}"
SNMP_COMMUNITY="${SNMP_COMMUNITY:-librenms-lab}"

hostname "$NODE_NAME"
mkdir -p /etc/snmp
cat >/etc/snmp/snmpd.conf <<EOF
agentAddress udp:161
rocommunity ${SNMP_COMMUNITY}
sysName ${NODE_NAME}
sysLocation Containerlab-Campus
sysContact LibreNMS
EOF

ip link add br-campus type bridge vlan_filtering 1 2>/dev/null || true
ip link set br-campus up

if ip link show eth1 >/dev/null 2>&1; then
    ip link set eth1 master br-campus
    ip link set eth1 up
    bridge vlan add dev eth1 vid "$ACCESS_VLAN"
fi

lldpd
snmpd -f -Lo

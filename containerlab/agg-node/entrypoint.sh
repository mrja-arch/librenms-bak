#!/usr/bin/env sh
set -eu

NODE_NAME="${NODE_NAME:-aggregation}"
SNMP_COMMUNITY="${SNMP_COMMUNITY:-librenms-lab}"
TRUNK_PORTS="${TRUNK_PORTS:-eth2:110 eth3:120 eth4:130}"

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
for item in $TRUNK_PORTS; do
    port="${item%%:*}"
    vlan="${item##*:}"
    if ip link show "$port" >/dev/null 2>&1; then
        ip link set "$port" master br-campus
        ip link set "$port" up
        bridge vlan add dev "$port" vid "$vlan"
    fi
done

/usr/lib/frr/frrinit.sh start
lldpd
exec snmpd -f -Lo

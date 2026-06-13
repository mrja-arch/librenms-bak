# Huawei Campus Containerlab

This lab models four Huawei CE12800 VRP nodes: one core, one aggregation
switch, and two access switches. The core-to-aggregation link runs OSPF and
aggregation-to-access links carry VLAN trunks. LLDP and SNMP are enabled on
all nodes.

## Quick start

1. Install Ubuntu 24.04 WSL2 with `scripts/install-wsl.ps1`.
2. Enable Docker Desktop WSL integration for Ubuntu. The scripts run
   Containerlab in its privileged container on Docker Desktop, so a separate
   host installation is not required.
3. Pull the CE12800 image:

```bash
docker pull windddkz/huawei_vrp:ce12800-8.180
```

4. Deploy the lab and attach LibreNMS to the management network:

```bash
./containerlab/scripts/up.sh
```

The CE12800 VM needs KVM (`/dev/kvm`) and can take several minutes to finish
booting. Use `scripts/status.sh` to inspect the lab and `scripts/destroy.sh` to
remove it. `CE_IMAGE` can override the default image tag.

The management addresses are `172.31.255.11` for the core,
`172.31.255.21` for aggregation, and `172.31.255.31-32` for access.
LibreNMS uses `172.31.255.2` when the Compose override is enabled.

Each node also publishes its management services on unique host ports:

| Node | SSH | SNMP/UDP | HTTP | HTTPS | NETCONF |
| --- | ---: | ---: | ---: | ---: | ---: |
| core01 | 2201 | 16101 | 8001 | 8441 | 8301 |
| agg01 | 2202 | 16102 | 8002 | 8442 | 8302 |
| access11 | 2211 | 16111 | 8011 | 8451 | 8311 |
| access12 | 2212 | 16112 | 8012 | 8452 | 8312 |

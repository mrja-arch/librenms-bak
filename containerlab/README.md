# Huawei Campus Containerlab

This lab models one CE12800 core, two lightweight FRRouting aggregation
nodes, and six Linux access nodes. Core-to-aggregation links run OSPF and
aggregation-to-access links carry VLAN trunks. LLDP and SNMP are enabled on
all nodes. The CE image is not distributed by this repository.

## Quick start

1. Install Ubuntu 24.04 WSL2 with `scripts/install-wsl.ps1`.
2. Run `scripts/install-containerlab.sh` in Ubuntu.
3. Put `huawei_ce12800-V800R023*.qcow2` in `images/`.
4. Run `scripts/build-ce12800.sh`.
5. Run `scripts/deploy.sh`.
6. Start LibreNMS with the shared management network:

```bash
docker compose -f docker/compose.yml -f containerlab/compose.override.yml up -d
```

Use `scripts/status.sh` to inspect the lab and `scripts/destroy.sh` to remove it.

The management addresses are `172.31.255.11` for the core,
`172.31.255.21-22` for aggregation, and `172.31.255.31-36` for access.
LibreNMS uses `172.31.255.2` when the Compose override is enabled.

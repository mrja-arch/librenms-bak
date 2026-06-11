# Docker 部署与调试说明

## 1. 目标

本仓库的 Docker 结构服务于：

- 通用 LibreNMS 多厂商监控平台
- Huawei MIB 与 Trap 增强
- 本地开发调试
- 单机部署验证

## 2. 服务说明

基础服务：

- `db`
- `redis`
- `librenms`
- `dispatcher`
- `snmptrapd`

可选服务：

- `syslogng`
- `oxidized`

## 3. 启动方式

基础部署：

```bash
cp docker/.env.example .env
docker compose -f docker/compose.yml build
docker compose -f docker/compose.yml up -d
```

启用 Syslog sidecar：

```bash
docker compose -f docker/compose.yml --profile syslog up -d
```

启用 Oxidized：

```bash
docker compose -f docker/compose.yml --profile oxidized up -d
```

开发调试挂载源码：

```bash
docker compose -f docker/compose.yml -f docker/compose.dev.yml up -d
```

## 4. 关键约束

- 默认部署模式不覆盖 `/opt/librenms`
- sidecar 角色通过 `SIDECAR_DISPATCHER`、`SIDECAR_SNMPTRAPD`、`SIDECAR_SYSLOGNG` 启动
- Huawei MIB 通过 `SNMP_EXTRA_MIB_DIRS=/opt/librenms/mibs/huawei` 注入
- `snmptrapd` 接收全部厂商 Trap，Huawei 只是额外增强

## 5. Oxidized 建议

- 默认纳管 Huawei CLI 网络设备：VRP、YunShan、SmartAX、SmartAX MDU、OptiX RTN
- 默认排除：iBMC、SMU、OceanStor、UPS
- 其他厂商网络设备直接走 LibreNMS / Oxidized 原生映射
- LibreNMS 侧建议开启：

```bash
lnms config:set oxidized.enabled true
lnms config:set oxidized.url http://oxidized:8888
lnms config:set oxidized.features.versioning true
lnms config:set oxidized.group_support true
lnms config:set oxidized.ignore_os '["ibmc","huawei-smu","oceanstor","huaweiups"]'
```

## 6. 验证

容器启动后建议至少执行：

```bash
docker compose -f docker/compose.yml config
docker compose -f docker/compose.yml ps
docker compose -f docker/compose.yml exec librenms ./validate.php
docker compose -f docker/compose.yml exec librenms ./lnms dev:check
```

# LibreNMS 多厂商平台与 Huawei 深度增强实施计划

## 1. 目标

本仓库以 LibreNMS 原生多厂商能力为主体，Huawei 增强能力作为增量交付。

目标包括：

- 保持其他厂商设备具备基础发现、轮询、Trap、Syslog 和配置备份能力
- 为 Huawei VRP、YunShan、SmartAX、SmartAX MDU、iBMC、SMU、OptiX RTN、OceanStor、Huawei UPS 提供更完整的设备管理能力
- 使用一套运行时 Huawei MIB 生效集兼容 `V600R025C00SPC600`、`V800R025C00SPC600` 及后续版本
- 将全部项目自定义文档统一收敛到 `docs-custom/`

## 2. 平台架构

- Docker 编排采用通用服务：`db`、`redis`、`librenms`、`dispatcher`、`snmptrapd`
- 可选服务：`syslogng`、`oxidized`
- `dispatcher`、`snmptrapd`、`syslogng` 采用 LibreNMS sidecar 模式
- 运行时不再把整个仓库覆盖挂载到 `/opt/librenms`
- 开发调试通过 `docker/compose.dev.yml` 精确挂载源码
- Huawei MIB 通过额外目录加入，不替换 LibreNMS 现有多厂商能力

## 3. Huawei MIB 兼容策略

- `mibs/huawei` 是唯一运行时生效目录
- `mib-archives/huawei` 只用于保存厂家原始版本包和来源元数据
- `mibs/huawei-manifest.json` 记录模块、状态、版本、哈希、设备族和代码引用
- `mibs/huawei-trap-modules.list` 记录运行时 Trap 模块清单
- MIB 升级流程固定为：归档、差异审计、兼容合并、代码校验、回归验证、再替换生效集

## 4. 代码实现方向

- YunShan 使用独立 OS 类复用 VRP 网络设备增强能力
- SmartAX 处理器采集迁移到 `SnmpQuery`
- OceanStor、OptiX RTN、SMU、UPS 等定义修正 MIB 列表、检测和基础 OS 信息
- Trap 使用通用 Huawei handler 承接高价值告警与恢复事件
- 继续允许未专门适配的 Huawei Trap 进入详细 Eventlog

## 5. 验收标准

- `docker compose config` 通过
- Huawei 与非 Huawei 设备基础回归均无明显回退
- Huawei Trap 可解析入库，其他厂商 Trap 不受影响
- 文档、模板、计划全部迁移到 `docs-custom/`
- 文档编码统一为 UTF-8

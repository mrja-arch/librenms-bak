# 设备接入说明

## 1. 总体原则

平台分为两层：

- 通用层：LibreNMS 原生多厂商能力
- Huawei 增强层：Huawei MIB、发现逻辑、Trap 和配置备份增强

## 2. 非 Huawei 设备

默认按 LibreNMS 原生方式接入：

- 配置 SNMP
- 添加设备
- 验证 interfaces、availability、基础 sensors
- 按需要启用 Syslog、Trap、Oxidized

第一阶段不额外开发非 Huawei 厂商专用 discovery/polling。

## 3. Huawei 设备

新增或升级设备前，按以下顺序确认：

1. 设备是否属于已覆盖设备族
2. 所需 MIB 是否已在 `mibs/huawei` 生效集内
3. `os_detection` 与 `os_discovery` 是否已引用对应模块
4. 是否需要补 OS 类、传感器、Trap handler
5. 是否需要加入 Oxidized CLI 备份映射

## 4. Huawei 设备族期望

- VRP / YunShan：资产、CPU、内存、VLAN、FDB、BGP、Trap、配置备份
- SmartAX / SmartAX MDU：机框板卡、电源、PON 口、基础 ONT 统计
- iBMC：服务器健康、风扇、电源、温度、磁盘、内存、RAID
- SMU：整流、电池、环境和关键站点告警
- OptiX RTN：板卡、ODU、温度、频率、光功率
- OceanStor：控制器、磁盘、池、容量和统一告警
- Huawei UPS：基于 UPS-MIB 的基础监控和 Huawei 检测增强

## 5. 新增 MIB 的处理

1. 先进入 `mib-archives/huawei`
2. 用工作流脚本做差异和引用审计
3. 修正 YAML / PHP / Trap handler 对应关系
4. 回归通过后再进入 `mibs/huawei`

## 6. 新增 Trap 的处理

- 有明确告警 / 恢复语义：加入 `config/snmptraps.php` 并实现 handler
- 暂无明确语义：先依赖详细 Eventlog 收集
- 涉及多版本 VarBind 漂移：handler 必须按兼容输入设计

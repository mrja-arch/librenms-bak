# Huawei MIB 多版本兼容实施与维护手册

## 1. 目标

本仓库现在采用一套长期维护的 Huawei MIB 兼容模式，覆盖以下设备族:

- VRP
- YunShan
- SmartAX
- SmartAX MDU
- iBMC
- SMU
- OptiX RTN
- OceanStor
- Huawei UPS

当前基线版本固定为 `V600R025C00SPC600`，未来路由器升级目标版本为 `V800R025C00SPC600`。后续再有新版本，也沿用同一套流程。

## 2. 目录约定

### 2.1 生效集

- 运行时生效目录: `mibs/huawei`
- 这里只有一套真正被 LibreNMS 和 snmptrapd 加载的 Huawei MIB
- 这里允许保留兼容模块，但不允许并存多个同名版本

### 2.2 版本归档

- 原始归档目录: `mib-archives/huawei`
- 这里保存厂家原始包、来源说明、校验值和差异审计材料
- 这里绝不能加入 `MIBDIRS`

### 2.3 治理产物

- 机器清单: `mibs/huawei-manifest.json`
- Trap 加载清单: `mibs/huawei-trap-modules.list`
- 差异报告模板: `mib-reports/huawei/HUAWEI_MIB_DIFF_TEMPLATE.md`
- 工作流脚本: `scripts/huawei-mib-workflow.php`

## 3. 本次落地的默认策略

### 3.1 当前分类

- `current`: 当前 V600 生效且直接采用的模块
- `compatibility`: 新厂家包缺失，但现有设备族或现有 LibreNMS 定义仍依赖的兼容模块
- `dependency`: 公共基础、Trap 基础或被其他模块依赖的模块

### 3.2 已处理的关键点

- 已从 `mibs/huawei` 移除与根 `mibs/` 重复的标准 MIB 副本
- 已恢复现有设备族依赖的兼容模块:
  - `HUAWEI-WAN-MIB`
  - `HUAWEI-POWER-MIB`
  - `HUAWEI-XPON-MIB`
  - `HUAWEI-XPON-COMMON-MIB`
  - `HWMUSA-DEV-MIB`
  - `HUAWEI-SERVER-IBMC-MIB`
  - `HUAWEI-SITE-MONITOR-MIB`
  - `HUAWEI-STORAGE-HARDWARE-MIB`
  - `HUAWEI-STORAGE-SPACE-MIB`
  - `ISM-HUAWEI-MIB`
  - `ISM-STORAGE-SVC-MIB`
  - `ISM-PERFORMANCE-MIB`
  - `OPTIX-BOARD-MANAGE-MIB`
  - `OPTIX-MISC-MIB`
  - `OPTIX-NE-MIB`
  - `OPTIX-OID-MIB`
  - `OPTIX-RTN-ODU-MGR-MIB`

## 4. 为什么只更新 MIB 还不够

根据 LibreNMS 开发文档，MIB 只是翻译和符号化基础，不会自动让设备“被支持”。要真正支持 Huawei 设备管理，仍要同步检查:

- `resources/definitions/os_detection/*.yaml`
- `resources/definitions/os_discovery/*.yaml`
- `LibreNMS/OS/*`
- `includes/discovery/*`
- `includes/polling/*`
- `config/snmptraps.php`
- `LibreNMS/Snmptrap/Handlers/*`

换句话说:

- 新增 OID 不等于自动会被发现
- 新增 Trap 不等于自动会被处理
- 同名符号漂移时，文本 OID 可能失效

## 5. 当前设备族依赖关系

### 5.1 VRP / YunShan

重点依赖:

- `HUAWEI-WAN-MIB`
- `HUAWEI-WLAN-CONFIGURATION-MIB`
- `HUAWEI-ENTITY-EXTENT-MIB`
- `HUAWEI-ENERGYMNGT-MIB`
- `HUAWEI-STACK-MIB`

### 5.2 SmartAX / SmartAX MDU

重点依赖:

- `HUAWEI-DEVICE-MIB`
- `HUAWEI-POWER-MIB`
- `HWMUSA-DEV-MIB`
- `HUAWEI-XPON-MIB`
- `HUAWEI-XPON-COMMON-MIB`

### 5.3 iBMC

重点依赖:

- `HUAWEI-SERVER-IBMC-MIB`

### 5.4 SMU

重点依赖:

- `HUAWEI-SITE-MONITOR-MIB`

### 5.5 OptiX RTN

重点依赖:

- `OPTIX-BOARD-MANAGE-MIB`
- `OPTIX-MISC-MIB`
- `OPTIX-NE-MIB`
- `OPTIX-OID-MIB`
- `OPTIX-RTN-ODU-MGR-MIB`

### 5.6 OceanStor

重点依赖:

- `ISM-HUAWEI-MIB`
- `ISM-STORAGE-SVC-MIB`
- `HUAWEI-STORAGE-HARDWARE-MIB`
- `HUAWEI-STORAGE-SPACE-MIB`
- `ISM-PERFORMANCE-MIB`

## 6. Trap 支持策略

当前仓库已有 Huawei Trap handler:

- `HUAWEI-LDT-MIB::hwLdtPortLoopDetect`
- `HUAWEI-LDT-MIB::hwLdtPortLoopDetectRecovery`

运行策略:

- snmptrapd 只加载 `mibs/huawei-trap-modules.list` 中列出的生效模块
- 现有 handler 保持不变
- 新版本新增但尚未定制 handler 的 Trap，先用 LibreNMS 通用 trap eventlog 入库
- 如果 V600 和 V800 的同一 Trap VarBind 变化，handler 要按兼容输入设计，而不是只硬编码一种结构

建议配置:

```bash
lnms config:set snmptraps.eventlog 'all'
lnms config:set snmptraps.eventlog_detailed true
```

这样第一阶段即使没有专用 handler，也能把 Huawei Trap 先落库用于分析。

## 7. V600 升级到 V800 的固定流程

1. 把 `V800R025C00SPC600` 原始厂家包放到 `mib-archives/huawei/V800R025C00SPC600`
2. 不要直接覆盖 `mibs/huawei`
3. 规范化文件名、换行和模块名
4. 用 `scripts/huawei-mib-workflow.php diff-template` 生成差异报告骨架
5. 审计三类变化:
   - 模块增删改
   - OID / 符号变化
   - Trap / VarBind 变化
6. 逐个核对 `os_detection`、`os_discovery`、Trap handler 用到的文本 OID 是否仍能解析
7. 对符号漂移严重的查询，优先改为稳定数字 OID 或按存在性分支查询
8. 只把通过验证的模块合并到 `mibs/huawei`
9. 新版本删除但旧设备仍依赖的模块，继续作为 `compatibility` 保留
10. 更新 `mibs/huawei-manifest.json`
11. 运行 Discovery、Polling、Trap 回归

## 8. 将来再有新版本时怎么处理

适用于 `V800R025C00SPC600` 之后的所有版本:

1. 新包先归档，不直接上线
2. 一次只比较相邻基线和目标版本
3. 差异结论必须写进报告
4. 生效集始终只有一套
5. 不为每个固件建一套运行目录
6. 不为每个固件版本新建一个 LibreNMS OS 类型
7. 兼容保留优先于简单覆盖

### 什么时候必须改代码

出现以下任一情况，就不能只换 MIB:

- YAML 中的文本 OID 失效
- 新设备型号的识别 OID 与现有定义不同
- 新 Trap 需要专用语义化日志
- 旧 Trap 的 VarBind 顺序或名称变化
- 旧模块删除后，新模块 OID 结构不兼容

## 9. Docker 开发与部署

仓库已补充:

- `Dockerfile.huawei`
- `docker-compose.yml`
- `.env.docker.example`

### 9.1 主要服务

- `mariadb`
- `redis`
- `librenms`
- `dispatcher`
- `snmptrapd`

### 9.2 启动方式

```bash
cp .env.docker.example .env
docker compose build
docker compose up -d
```

### 9.3 snmptrapd 说明

`snmptrapd` 服务会:

- 安装 `snmptrapd` 和 `snmp`
- 只加载 `mibs` 与 `mibs/huawei`
- 通过 `HUAWEI_TRAP_MODULES` 指定 Huawei Trap 模块清单
- 把原始 Trap 记录到 `/var/log/snmptrap/traps.log`

## 10. 验证步骤

### 10.1 MIB 治理校验

在有 PHP 环境时执行:

```bash
php scripts/huawei-mib-workflow.php audit
php scripts/huawei-mib-workflow.php manifest > mibs/huawei-manifest.json
php scripts/huawei-mib-workflow.php trap-modules > mibs/huawei-trap-modules.list
```

### 10.2 LibreNMS 基础校验

在容器内执行:

```bash
./validate.php
./lnms dev:check
```

### 10.3 回归样本

应重点覆盖:

- `tests/snmpsim/vrp*.snmprec`
- `tests/snmpsim/yunshan*.snmprec`
- `tests/snmpsim/smartax*.snmprec`
- `tests/snmpsim/ibmc.snmprec`
- `tests/snmpsim/huawei-smu*.snmprec`
- `tests/snmpsim/huawei-optixrtn*.snmprec`
- `tests/snmpsim/oceanstor*.snmprec`
- `tests/snmpsim/huaweiups*.snmprec`

### 10.4 Trap 验证

建议至少验证:

- 现有 LDT Trap 单元测试
- 仿真 Trap
- 真实设备 Trap
- eventlog 是否带符号名
- 设备是否能匹配到 source IP

## 11. 回滚策略

如果新版本导入后发现 discovery、polling 或 trap 异常:

1. 回退 `mibs/huawei` 中本次替换的模块
2. 恢复上一个 `huawei-manifest.json`
3. 重启 snmptrapd / LibreNMS 服务
4. 复查差异报告中被判定为兼容的模块

## 12. 本地已知限制

当前工作区已经完成了实施文件落地，但本机这次没有可直接执行的:

- `php`
- `snmptranslate`
- 正在运行的 Docker daemon

所以本次没有完成实际运行级验证。等 Docker daemon 和 PHP 环境可用后，按上面的步骤跑一轮就能把最后一段闭环补上。

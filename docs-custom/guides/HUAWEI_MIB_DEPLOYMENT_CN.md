# Huawei MIB 多版本兼容实施与维护手册

## 1. 目录约定

- 运行时生效目录：`mibs/huawei`
- 原始归档目录：`mib-archives/huawei`
- 机器清单：`mibs/huawei-manifest.json`
- Trap 模块清单：`mibs/huawei-trap-modules.list`
- 差异报告模板：`docs-custom/templates/HUAWEI_MIB_DIFF_TEMPLATE.md`
- 差异报告输出目录：`docs-custom/reports/huawei/`
- 工作流脚本：`scripts/huawei-mib-workflow.php`

`mibs/huawei` 只保留一套真正被 LibreNMS 和 snmptrapd 加载的 Huawei MIB。
`mib-archives/huawei` 绝不能加入 `MIBDIRS`。

## 2. 设备族覆盖

当前兼容体系覆盖以下 Huawei 设备族：

- VRP
- YunShan
- SmartAX
- SmartAX MDU
- iBMC
- SMU
- OptiX RTN
- OceanStor
- Huawei UPS

## 3. 为什么只更新 MIB 还不够

MIB 只负责：

- 文本 OID 翻译
- Trap 符号化
- 为 discovery / polling / handler 提供对象定义

真正支持设备管理还必须同步检查：

- `resources/definitions/os_detection/*.yaml`
- `resources/definitions/os_discovery/*.yaml`
- `LibreNMS/OS/*`
- `includes/discovery/*`
- `includes/polling/*`
- `config/snmptraps.php`
- `LibreNMS/Snmptrap/Handlers/*`

新增 OID 不会自动变成监控项，新增 Trap 也不会自动变成语义化事件。

## 4. 当前默认策略

- 当前基线版本：`V600R025C00SPC600`
- 未来路由器目标版本：`V800R025C00SPC600`
- 多版本长期并存，但运行时始终只有一套 Huawei 生效集
- 新版厂家包删除但旧设备仍依赖的模块继续作为 `compatibility` 保留
- Trap 第一阶段以“解析并入库”为先，未专门适配的 Trap 进入详细 Eventlog

## 5. 升级流程

1. 将新版本原始包放入 `mib-archives/huawei/<version>/`
2. 不直接覆盖 `mibs/huawei`
3. 运行差异报告模板：

```bash
php scripts/huawei-mib-workflow.php diff-template V600R025C00SPC600 V800R025C00SPC600 > docs-custom/reports/huawei/HUAWEI_MIB_DIFF_V600_TO_V800.md
```

4. 对模块、OID、Trap、VarBind 变更做差异审计
5. 运行审计脚本检查 YAML、PHP、Trap handler 引用：

```bash
php scripts/huawei-mib-workflow.php audit
php scripts/huawei-mib-workflow.php manifest > mibs/huawei-manifest.json
php scripts/huawei-mib-workflow.php trap-modules > mibs/huawei-trap-modules.list
```

6. 校验 discovery、polling、Trap 回归
7. 仅把验证通过的模块合并到 `mibs/huawei`

## 6. Trap 运行策略

- `snmptrapd` 只额外加载 Huawei 生效目录和 `mibs/huawei-trap-modules.list`
- 通用平台仍接收和处理其他厂商 Trap
- Huawei 已适配 Trap 走专用或通用 handler
- 未适配 Huawei Trap 通过以下配置进入详细 Eventlog：

```bash
lnms config:set snmptraps.eventlog 'all'
lnms config:set snmptraps.eventlog_detailed true
```

## 7. 回滚

发现 discovery、polling 或 Trap 异常时：

1. 回退本次替换的 `mibs/huawei` 模块
2. 恢复上一个 `mibs/huawei-manifest.json`
3. 重新生成 `mibs/huawei-trap-modules.list`
4. 重启 LibreNMS 与 snmptrapd sidecar

# Huawei MIB 多版本兼容实施与维护手册

## 1. 目录约定

- 运行时生效目录：`mibs/huawei`
- 原始归档目录：`mib-archives/huawei`
- 机器清单：`mibs/huawei-manifest.json`
- Trap 模块清单：`mibs/huawei-trap-modules.list`
- Trap Handler 全量映射：`mibs/huawei-trap-handler-map.json`
- 当前厂家来源版本：`mibs/huawei-source-version.txt`
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

1. 将厂家原始 ZIP 保存在仓库外，文件名保留版本号，例如
   `V800R025C00SPC600_MIB.zip`。记录 SHA-256，不把原始包提交到 Git。
2. 在 PowerShell 中计算校验值：

```powershell
Get-FileHash -Algorithm SHA256 C:\MIB\V800R025C00SPC600_MIB.zip
```

3. 使用 LibreNMS 镜像中的 PHP 执行导入。将 ZIP 目录只读挂载为 `/vendor`：

```powershell
docker run --rm --entrypoint /bin/sh `
  -v "${PWD}:/work" -v "C:\MIB:/vendor:ro" -w /work `
  mrja/librenms:26.5.1-custom -lc `
  "php scripts/huawei-mib-workflow.php import /vendor/V800R025C00SPC600_MIB.zip <SHA256>"
```

导入命令只更新 `HUAWEI-*.mib`，不会用厂家包中的标准依赖 MIB 覆盖 LibreNMS 基线，也不会删除厂家包中没有提供的兼容模块。
脚本从 ZIP 文件名识别版本，并更新 `mibs/huawei-source-version.txt`。

4. 运行差异报告模板：

```powershell
docker run --rm --entrypoint /bin/sh -v "${PWD}:/work" -w /work `
  mrja/librenms:26.5.1-custom -lc `
  "php scripts/huawei-mib-workflow.php diff-template V600R025C00SPC600 V800R025C00SPC600 > docs-custom/reports/huawei/HUAWEI_MIB_DIFF_V600_TO_V800.md"
```

5. 对新增、删除和变化的模块、OID、Trap、VarBind 做差异审计。新版包缺失但旧设备仍使用的模块，不要直接删除，应标记并保留为兼容模块。
6. 运行审计并重新生成机器清单、Trap 模块清单与 Handler 全量映射：

```powershell
docker run --rm --entrypoint /bin/sh -v "${PWD}:/work" -w /work `
  mrja/librenms:26.5.1-custom -lc `
  "php scripts/huawei-mib-workflow.php audit &&
   php scripts/huawei-mib-workflow.php manifest > mibs/huawei-manifest.json &&
   php scripts/huawei-mib-workflow.php trap-modules > mibs/huawei-trap-modules.list &&
   php scripts/huawei-mib-workflow.php trap-handler-map > mibs/huawei-trap-handler-map.json"
```

审计结果中的 `duplicate_files_in_huawei_dir` 和 `missing_referenced_modules` 均应为空。

7. 重建自定义 LibreNMS 镜像。MIB 位于镜像的 `/opt/librenms/mibs/huawei`，
   不是运行时宿主目录挂载，因此只执行 `restart` 不会载入新文件：

```powershell
docker compose -f docker/compose.yml -f containerlab/compose.override.yml build librenms
docker compose -f docker/compose.yml -f containerlab/compose.override.yml up -d --force-recreate `
  librenms dispatcher operations-worker snmptrapd
```

启用了 `syslog` profile 时，也要重建 `syslogng`。

8. 校验宿主与容器文件数量、关键文件哈希和 MIB 搜索路径：

```powershell
(Get-ChildItem -File mibs/huawei).Count
Get-FileHash -Algorithm SHA256 mibs/huawei/HUAWEI-MIB
docker compose -f docker/compose.yml -f containerlab/compose.override.yml exec librenms `
  sh -lc 'echo "$SNMP_EXTRA_MIB_DIRS"; find /opt/librenms/mibs/huawei -type f | wc -l; sha256sum /opt/librenms/mibs/huawei/HUAWEI-MIB'
```

9. 使用 Huawei 目录和 LibreNMS 标准 MIB 根目录共同做符号解析：

```powershell
docker compose -f docker/compose.yml -f containerlab/compose.override.yml exec librenms `
  snmptranslate -M /opt/librenms/mibs:/opt/librenms/mibs/huawei:/usr/share/snmp/mibs `
  -m HUAWEI-MIB -On HUAWEI-MIB::hwDatacomm
```

10. 对测试设备执行 discovery、poller 和实际 OID 查询，并发送至少一条已知 Trap，
    检查设备识别、传感器、端口、事件日志及 Trap handler。新增 OID 或 Trap 时，还要同步修改第 3 节列出的代码和定义文件。

## 6. Trap 运行策略

- `snmptrapd` 使用 `-m ALL`，MIB 搜索路径包含
  `/opt/librenms/mibs/huawei`，因此会尝试加载生效目录中的全部 Huawei MIB。
- `mibs/huawei-trap-modules.list` 由工作流扫描 `NOTIFICATION-TYPE` 自动生成，
  用于审计所有包含 Trap 的模块是否随版本变化同步进入镜像。
- 已有精确映射的 Huawei Trap 优先进入专用 handler，例如接口状态、环路检测和
  资源告警；专用 handler 可以更新端口等 LibreNMS 对象状态。
- 未建立精确映射的 `HUAWEI`、`HWMUSA`、`ISM`、`OPTIX`、`NQA` Trap
  进入统一 Huawei handler，保存 Trap 名称、VarBind、关联对象和推断严重性，
  不再作为 `Unhandled trap` 丢给通用 fallback。
- OID 能显示为 `HUAWEI-xxx-MIB::symbol` 只代表 MIB 解析成功，不代表已有专用
  业务处理器。

需要同时保留 handler 事件和完整原始 Trap 时可配置：

```bash
lnms config:set snmptraps.eventlog 'all'
lnms config:set snmptraps.eventlog_detailed true
```

Huawei 物理管理接口状态示例：

- `HUAWEI-IF-EXT-MIB::hwPhysicalAdminIfDown`：记录私有 Trap 名称，将对应端口
  `ifAdminStatus` 和 `ifOperStatus` 更新为 Down。
- `HUAWEI-IF-EXT-MIB::hwPhysicalAdminIfUp`：记录私有 Trap 名称，将对应端口
  状态更新为 Up。
- 两者使用 `IF-MIB::ifIndex` 关联端口；找不到端口时仍保存详细 Trap，并在
  snmptrapd 日志中记录警告。

这两个私有通知的 MIB 描述针对物理管理接口语义。普通 GE 业务接口应启用并使用
标准 `IF-MIB::linkDown/linkUp`；Huawei VRP 上需要显式打开 IFNET 的 `linkdown`
和 `linkup` Trap。仅执行 `undo shutdown` 但 `ifOperStatus` 没有变化时，不会产生
标准链路通知。

验证运行命令和代表性 OID：

```powershell
docker compose -f docker/compose.yml -f containerlab/compose.override.yml exec snmptrapd `
  sh -lc 'ps auxww | grep "[s]nmptrapd"'

docker compose -f docker/compose.yml -f containerlab/compose.override.yml exec snmptrapd `
  snmptranslate -M /opt/librenms/mibs:/opt/librenms/mibs/huawei `
  -m HUAWEI-IF-EXT-MIB -On HUAWEI-IF-EXT-MIB::hwPhysicalAdminIfDown
```

当前生效集共有 6676 条通知定义，去重后为 6670 个通知。其中 13 个命中专用
Handler，其余 6657 个由 `HuaweiGenericTrap` 处理。6 条重复定义保留在映射文件的
`definition_count` 字段中，不会形成重复路由。此数量是当前 MIB 基线的审计结果，
升级 MIB 后应以重新生成的 `summary` 为准。

Trap 的选择顺序固定如下：

1. 首先查找 `config/snmptraps.php` 中的完整 `MIB::notification` 精确映射。
2. 没有精确映射，但模块名属于 `HUAWEI`、`HWMUSA`、`ISM`、`OPTIX` 或 `NQA`
   厂商前缀时，进入 `HuaweiGenericTrap`。
3. 其他厂商且没有精确映射的 Trap 才进入 LibreNMS 原有通用 fallback。

`HuaweiGenericTrap` 会保存符号化 Trap 名称、完整 VarBind、设备引用和推断严重性，
但不会猜测并修改端口、传感器或告警对象状态。需要改变 LibreNMS 对象状态的通知，
必须增加专用 Handler。

查看全量映射及统计：

```powershell
$map = Get-Content mibs/huawei-trap-handler-map.json -Raw | ConvertFrom-Json
$map.summary
$map.notifications.'HUAWEI-IF-EXT-MIB::hwPhysicalAdminIfDown'
$map.notifications.'HUAWEI-LLDP-MIB::hwLldpInterfaceRemTablesChange'
```

Windows PowerShell 5 的 `ConvertFrom-Json` 对仅大小写不同的 JSON 属性不兼容。遇到该
限制时使用 PowerShell 7，或直接在编辑器中搜索完整 `MIB::notification` 键。

## 7. 新增专用 Trap Handler

只有确认通知具有明确业务语义并需要更新 LibreNMS 对象时，才增加专用 Handler：

1. 用真实设备 Trap 或厂家文档确认通知 OID、VarBind、恢复通知及状态语义。
2. 在 `LibreNMS/Snmptrap/Handlers/` 新建实现 `SnmptrapHandler` 的类。
3. 从 VarBind 读取稳定索引，通过设备关联定位端口、传感器或其他模型。
4. 找不到关联对象时仍保存结构化事件并记录警告，不可静默丢弃 Trap。
5. 在 `config/snmptraps.php` 添加完整通知名到 Handler 类的精确映射。
6. 在 `tests/Feature/SnmpTraps/` 增加正常、恢复和未知对象降级测试。
7. 重新生成 `mibs/huawei-trap-handler-map.json`，确认目标通知从
   `HuaweiGenericTrap` 变为新 Handler。

接口管理状态可参考 `HuaweiPhysicalAdminIfDown` 和
`HuaweiPhysicalAdminIfUp`；环路检测可参考现有 LDT Handler；厂家告警可参考
`HuaweiAlarmTrap`。不要为 6670 个通知机械创建空 Handler：无状态副作用的通知由
通用 Handler 保留完整信息，更易维护；只有具备可靠语义的通知才专门适配。

## 8. MIB 变化后的 Trap 审计

每次导入新 MIB 后必须：

1. 重新生成 `huawei-manifest.json`、`huawei-trap-modules.list` 和
   `huawei-trap-handler-map.json`。
2. 对比映射 `summary`、新增通知、删除通知和 `definition_count` 变化。
3. 新通知默认由 `HuaweiGenericTrap` 接管；逐项评估其中会改变接口、资源、实体或
   告警状态的通知是否需要专用 Handler。
4. 新增专用 Handler 后同步修改 `config/snmptraps.php`、功能测试和操作手册。
5. 重建并强制重建 LibreNMS、dispatcher、operations-worker 与 snmptrapd。
6. 在容器内执行 `snmptranslate`，再发送模拟 Trap 和至少一条真实设备 Trap。

单元测试会校验所有 `NOTIFICATION-TYPE` 均出现在全量映射中，因此 MIB 新增通知但
忘记重新生成映射时，测试会直接失败。

## 9. 回滚

发现 discovery、polling 或 Trap 异常时：

1. 回退本次替换的 `mibs/huawei` 模块
2. 恢复上一个 `mibs/huawei-manifest.json`
3. 恢复上一个 `mibs/huawei-source-version.txt`
4. 重新生成 `mibs/huawei-trap-modules.list` 和
   `mibs/huawei-trap-handler-map.json`
5. 重建自定义镜像并强制重建 LibreNMS 与 snmptrapd sidecar

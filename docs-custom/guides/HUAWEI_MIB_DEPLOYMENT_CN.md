# Huawei MIB 多版本兼容实施与维护手册

## 1. 目录约定

- 运行时生效目录：`mibs/huawei`
- 原始归档目录：`mib-archives/huawei`
- 机器清单：`mibs/huawei-manifest.json`
- Trap 模块清单：`mibs/huawei-trap-modules.list`
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
6. 运行审计并重新生成机器清单与 Trap 模块清单：

```powershell
docker run --rm --entrypoint /bin/sh -v "${PWD}:/work" -w /work `
  mrja/librenms:26.5.1-custom -lc `
  "php scripts/huawei-mib-workflow.php audit &&
   php scripts/huawei-mib-workflow.php manifest > mibs/huawei-manifest.json &&
   php scripts/huawei-mib-workflow.php trap-modules > mibs/huawei-trap-modules.list"
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
3. 恢复上一个 `mibs/huawei-source-version.txt`
4. 重新生成 `mibs/huawei-trap-modules.list`
5. 重建自定义镜像并强制重建 LibreNMS 与 snmptrapd sidecar

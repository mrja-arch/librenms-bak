# LibreNMS 园区网络部署与运维操作手册

本文适用于 `26.5.1-custom` 分支，覆盖 Windows 11、WSL2、Docker
Compose、Containerlab 园区实验、设备发现审批、拓扑感知、监控和告警。

## 1. 架构与地址

实验拓扑为核心、汇聚、接入三层：

| 层级 | 节点 | 管理地址 | 说明 |
| --- | --- | --- | --- |
| 管理 | LibreNMS | `172.31.255.2` | Docker Compose |
| 核心 | core01 | `172.31.255.11` | Huawei CE12800 VRP 8.180 |
| 汇聚 | agg01 | `172.31.255.21` | Huawei CE12800 VRP 8.180、OSPF |
| 接入 | access11-access12 | `172.31.255.31-32` | Huawei CE12800 VRP 8.180、VLAN Trunk |

SNMP v2c 团体字为 `librenms-lab`，只用于隔离的实验网络。生产环境应使用
SNMPv3，并通过访问控制限制 LibreNMS 管理地址。

## 2. Windows、WSL2 与 Docker

以管理员 PowerShell 执行：

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\containerlab\scripts\install-wsl.ps1
```

重启后进入 Ubuntu 24.04，确认 Docker Desktop 已启用该 WSL2 发行版集成，再执行：

```bash
cd /mnt/e/vscode/librenms
docker version
```

在 Docker Desktop 设置中开启 Ubuntu 24.04 的 WSL Integration。部署脚本会在
Docker Desktop 引擎内运行特权 Containerlab 容器，以便正确创建虚拟链路；无需在
Ubuntu 中单独安装 Containerlab。

构建并启动自有 LibreNMS 镜像：

```powershell
docker compose -f docker/compose.yml -f docker/compose.dev.yml build librenms
docker compose -f docker/compose.yml up -d
docker compose -f docker/compose.yml ps
```

浏览器访问 `http://localhost:8000/`。首次部署完成管理员创建和数据库初始化。

## 3. CE12800 镜像与园区拓扑

当前拓扑默认使用 Docker Hub 镜像
`windddkz/huawei_vrp:ce12800-8.180`。该镜像包含虚拟机软件，使用前请确认镜像来源和
授权符合你的组织要求。进入 WSL2 执行：

```bash
cd /mnt/e/vscode/librenms
docker pull windddkz/huawei_vrp:ce12800-8.180
./containerlab/scripts/up.sh
```

`up.sh` 会依次检查 KVM、构建轻量汇聚/接入节点、部署 Containerlab、将 LibreNMS
接入 `campus-mgmt`，并从 LibreNMS 容器验证 4 个节点的 SNMP。CE12800 首次启动通常
需要数分钟；脚本出现 `SNMP WAIT` 时可稍后执行：

```bash
./containerlab/scripts/status.sh
docker compose -f docker/compose.yml -f containerlab/compose.override.yml \
  exec librenms snmpget -v2c -c librenms-lab 172.31.255.11 SNMPv2-MIB::sysName.0
```

如需临时使用其他镜像标签，可在命令前设置 `CE_IMAGE`，无需修改拓扑文件。

四台 VRP 的管理服务同时发布到 Windows 宿主机，便于从宿主机直接调试：

| 节点 | SSH | SNMP/UDP | HTTP | HTTPS | NETCONF |
| --- | ---: | ---: | ---: | ---: | ---: |
| core01 | `2201` | `16101` | `8001` | `8441` | `8301` |
| agg01 | `2202` | `16102` | `8002` | `8442` | `8302` |
| access11 | `2211` | `16111` | `8011` | `8451` | `8311` |
| access12 | `2212` | `16112` | `8012` | `8452` | `8312` |

例如，从宿主机读取核心设备：

```powershell
ssh -p 2201 admin@127.0.0.1
snmpget -v2c -c librenms-lab 127.0.0.1:16101 SNMPv2-MIB::sysName.0
```

LibreNMS 不经过这些宿主端口，而是通过共享管理网络直接访问
`172.31.255.11/21/31/32:161/udp`。

销毁实验拓扑：

```bash
./containerlab/scripts/destroy.sh
```

## 4. 网络协议检查

核心至汇聚使用 `10.255.0.0/31`，运行 OSPF Area 0。
汇聚承载 VLAN 110、120。

常用检查：

```bash
docker exec -it clab-huawei-campus-agg01 vtysh -c "show ip ospf neighbor"
docker exec -it clab-huawei-campus-agg01 lldpcli show neighbors
docker exec -it clab-huawei-campus-agg01 bridge vlan show
snmpget -v2c -c librenms-lab 172.31.255.21 SNMPv2-MIB::sysName.0
```

如果 OSPF 邻居未建立，先检查链路接口地址和接口状态；如果拓扑无链路，检查两端
LLDP 是否开启以及 LibreNMS 是否已经完成一次设备发现。

## 5. 自动发现与审批

在全局设置的“自动发现”中配置 `nets`，例如：

```text
172.31.255.0/24
```

排除网段继续使用 `autodiscovery.nets-exclude`。Web 扫描必须完全位于 `nets`
中，每次最多 4096 个地址。

进入“设备 > 自动发现”：

1. 在“可信网段”中输入 CIDR，点击“开始扫描”。
2. 在“扫描记录”查看排队、执行、进度和错误。
3. 在“候选设备”核对 IP、sysName、OS、Ping、SNMP 和发现来源。
4. 点击“纳管”，设置 Hostname、Poller Group、端口关联模式。
5. SNMP 不可用且只需 Ping 监控时，显式启用“Ping 回退”。
6. 批量选择候选设备后可以统一纳管。
7. “忽略”会持续保留忽略状态；需要重新评估时点击“恢复”。

![自动发现候选审批页面](../assets/automatic-discovery-cn.png)

LLDP、CDP、FDP、OSPF、OSPFv3、BGP 等官方自动发现入口在审批模式下也只会写入
候选池。关闭 `autodiscovery.require_approval` 后才恢复官方直接纳管行为。

候选表不保存 Community、SNMPv3 用户或密码。点击纳管时，系统重新读取全局 SNMP
设置并调用 LibreNMS 原生验证流程。

## 6. 拓扑感知

设备纳管后先执行一次“立即发现”，再进入“地图 > 网络”检查链路。拓扑依赖：

- 两端设备均已纳管。
- LLDP/CDP/FDP 发现模块已启用。
- 接口索引和端口关联模式稳定。
- 轮询器可访问设备管理地址和 SNMP。

链路缺失时依次检查设备事件记录、发现日志、邻居表和端口状态。不要通过手工编辑
数据库补链路，先修复协议或设备标识。

## 7. 设备、端口、图形与日志

在设备详情页重点检查：

- 概况：在线状态、硬件、版本、运行时间。
- 端口：状态、速率、双工、流量、错误、描述。
- 健康状况：CPU、内存、温度、电源和风扇。
- 图形：Bits、单播包、非单播包、错误和 Ping 性能。
- 日志：事件记录、Syslog、宕机记录。

首次纳管后图形需要至少两个轮询周期才会形成趋势。端口计数异常时先确认 SNMP
视图、设备 MIB 和端口关联模式。

### 7.1 端口存在但图形显示 No Data

端口清单由 discovery 写入数据库，流量计数和 `port-id*.rrd` 文件由 poller
周期性生成。因此“能看到端口”只说明发现成功，不能证明轮询器正常。页面显示
`No Data file port-idXX.rrd` 时按以下顺序检查：

```powershell
$dc = "docker compose -f docker/compose.yml -f containerlab/compose.override.yml"

# dispatcher 必须为 Up，日志中应持续出现 Polling device 和 completed
Invoke-Expression "$dc ps"
Invoke-Expression "$dc logs --tail 200 dispatcher"

# 将 4 替换为设备 ID；调试时只执行 ports 模块
Invoke-Expression "$dc exec -T -u librenms librenms php poller.php -h 4 -m ports -d"

# 将地址替换为目标设备，确认端口 RRD 已创建
Invoke-Expression "$dc exec -T librenms sh -lc 'ls -l /data/rrd/172.31.255.11/port-id*.rrd'"
```

还应在数据库或调试输出中确认设备 `last_polled`、端口 `poll_time`、
`ifInOctets` 和 `ifOutOctets` 持续更新。首次轮询只创建数据源，至少等待两个
完整轮询周期；实验链路没有业务流量时，图形会是正常的 0 bps 平线。

本项目曾出现 dispatcher 日志报
`env: 'python3\r': No such file or directory`。这是 Windows CRLF 进入镜像中的
shebang 脚本，导致 poller 根本没有运行。`docker/Dockerfile.huawei` 已在构建时
统一清理 shebang 行的 CR；再次出现时执行：

```powershell
docker compose -f docker/compose.yml build --no-cache librenms
docker compose -f docker/compose.yml -f containerlab/compose.override.yml `
  up -d --force-recreate librenms dispatcher operations-worker snmptrapd
docker compose -f docker/compose.yml -f containerlab/compose.override.yml `
  logs --tail 200 dispatcher
```

如果轮询正常但计数取不到，再用 `snmpget/snmpwalk` 检查 IF-MIB 的
`ifHCInOctets`、`ifHCOutOctets`、`ifInOctets` 和 `ifOutOctets`，并检查 Huawei
SNMP MIB view 是否允许访问这些 OID。

## 8. 告警规则与传送

在“告警”菜单中依次配置：

1. 告警规则：定义设备、端口、传感器或协议条件。
2. 告警模板：设置通知正文和恢复正文。
3. 告警传送：配置邮件、Webhook 等目标。
4. Operations：设置升级步骤、重复间隔和传送组合。
5. 定期维护：在变更窗口内抑制计划内告警。

创建规则后先使用规则预览确认匹配设备，再启用通知。生产环境应先在测试传送目标上
验证恢复通知和重复通知行为。

## 9. 告警监控与故障定位

“通知”显示当前告警，“告警历史”用于审计状态变化。处理流程：

1. 核对时间、规则、设备、位置和消息。
2. 查看设备可用性、端口状态和对应性能图形。
3. 查看事件记录、Syslog、Trap 和发现/轮询日志。
4. 确认告警并填写处置说明。
5. 故障恢复后确认恢复事件和通知已经产生。

确认只代表运维人员已接手，不会改变设备状态或强制关闭告警。

## 10. Web 运维任务中心

“设备 > 运维任务中心”提供受控操作：

- 立即发现设备。
- 立即轮询设备。
- Ping 连通性检测。
- 查看发起人、状态、耗时、结果和错误摘要。

任务由独立 `operations` 队列执行，不阻塞 Web 请求。只有具有设备创建权限的管理员
可以发起扫描、审批和任务。

![运维任务中心](../assets/operation-center-cn.png)

## 11. 必须使用 CLI 的操作

以下操作影响范围大或需要交互式诊断，不在 Web 中开放：

- LibreNMS 升级、数据库迁移和回滚。
- 插件安装、升级和生命周期管理。
- 原始 `snmpwalk`、MIB 调试和抓包。
- 数据库维护、备份恢复和批量修复。
- 任意 Artisan、Shell 命令或高级 `config:set`。

这项限制避免 Web 权限被扩展为主机命令执行权限，并保留完整终端审计记录。

## 12. 与 Oxidized 集成

Oxidized 通过 LibreNMS API 获取设备清单，再通过共享的 `campus-mgmt` 网络直接
访问设备管理地址的 TCP/22。它不使用宿主机映射的 SSH 端口。当前 Huawei VRP
设备模型为 `vrp`，备份结果保存在 `oxidized-output` 命名卷中的 Git 仓库。

1. 在 LibreNMS 的“API > API 设置”中为专用账号创建令牌。
2. 在本机 `docker/.env` 设置以下值，不要把真实密码或令牌提交到 Git：

```dotenv
OXIDIZED_USERNAME=admin
OXIDIZED_PASSWORD=change-me
OXIDIZED_API_TOKEN=replace-with-librenms-api-token
```

生产环境应使用只读设备账号、独立 LibreNMS API 账号和 SSH 密钥。实验环境默认
账号仅用于验证流程。

启动 Oxidized 并启用 LibreNMS 集成：

```powershell
$dc = "docker compose -f docker/compose.yml -f containerlab/compose.override.yml"
Invoke-Expression "$dc --profile oxidized up -d oxidized"

Invoke-Expression "$dc exec -T -u librenms librenms php artisan config:set oxidized.enabled true"
Invoke-Expression "$dc exec -T -u librenms librenms php artisan config:set oxidized.url http://oxidized:8888"
Invoke-Expression "$dc exec -T -u librenms librenms php artisan config:set oxidized.features.versioning true"
Invoke-Expression "$dc exec -T -u librenms librenms php artisan config:set oxidized.reload_nodes true"
```

`docker/oxidized/config` 从
`http://librenms:8000/api/v0/oxidized` 获取设备，REST 服务监听
`0.0.0.0:8888`。验证节点状态和备份结果：

```powershell
Invoke-Expression "$dc --profile oxidized logs --tail 200 oxidized"
Invoke-RestMethod http://localhost:8888/nodes.json |
  Select-Object name, model, status, time

Invoke-Expression "$dc --profile oxidized exec -T oxidized sh -lc `
  'git --git-dir=/home/oxidized/.config/oxidized/output/configs.git log --oneline --all -8'"
```

日志出现 `Loaded 4 nodes`、每台设备状态为 `success`，且 Git 仓库中存在四个管理
地址文件后，LibreNMS 设备页的配置和版本差异功能即可使用。

| Oxidized 现象 | 检查 |
| --- | --- |
| API 返回 401/403 | `OXIDIZED_API_TOKEN` 是否有效，API 账号是否仍启用 |
| `librenms:80` 拒绝连接 | 容器内 Web 端口是 `8000`，源 URL 必须使用该端口 |
| `Loaded 0 nodes` | API 响应、设备 OS/model 映射和 `oxidized.enabled` |
| LibreNMS 无法连接 Oxidized | REST 必须监听 `0.0.0.0:8888`，两容器需在默认网络 |
| SSH 超时或认证失败 | Oxidized 是否加入 `campus-mgmt`、设备 TCP/22、账号和 VRP AAA |
| 没有配置版本 | 检查 `output.git.repo`、命名卷权限和 Oxidized 日志 |

## 13. 备份、升级与镜像重建

备份数据库和持久化卷：

```powershell
docker compose -f docker/compose.yml exec db mariadb-dump -ulibrenms -plibrenms librenms > librenms.sql
docker run --rm -v librenms_librenms-data:/data -v ${PWD}:/backup alpine \
  tar czf /backup/librenms-data.tgz -C /data .
```

升级时从新的官方 Tag 创建升级分支，逐个迁移自定义提交并运行测试，不要将旧分支完整
覆盖到新版本。重建：

```powershell
docker compose -f docker/compose.yml build --no-cache librenms
docker compose -f docker/compose.yml up -d --force-recreate
docker compose -f docker/compose.yml logs --tail 200 librenms operations-worker
```

## 14. 常见故障

| 现象 | 检查 |
| --- | --- |
| 页面 HTTP 500 | `docker compose logs librenms`、迁移状态、缓存和文件权限 |
| 扫描一直排队 | `operations-worker` 是否运行、数据库队列是否可用 |
| 扫描被拒绝 | CIDR 是否完全位于 `nets`、是否命中排除网段、是否超过 4096 |
| 纳管失败 | Ping、SNMP、重复 Hostname/sysName、全局凭据 |
| 拓扑无链路 | LLDP/CDP、两端纳管状态、发现任务、端口关联模式 |
| 图形无数据 | 检查 dispatcher、`last_polled`、`poll_time`、端口 RRD 和 SNMP 计数 |
| `python3\r` | Windows CRLF 破坏 shebang；无缓存重建镜像并重建 poller 容器 |
| Oxidized 无配置 | 检查 API 令牌、共享管理网、SSH、VRP model 和 Git 输出目录 |
| CE12800 启动慢 | 检查 KVM、内存、CPU 虚拟化；首次启动可能需要较长时间 |

关键页面截图存放在 `docs-custom/assets/`，用于版本发布前复核界面和手册描述。

## 15. Huawei MIB 更新

当前 Huawei 生效集位于宿主机 `mibs/huawei`，镜像内路径为
`/opt/librenms/mibs/huawei`。`SNMP_EXTRA_MIB_DIRS` 已指向该目录。厂家包版本记录在
`mibs/huawei-source-version.txt`，模块及哈希记录在
`mibs/huawei-manifest.json`。

未来收到新 MIB 包时，不能只把文件复制到目录后重启容器。必须依次完成：

1. 记录原始 ZIP 的版本和 SHA-256，并保留旧版用于回滚。
2. 使用 `scripts/huawei-mib-workflow.php import` 导入 Huawei 私有模块。
3. 审计新增、删除及变更的 OID、Trap 和 VarBind。
4. 执行 `audit`，重新生成 manifest 与 Trap 模块清单。
5. 检查 discovery、polling、OS 定义和 Trap handler 是否需要同步适配。
6. 重建 `librenms` 镜像，并强制重建 `librenms`、`dispatcher`、
   `operations-worker`、`snmptrapd`。
7. 对比宿主和容器内文件数量、关键文件 SHA-256，运行 `snmptranslate`、
   discovery、poller 和 Trap 回归。

完整 PowerShell 命令、差异报告和回滚步骤见
`docs-custom/guides/HUAWEI_MIB_DEPLOYMENT_CN.md`。

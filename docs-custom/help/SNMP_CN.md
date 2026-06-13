# SNMPv3 与自定义 OID

## SNMPv3

设备和 LibreNMS 的用户名、认证算法、认证密码、加密算法及加密密码必须完全一致。页面中不可选的 SHA2 或 AES 扩展算法通常表示当前 Net-SNMP 构建不支持，应先在服务器执行 `snmpget` 验证。

## 添加前验证

```bash
snmpget -v3 -l authPriv -u <USER> -a SHA -A '<AUTH_PASSWORD>' \
  -x AES -X '<PRIV_PASSWORD>' <DEVICE_IP> sysObjectID.0
```

## 自定义 OID

自定义 OID 适合采集 LibreNMS 尚未内置的单值指标。先用 `snmpget` 验证 OID、类型和权限，再在设备的自定义 OID 页面添加。计数器、状态值和带单位数值应选择匹配的数据类型与转换规则。

## 无数据排查

检查设备 MIB View、ACL、SNMP 版本、OID 实例后缀和轮询日志。MIB 文件只负责名称解析，不会自动创建发现或轮询逻辑。

# API 与 Token 使用

## 创建 Token

登录后进入“API -> API 访问”，为具体用户创建 Token。Token 继承该用户权限，应使用最小权限账号并设置独立用途。

## 调用方式

请求头使用 `X-Auth-Token` 携带 Token。API 地址以当前 LibreNMS 部署地址为准，例如 `/api/v0/devices`。

```bash
curl -H "X-Auth-Token: <TOKEN>" "https://librenms.example/api/v0/devices"
```

## 安全要求

- 不要把 Token 写入代码仓库、截图或聊天记录。
- 自动化系统使用独立账号和独立 Token。
- Token 泄露后立即撤销并重新签发。
- 调用失败时记录 HTTP 状态码和脱敏后的响应正文。

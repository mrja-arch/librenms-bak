<?php

return [
    'topics' => [
        'campus-operations' => [
            'title' => '园区部署与日常运维',
            'summary' => '园区拓扑、设备纳管、发现、轮询、Trap、链路与常见故障处理。',
            'file' => 'docs-custom/guides/LIBRENMS_CAMPUS_OPERATIONS_CN.md',
        ],
        'deployment' => [
            'title' => 'Docker 部署与升级',
            'summary' => '容器部署、升级、重建、备份和运行状态检查。',
            'file' => 'docs-custom/guides/DOCKER_DEPLOYMENT_CN.md',
        ],
        'device-onboarding' => [
            'title' => '设备纳管与 SNMP',
            'summary' => '设备添加、自动发现、SNMPv2c、SNMPv3 和连通性检查。',
            'file' => 'docs-custom/guides/DEVICE_ONBOARDING_CN.md',
        ],
        'huawei-mib' => [
            'title' => 'Trap、Huawei MIB 与 LLDP',
            'summary' => 'Huawei MIB 加载、Trap 处理、LLDP 邻居和专用 Handler 开发。',
            'file' => 'docs-custom/guides/HUAWEI_MIB_DEPLOYMENT_CN.md',
        ],
        'alerting' => [
            'title' => '告警规则、通知与模板',
            'summary' => '规则、通知方式、模板、旧模板迁移和常见排查。',
            'file' => 'docs-custom/help/ALERTING_CN.md',
        ],
        'api' => [
            'title' => 'API 与 Token 使用',
            'summary' => '创建 Token、调用 API、权限控制和安全建议。',
            'file' => 'docs-custom/help/API_CN.md',
        ],
        'snmp' => [
            'title' => 'SNMPv3 与自定义 OID',
            'summary' => 'SNMPv3 算法支持、自定义 OID 和采集验证。',
            'file' => 'docs-custom/help/SNMP_CN.md',
        ],
        'poller' => [
            'title' => '轮询器与分布式轮询',
            'summary' => '轮询周期、Worker、分布式轮询和性能诊断。',
            'file' => 'docs-custom/help/POLLER_CN.md',
        ],
        'database' => [
            'title' => '数据库校验与常见故障',
            'summary' => '配置检验、字符集、排序规则、迁移和数据库修复。',
            'file' => 'docs-custom/help/DATABASE_CN.md',
        ],
        'support' => [
            'title' => '公开故障帮助',
            'summary' => '登录故障、权限、页面错误和诊断材料准备。',
            'file' => 'docs-custom/help/SUPPORT_CN.md',
            'public' => true,
        ],
    ],
];

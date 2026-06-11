#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$huaweiDir = $root . '/mibs/huawei';
$rootMibDir = $root . '/mibs';

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'manifest':
        writeJson(buildManifest($huaweiDir));
        break;
    case 'trap-modules':
        echo implode(PHP_EOL, trapModules($huaweiDir)) . PHP_EOL;
        break;
    case 'audit':
        writeJson(runAudit($root, $rootMibDir, $huaweiDir));
        break;
    case 'diff-template':
        $from = $argv[2] ?? 'V600R025C00SPC600';
        $to = $argv[3] ?? 'V800R025C00SPC600';
        echo renderDiffTemplate($from, $to);
        break;
    default:
        fwrite(STDERR, <<<TXT
Usage:
  php scripts/huawei-mib-workflow.php manifest > mibs/huawei-manifest.json
  php scripts/huawei-mib-workflow.php trap-modules > mibs/huawei-trap-modules.list
  php scripts/huawei-mib-workflow.php audit
  php scripts/huawei-mib-workflow.php diff-template V600R025C00SPC600 V800R025C00SPC600

TXT);
        exit(1);
}

function buildManifest(string $huaweiDir): array
{
    $modules = [];
    foreach (sortedMibFiles($huaweiDir) as $file) {
        $name = basename($file);
        $families = moduleFamilies($name);
        $modules[$name] = [
            'file' => 'mibs/huawei/' . $name,
            'mib_name' => extractMibName($file),
            'status' => moduleStatus($name),
            'source_version' => moduleSourceVersion($name),
            'sha256' => hash_file('sha256', $file),
            'device_families' => $families,
            'references' => moduleReferences($families, $name),
        ];
    }

    return [
        'schema' => 'librenms-huawei-mib-manifest-v1',
        'baseline_version' => 'V600R025C00SPC600',
        'future_router_version' => 'V800R025C00SPC600',
        'active_dir' => 'mibs/huawei',
        'archive_dir' => 'mib-archives/huawei',
        'generated_at' => gmdate('c'),
        'modules' => $modules,
    ];
}

function runAudit(string $root, string $rootMibDir, string $huaweiDir): array
{
    $rootFiles = array_flip(array_map('basename', glob($rootMibDir . '/*') ?: []));
    $huaweiFiles = array_map('basename', sortedMibFiles($huaweiDir));
    $duplicateFiles = array_values(array_filter($huaweiFiles, static fn ($file) => isset($rootFiles[$file])));

    $referencedModules = [];
    foreach (familyDefinitions() as $family => $definitionFiles) {
        foreach ($definitionFiles as $definitionFile) {
            foreach (referencedMibs($root . '/' . $definitionFile) as $module) {
                $referencedModules[$module]['families'][$family] = true;
                $referencedModules[$module]['references'][$definitionFile] = true;
            }
        }
    }

    foreach (trapModules($huaweiDir) as $module) {
        $referencedModules[$module]['families']['trap'] = true;
        $referencedModules[$module]['references']['config/snmptraps.php'] = true;
    }

    $missing = [];
    foreach ($referencedModules as $module => $metadata) {
        if (! file_exists($huaweiDir . '/' . $module) && ! file_exists($rootMibDir . '/' . $module)) {
            $missing[$module] = [
                'device_families' => array_keys($metadata['families']),
                'references' => array_keys($metadata['references']),
            ];
        }
    }

    return [
        'baseline_version' => 'V600R025C00SPC600',
        'duplicate_files_in_huawei_dir' => array_values($duplicateFiles),
        'missing_referenced_modules' => $missing,
        'trap_modules' => trapModules($huaweiDir),
    ];
}

function renderDiffTemplate(string $from, string $to): string
{
    return <<<MD
# Huawei MIB Diff Report: {$from} -> {$to}

## 1. Summary
- Old version:
- New version:
- Review date:
- Reviewer:
- Approved for active-set replacement:

## 2. Module Inventory Diff
### 2.1 Added modules
- 

### 2.2 Removed modules
- 

### 2.3 Changed modules
- 

## 3. OID and Symbol Diff
### 3.1 Added OIDs
- 

### 3.2 Removed OIDs
- 

### 3.3 Renames / conflicts
- 

## 4. Trap Diff
### 4.1 Added traps
- 

### 4.2 Removed traps
- 

### 4.3 VarBind shape changes
- 

## 5. LibreNMS Impact
### 5.1 YAML updates
- 

### 5.2 PHP / handler updates
- 

### 5.3 OIDs that should fall back to numeric form
- 

## 6. Regression Validation
- [ ] VRP
- [ ] YunShan
- [ ] SmartAX
- [ ] SmartAX MDU
- [ ] iBMC
- [ ] SMU
- [ ] OptiX RTN
- [ ] OceanStor
- [ ] Huawei UPS
- [ ] Simulated traps
- [ ] Real-device traps

## 7. Release Decision
- Active-set replacement summary:
- Compatibility modules retained:
- Rollback point:

MD;
}

function sortedMibFiles(string $dir): array
{
    $files = glob($dir . '/*') ?: [];
    sort($files);

    return array_values(array_filter($files, 'is_file'));
}

function familyDefinitions(): array
{
    return [
        'vrp' => [
            'resources/definitions/os_detection/vrp.yaml',
            'resources/definitions/os_discovery/vrp.yaml',
        ],
        'yunshan' => [
            'resources/definitions/os_detection/yunshan.yaml',
            'resources/definitions/os_discovery/yunshan.yaml',
        ],
        'smartax' => [
            'resources/definitions/os_detection/smartax.yaml',
            'resources/definitions/os_discovery/smartax.yaml',
        ],
        'smartax-mdu' => [
            'resources/definitions/os_detection/smartax-mdu.yaml',
        ],
        'ibmc' => [
            'resources/definitions/os_detection/ibmc.yaml',
            'resources/definitions/os_discovery/ibmc.yaml',
        ],
        'huawei-smu' => [
            'resources/definitions/os_detection/huawei-smu.yaml',
            'resources/definitions/os_discovery/huawei-smu.yaml',
        ],
        'huawei-optixrtn' => [
            'resources/definitions/os_detection/huawei-optixrtn.yaml',
            'resources/definitions/os_discovery/huawei-optixrtn.yaml',
        ],
        'oceanstor' => [
            'resources/definitions/os_detection/oceanstor.yaml',
            'resources/definitions/os_discovery/oceanstor.yaml',
        ],
        'huaweiups' => [
            'resources/definitions/os_detection/huaweiups.yaml',
            'resources/definitions/os_discovery/huaweiups.yaml',
        ],
    ];
}

function referencedMibs(string $file): array
{
    if (! is_file($file)) {
        return [];
    }

    $contents = file_get_contents($file);
    if ($contents === false) {
        return [];
    }

    if (! preg_match('/^mib:\s*(.+)$/m', $contents, $matches)) {
        return [];
    }

    return array_values(array_filter(array_map('trim', preg_split('/:+/', $matches[1]) ?: [])));
}

function trapModules(string $huaweiDir): array
{
    $modules = [
        'HUAWEI-BASE-TRAP-MIB',
        'HUAWEI-ENTITY-TRAP-MIB',
        'HUAWEI-FWD-RES-TRAP-MIB',
        'HUAWEI-LDT-MIB',
        'HUAWEI-NTP-TRAP-MIB',
        'HUAWEI-SNMP-NOTIFICATION-MIB',
    ];

    $modules = array_values(array_filter($modules, static fn ($module) => is_file($huaweiDir . '/' . $module)));
    sort($modules);

    return $modules;
}

function moduleFamilies(string $module): array
{
    $mapping = [
        'vrp' => ['HUAWEI-WLAN-CONFIGURATION-MIB', 'HUAWEI-WAN-MIB', 'HUAWEI-ENTITY-EXTENT-MIB', 'HUAWEI-ENERGYMNGT-MIB', 'HUAWEI-STACK-MIB'],
        'yunshan' => ['HUAWEI-WAN-MIB'],
        'smartax' => ['HUAWEI-DEVICE-MIB', 'HUAWEI-POWER-MIB', 'HWMUSA-DEV-MIB', 'HUAWEI-XPON-MIB', 'HUAWEI-XPON-COMMON-MIB'],
        'smartax-mdu' => ['HUAWEI-DEVICE-MIB'],
        'ibmc' => ['HUAWEI-SERVER-IBMC-MIB'],
        'huawei-smu' => ['HUAWEI-SITE-MONITOR-MIB'],
        'huawei-optixrtn' => ['OPTIX-BOARD-MANAGE-MIB', 'OPTIX-MISC-MIB', 'OPTIX-NE-MIB', 'OPTIX-OID-MIB', 'OPTIX-RTN-ODU-MGR-MIB'],
        'oceanstor' => ['ISM-HUAWEI-MIB', 'ISM-STORAGE-SVC-MIB', 'HUAWEI-STORAGE-HARDWARE-MIB', 'HUAWEI-STORAGE-SPACE-MIB', 'ISM-PERFORMANCE-MIB'],
        'trap' => ['HUAWEI-LDT-MIB', 'HUAWEI-BASE-TRAP-MIB', 'HUAWEI-ENTITY-TRAP-MIB', 'HUAWEI-FWD-RES-TRAP-MIB', 'HUAWEI-NTP-TRAP-MIB', 'HUAWEI-SNMP-NOTIFICATION-MIB'],
    ];

    $families = [];
    foreach ($mapping as $family => $modules) {
        if (in_array($module, $modules, true)) {
            $families[] = $family;
        }
    }

    sort($families);

    return $families;
}

function moduleReferences(array $families, string $module): array
{
    $references = [];
    foreach ($families as $family) {
        if ($family === 'trap') {
            $references[] = 'config/snmptraps.php';
            continue;
        }

        foreach (familyDefinitions()[$family] ?? [] as $definitionFile) {
            $references[] = $definitionFile;
        }
    }

    if ($module === 'HUAWEI-LDT-MIB') {
        $references[] = 'tests/Feature/SnmpTraps/HuaweiLdtPortLoopDetectTest.php';
        $references[] = 'tests/Feature/SnmpTraps/HuaweiLdtPortLoopDetectRecoveryTest.php';
    }

    $references = array_values(array_unique($references));
    sort($references);

    return $references;
}

function moduleStatus(string $module): string
{
    if (in_array($module, compatibilityModules(), true)) {
        return 'compatibility';
    }

    if (in_array($module, dependencyModules(), true) || ! preg_match('/^(HUAWEI|HWMUSA|ISM|OPTIX|NQA)/', $module)) {
        return 'dependency';
    }

    return 'current';
}

function moduleSourceVersion(string $module): string
{
    return in_array($module, compatibilityModules(), true) ? 'repo-compat' : 'V600R025C00SPC600';
}

function compatibilityModules(): array
{
    return [
        'HUAWEI-WAN-MIB',
        'HUAWEI-POWER-MIB',
        'HUAWEI-ENVIRONMENT-MIB',
        'HUAWEI-XPON-MIB',
        'HUAWEI-XPON-COMMON-MIB',
        'HWMUSA-DEV-MIB',
        'HUAWEI-SERVER-IBMC-MIB',
        'HUAWEI-SITE-MONITOR-MIB',
        'HUAWEI-STORAGE-HARDWARE-MIB',
        'HUAWEI-STORAGE-SPACE-MIB',
        'ISM-HUAWEI-MIB',
        'ISM-STORAGE-SVC-MIB',
        'ISM-PERFORMANCE-MIB',
        'OPTIX-BOARD-MANAGE-MIB',
        'OPTIX-MISC-MIB',
        'OPTIX-NE-MIB',
        'OPTIX-OID-MIB',
        'OPTIX-RTN-ODU-MGR-MIB',
    ];
}

function dependencyModules(): array
{
    return [
        'HUAWEI-MIB',
        'HUAWEI-TC-MIB',
        'HUAWEI-BASE-TRAP-MIB',
        'HUAWEI-DEVICE-MIB',
        'HUAWEI-DEVICE-EXT-MIB',
        'HUAWEI-ENTITY-EXTENT-MIB',
        'HUAWEI-ENTITY-TRAP-MIB',
        'HUAWEI-CPU-MIB',
        'HUAWEI-MEMORY-MIB',
        'HUAWEI-FLASH-MAN-MIB',
        'HUAWEI-LLDP-MIB',
        'HUAWEI-SNMP-EXT-MIB',
        'HUAWEI-SNMP-NOTIFICATION-MIB',
        'HUAWEI-STACK-MIB',
        'HUAWEI-ENERGYMNGT-MIB',
        'HUAWEI-SYS-CLOCK-MIB',
        'HUAWEI-SYS-MAN-MIB',
        'HUAWEI-TASK-MIB',
        'HUAWEI-VLAN-MIB',
        'HUAWEI-USA-MIB',
    ];
}

function extractMibName(string $file): string
{
    $handle = fopen($file, 'r');
    if (! $handle) {
        return basename($file);
    }

    $header = '';
    while (($line = fgets($handle)) !== false) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }

        $header .= ' ' . $trimmed;
        if (str_contains($trimmed, 'DEFINITIONS') && preg_match('/(\S+)\s+(?=DEFINITIONS)/', $header, $matches)) {
            fclose($handle);

            return $matches[1];
        }
    }

    fclose($handle);

    return basename($file);
}

function writeJson(array $data): void
{
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

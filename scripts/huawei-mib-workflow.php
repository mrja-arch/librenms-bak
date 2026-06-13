#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$huaweiDir = $root . '/mibs/huawei';
$rootMibDir = $root . '/mibs';
$docsRoot = 'docs-custom';
$command = $argv[1] ?? 'help';

switch ($command) {
    case 'import':
        $zipPath = $argv[2] ?? '';
        $expectedSha256 = $argv[3] ?? '';
        writeJson(importVendorZip($zipPath, $expectedSha256, $huaweiDir));
        break;
    case 'manifest':
        writeJson(buildManifest($root, $huaweiDir));
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
  php scripts/huawei-mib-workflow.php import /path/to/V600R025C00SPC600_MIB.zip [expected-sha256]
  php scripts/huawei-mib-workflow.php manifest > mibs/huawei-manifest.json
  php scripts/huawei-mib-workflow.php trap-modules > mibs/huawei-trap-modules.list
  php scripts/huawei-mib-workflow.php audit
  php scripts/huawei-mib-workflow.php diff-template V600R025C00SPC600 V800R025C00SPC600 > {$docsRoot}/reports/huawei/HUAWEI_MIB_DIFF_*.md

TXT);
        exit(1);
}

function importVendorZip(string $zipPath, string $expectedSha256, string $huaweiDir): array
{
    if ($zipPath === '' || ! is_file($zipPath)) {
        throw new InvalidArgumentException("Huawei MIB ZIP not found: {$zipPath}");
    }

    if (! class_exists(ZipArchive::class)) {
        throw new RuntimeException('The PHP zip extension is required to import Huawei MIB files.');
    }

    $actualSha256 = hash_file('sha256', $zipPath);
    $sourceVersion = sourceVersionFromFilename($zipPath);
    if ($expectedSha256 !== '' && ! hash_equals(strtolower($expectedSha256), strtolower($actualSha256))) {
        throw new RuntimeException("Huawei MIB ZIP checksum mismatch. Expected {$expectedSha256}, got {$actualSha256}.");
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException("Unable to open Huawei MIB ZIP: {$zipPath}");
    }

    $imported = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entry = str_replace('\\', '/', $zip->getNameIndex($index));
        $filename = basename($entry);
        if (! preg_match('/^HUAWEI-[A-Z0-9-]+\.mib$/i', $filename)) {
            continue;
        }

        $contents = $zip->getFromIndex($index);
        if ($contents === false) {
            $zip->close();
            throw new RuntimeException("Unable to read {$entry} from Huawei MIB ZIP.");
        }

        $module = pathinfo($filename, PATHINFO_FILENAME);
        $target = $huaweiDir . '/' . $module;
        $temporary = $target . '.tmp';
        if (file_put_contents($temporary, $contents) === false || ! rename($temporary, $target)) {
            @unlink($temporary);
            $zip->close();
            throw new RuntimeException("Unable to write imported Huawei MIB: {$target}");
        }

        $imported[] = $module;
    }

    $zip->close();
    file_put_contents(dirname($huaweiDir) . '/huawei-source-version.txt', $sourceVersion . PHP_EOL);
    sort($imported);

    return [
        'source_file' => basename($zipPath),
        'source_version' => $sourceVersion,
        'sha256' => $actualSha256,
        'policy' => 'Import HUAWEI-*.mib only; preserve all other LibreNMS 26.5.1 MIB files.',
        'imported_count' => count($imported),
        'imported_modules' => $imported,
    ];
}

function buildManifest(string $root, string $huaweiDir): array
{
    $references = scanCodeReferences($root);
    $sourceVersion = activeSourceVersion(dirname($huaweiDir));
    $modules = [];

    foreach (sortedMibFiles($huaweiDir) as $file) {
        $name = basename($file);
        $families = moduleFamilies($name);
        $modules[$name] = [
            'file' => 'mibs/huawei/' . $name,
            'mib_name' => extractMibName($file),
            'status' => moduleStatus($name),
            'source_version' => moduleSourceVersion($name, $sourceVersion),
            'sha256' => hash_file('sha256', $file),
            'device_families' => $families,
            'references' => $references[$name] ?? moduleReferences($families, $name),
        ];
    }

    return [
        'schema' => 'librenms-huawei-mib-manifest-v2',
        'baseline_version' => 'V600R025C00SPC600',
        'future_router_version' => 'V800R025C00SPC600',
        'active_dir' => 'mibs/huawei',
        'archive_dir' => 'mib-archives/huawei',
        'docs_dir' => 'docs-custom',
        'generated_at' => gmdate('c'),
        'modules' => $modules,
    ];
}

function runAudit(string $root, string $rootMibDir, string $huaweiDir): array
{
    $rootFiles = array_flip(array_map('basename', glob($rootMibDir . '/*') ?: []));
    $huaweiFiles = array_map('basename', sortedMibFiles($huaweiDir));
    $duplicateFiles = array_values(array_filter($huaweiFiles, static fn ($file) => isset($rootFiles[$file])));
    sort($duplicateFiles);

    $references = scanCodeReferences($root);
    $missing = [];
    foreach ($references as $module => $moduleReferences) {
        if (! fileExistsInMibSet($rootMibDir, $huaweiDir, $module)) {
            $missing[$module] = $moduleReferences;
        }
    }
    ksort($missing);

    return [
        'baseline_version' => 'V600R025C00SPC600',
        'active_dir' => 'mibs/huawei',
        'archive_dir' => 'mib-archives/huawei',
        'docs_dir' => 'docs-custom',
        'duplicate_files_in_huawei_dir' => $duplicateFiles,
        'missing_referenced_modules' => $missing,
        'referenced_modules' => array_keys($references),
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

function scanCodeReferences(string $root): array
{
    $references = [];

    foreach (referenceFiles($root) as $relativePath => $path) {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            continue;
        }

        foreach (extractReferencedModules($contents) as $module) {
            $references[$module][] = $relativePath;
        }
    }

    foreach ($references as $module => $files) {
        $files = array_values(array_unique($files));
        sort($files);
        $references[$module] = $files;
    }

    ksort($references);

    return $references;
}

function referenceFiles(string $root): array
{
    $files = [];
    foreach (glob($root . '/resources/definitions/os_detection/*.yaml') ?: [] as $path) {
        $files[str_replace('\\', '/', substr($path, strlen($root) + 1))] = $path;
    }
    foreach (glob($root . '/resources/definitions/os_discovery/*.yaml') ?: [] as $path) {
        $files[str_replace('\\', '/', substr($path, strlen($root) + 1))] = $path;
    }
    foreach (glob($root . '/LibreNMS/OS/*.php') ?: [] as $path) {
        $files[str_replace('\\', '/', substr($path, strlen($root) + 1))] = $path;
    }
    foreach (glob($root . '/LibreNMS/Snmptrap/Handlers/*.php') ?: [] as $path) {
        $files[str_replace('\\', '/', substr($path, strlen($root) + 1))] = $path;
    }
    foreach (recursiveFiles($root . '/includes/discovery', 'php') as $path) {
        $files[str_replace('\\', '/', substr($path, strlen($root) + 1))] = $path;
    }
    foreach (recursiveFiles($root . '/includes/polling', 'php') as $path) {
        $files[str_replace('\\', '/', substr($path, strlen($root) + 1))] = $path;
    }
    foreach (glob($root . '/tests/Feature/SnmpTraps/*.php') ?: [] as $path) {
        $files[str_replace('\\', '/', substr($path, strlen($root) + 1))] = $path;
    }
    $files['config/snmptraps.php'] = $root . '/config/snmptraps.php';

    ksort($files);

    return $files;
}

function recursiveFiles(string $dir, string $extension): array
{
    if (! is_dir($dir)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    $files = [];
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === strtolower($extension)) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

function extractReferencedModules(string $contents): array
{
    $modules = [];

    if (preg_match_all('/^mib:\s*(.+)$/mi', $contents, $matches)) {
        foreach ($matches[1] as $mibLine) {
            foreach (preg_split('/:+/', $mibLine) ?: [] as $module) {
                $module = trim($module);
                if (isHuaweiModule($module)) {
                    $modules[$module] = true;
                }
            }
        }
    }

    if (preg_match_all('/\b([A-Z0-9-]+-MIB)::/', $contents, $matches)) {
        foreach ($matches[1] as $module) {
            if (isHuaweiModule($module)) {
                $modules[$module] = true;
            }
        }
    }

    return array_keys($modules);
}

function isHuaweiModule(string $module): bool
{
    return (bool) preg_match('/^(HUAWEI|HWMUSA|OPTIX|ISM|NQA)-?[A-Z0-9-]*MIB$/', $module)
        || (bool) preg_match('/^(HUAWEI|HWMUSA|OPTIX|ISM|NQA)-[A-Z0-9-]+$/', $module);
}

function fileExistsInMibSet(string $rootMibDir, string $huaweiDir, string $module): bool
{
    return is_file($huaweiDir . '/' . $module) || is_file($rootMibDir . '/' . $module);
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
            'LibreNMS/OS/Vrp.php',
        ],
        'yunshan' => [
            'resources/definitions/os_detection/yunshan.yaml',
            'resources/definitions/os_discovery/yunshan.yaml',
            'LibreNMS/OS/Yunshan.php',
        ],
        'smartax' => [
            'resources/definitions/os_detection/smartax.yaml',
            'resources/definitions/os_discovery/smartax.yaml',
            'LibreNMS/OS/Smartax.php',
        ],
        'smartax-mdu' => [
            'resources/definitions/os_detection/smartax-mdu.yaml',
            'LibreNMS/OS/SmartaxMdu.php',
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

function moduleFamilies(string $module): array
{
    $mapping = [
        'vrp' => ['HUAWEI-WLAN-CONFIGURATION-MIB', 'HUAWEI-WAN-MIB', 'HUAWEI-ENTITY-EXTENT-MIB', 'HUAWEI-ENERGYMNGT-MIB', 'HUAWEI-STACK-MIB', 'HUAWEI-POE-MIB'],
        'yunshan' => ['HUAWEI-WLAN-CONFIGURATION-MIB', 'HUAWEI-WAN-MIB', 'HUAWEI-ENTITY-EXTENT-MIB', 'HUAWEI-ENERGYMNGT-MIB', 'HUAWEI-STACK-MIB', 'HUAWEI-POE-MIB'],
        'smartax' => ['HUAWEI-DEVICE-MIB', 'HUAWEI-POWER-MIB', 'HWMUSA-DEV-MIB', 'HUAWEI-XPON-MIB', 'HUAWEI-XPON-COMMON-MIB'],
        'smartax-mdu' => ['HUAWEI-DEVICE-MIB'],
        'ibmc' => ['HUAWEI-SERVER-IBMC-MIB'],
        'huawei-smu' => ['HUAWEI-SITE-MONITOR-MIB'],
        'huawei-optixrtn' => ['OPTIX-BOARD-MANAGE-MIB', 'OPTIX-MISC-MIB', 'OPTIX-NE-MIB', 'OPTIX-OID-MIB', 'OPTIX-RTN-ODU-MGR-MIB'],
        'oceanstor' => ['ISM-HUAWEI-MIB', 'ISM-STORAGE-SVC-MIB', 'HUAWEI-STORAGE-HARDWARE-MIB', 'HUAWEI-STORAGE-SPACE-MIB', 'ISM-PERFORMANCE-MIB'],
        'trap' => ['HUAWEI-LDT-MIB', 'HUAWEI-BASE-TRAP-MIB', 'HUAWEI-ENTITY-TRAP-MIB', 'HUAWEI-FWD-RES-TRAP-MIB', 'HUAWEI-NTP-TRAP-MIB', 'HUAWEI-SNMP-NOTIFICATION-MIB', 'ISM-HUAWEI-MIB'],
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
            $references[] = 'LibreNMS/Snmptrap/Handlers';
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

function trapModules(string $huaweiDir): array
{
    $modules = [
        'HUAWEI-BASE-TRAP-MIB',
        'HUAWEI-ENTITY-TRAP-MIB',
        'HUAWEI-FWD-RES-TRAP-MIB',
        'HUAWEI-LDT-MIB',
        'HUAWEI-NTP-TRAP-MIB',
        'HUAWEI-SNMP-NOTIFICATION-MIB',
        'ISM-HUAWEI-MIB',
    ];

    $modules = array_values(array_filter($modules, static fn ($module) => is_file($huaweiDir . '/' . $module)));
    sort($modules);

    return $modules;
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

function moduleSourceVersion(string $module, string $activeSourceVersion): string
{
    return in_array($module, compatibilityModules(), true) ? 'repo-compat' : $activeSourceVersion;
}

function sourceVersionFromFilename(string $path): string
{
    if (preg_match('/(V\d{3}R\d{3}C\d{2}SPC\d{3})/i', basename($path), $matches)) {
        return strtoupper($matches[1]);
    }

    throw new InvalidArgumentException(
        'Unable to detect Huawei source version from ZIP filename. Expected a name such as V600R025C00SPC600_MIB.zip.'
    );
}

function activeSourceVersion(string $mibRoot): string
{
    $versionFile = $mibRoot . '/huawei-source-version.txt';
    if (is_file($versionFile)) {
        $version = trim((string) file_get_contents($versionFile));
        if ($version !== '') {
            return $version;
        }
    }

    return 'V600R025C00SPC600';
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
        'ISM-TC-MIB',
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

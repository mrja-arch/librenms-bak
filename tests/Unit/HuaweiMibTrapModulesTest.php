<?php

namespace LibreNMS\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HuaweiMibTrapModulesTest extends TestCase
{
    public function testTrapModuleListContainsEveryHuaweiNotificationModule(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = json_decode((string) file_get_contents($root . '/mibs/huawei-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $expected = [];

        foreach ($manifest['modules'] as $module => $metadata) {
            $file = $root . '/' . $metadata['file'];
            if (preg_match('/^\s*[A-Za-z][A-Za-z0-9_-]*\s+NOTIFICATION-TYPE\s*$/mi', (string) file_get_contents($file))) {
                $expected[] = $module;
            }
        }

        sort($expected);
        $actual = file($root . '/mibs/huawei-trap-modules.list', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        sort($actual);

        $this->assertNotEmpty($expected);
        $this->assertSame($expected, $actual);
    }

    public function testEveryHuaweiNotificationHasAHandlerMapping(): void
    {
        $root = dirname(__DIR__, 2);
        $mapping = json_decode(
            (string) file_get_contents($root . '/mibs/huawei-trap-handler-map.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $notifications = $mapping['notifications'];

        $this->assertNotEmpty($notifications);
        $this->assertSame(count($notifications), $mapping['summary']['unique_notifications']);
        $this->assertGreaterThanOrEqual(
            count($notifications),
            $mapping['summary']['notification_definitions']
        );
        $this->assertSame(
            count($notifications),
            $mapping['summary']['dedicated'] + $mapping['summary']['generic']
        );
        $this->assertSame(
            'LibreNMS\\Snmptrap\\Handlers\\HuaweiPhysicalAdminIfDown',
            $notifications['HUAWEI-IF-EXT-MIB::hwPhysicalAdminIfDown']['handler']
        );
        $this->assertSame(
            'LibreNMS\\Snmptrap\\Handlers\\HuaweiGenericTrap',
            $notifications['HUAWEI-LLDP-MIB::hwLldpInterfaceRemTablesChange']['handler']
        );
    }
}

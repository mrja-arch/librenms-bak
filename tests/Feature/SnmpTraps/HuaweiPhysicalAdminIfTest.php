<?php

namespace LibreNMS\Tests\Feature\SnmpTraps;

use App\Models\Device;
use App\Models\Port;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LibreNMS\Enum\IfOperStatus;
use LibreNMS\Enum\Severity;
use LibreNMS\Snmptrap\Dispatcher;
use LibreNMS\Snmptrap\Trap;
use LibreNMS\Tests\Traits\RequiresDatabase;
use Log;
use Mockery;

final class HuaweiPhysicalAdminIfTest extends SnmpTrapTestCase
{
    use RequiresDatabase;
    use DatabaseTransactions;

    public function testPhysicalAdminIfDown(): void
    {
        $device = Device::factory()->create();
        $port = Port::factory()->make(['ifAdminStatus' => 'up', 'ifOperStatus' => 'up']);
        $device->ports()->save($port);

        $this->assertTrapLogsMessage("<UNKNOWN>
UDP: [$device->ip]:57123->[192.168.4.4]:162
DISMAN-EVENT-MIB::sysUpTimeInstance 0:0:10:00.00
SNMPv2-MIB::snmpTrapOID.0 HUAWEI-IF-EXT-MIB::hwPhysicalAdminIfDown
IF-MIB::ifIndex.$port->ifIndex $port->ifIndex
IF-MIB::ifName.$port->ifIndex \"$port->ifName\"
IF-MIB::ifOperStatus.$port->ifIndex down
",
            "SNMP Trap: hwPhysicalAdminIfDown down/down $port->ifDescr",
            'Could not handle hwPhysicalAdminIfDown',
            [Severity::Error, 'interface', $port->port_id],
            $device,
        );

        $port->refresh();
        $this->assertEquals(IfOperStatus::Down, $port->ifAdminStatus);
        $this->assertEquals(IfOperStatus::Down, $port->ifOperStatus);
    }

    public function testPhysicalAdminIfUp(): void
    {
        $device = Device::factory()->create();
        $port = Port::factory()->make(['ifAdminStatus' => 'down', 'ifOperStatus' => 'down']);
        $device->ports()->save($port);

        $this->assertTrapLogsMessage("<UNKNOWN>
UDP: [$device->ip]:57123->[192.168.4.4]:162
DISMAN-EVENT-MIB::sysUpTimeInstance 0:0:10:10.00
SNMPv2-MIB::snmpTrapOID.0 HUAWEI-IF-EXT-MIB::hwPhysicalAdminIfUp
IF-MIB::ifIndex.$port->ifIndex $port->ifIndex
IF-MIB::ifName.$port->ifIndex \"$port->ifName\"
IF-MIB::ifOperStatus.$port->ifIndex up
",
            "SNMP Trap: hwPhysicalAdminIfUp up/up $port->ifDescr",
            'Could not handle hwPhysicalAdminIfUp',
            [Severity::Ok, 'interface', $port->port_id],
            $device,
        );

        $port->refresh();
        $this->assertEquals(IfOperStatus::Up, $port->ifAdminStatus);
        $this->assertEquals(IfOperStatus::Up, $port->ifOperStatus);
    }

    public function testUnknownPortStillLogsTrap(): void
    {
        $device = Device::factory()->create();
        $raw = "<UNKNOWN>
UDP: [$device->ip]:57123->[192.168.4.4]:162
SNMPv2-MIB::snmpTrapOID.0 HUAWEI-IF-EXT-MIB::hwPhysicalAdminIfDown
IF-MIB::ifIndex.999 999
IF-MIB::ifName.999 \"GE9/9/9\"
IF-MIB::ifOperStatus.999 down";
        $trap = Mockery::mock(Trap::class . '[log,getDevice]', [$raw]);
        $trap->shouldReceive('getDevice')->andReturn($device);
        $trap->shouldReceive('log')->once()->with(
            $trap->toString(true),
            Severity::Error
        );
        Log::shouldReceive('warning')->once()->with(
            'Snmptrap hwPhysicalAdminIfDown: Could not find port at ifIndex 999 for device: ' . $device->hostname
        );

        $this->assertTrue(Dispatcher::handle($trap));
    }
}

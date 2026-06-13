<?php

namespace LibreNMS\Snmptrap\Handlers;

use App\Models\Device;
use LibreNMS\Enum\IfOperStatus;
use LibreNMS\Enum\Severity;
use LibreNMS\Interfaces\SnmptrapHandler;
use LibreNMS\Snmptrap\Trap;
use Log;

class HuaweiPhysicalAdminIfUp implements SnmptrapHandler
{
    public function handle(Device $device, Trap $trap): void
    {
        $ifIndex = $trap->getOidData($trap->findOid('IF-MIB::ifIndex'));
        $port = $device->ports()->where('ifIndex', $ifIndex)->first();

        if (! $port) {
            $trap->log($trap->toString(true), Severity::Ok);
            Log::warning("Snmptrap hwPhysicalAdminIfUp: Could not find port at ifIndex $ifIndex for device: " . $device->hostname);

            return;
        }

        $port->ifAdminStatus = IfOperStatus::Up;
        $port->ifOperStatus = IfOperStatus::tryFrom($trap->getOidData($trap->findOid('IF-MIB::ifOperStatus'))) ?? IfOperStatus::Up;
        $trap->log(
            "SNMP Trap: hwPhysicalAdminIfUp {$port->ifAdminStatus->value}/{$port->ifOperStatus->value} {$port->ifDescr}",
            Severity::Ok,
            'interface',
            $port->port_id
        );
        $port->save();
    }
}

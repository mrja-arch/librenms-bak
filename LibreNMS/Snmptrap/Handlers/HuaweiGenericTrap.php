<?php

namespace LibreNMS\Snmptrap\Handlers;

use App\Models\Device;
use Illuminate\Support\Str;
use LibreNMS\Enum\Severity;
use LibreNMS\Interfaces\SnmptrapHandler;
use LibreNMS\Snmptrap\Trap;

class HuaweiGenericTrap implements SnmptrapHandler
{
    public function handle(Device $device, Trap $trap): void
    {
        $trapOid = $trap->getTrapOid();
        $reference = $this->firstOidValue($trap, [
            'ifName',
            'entPhysicalName',
            'hwBaseTrapRelativeResource',
            'hwIsmReportingAlarmLocationInfo',
            'hwStorageName',
            'hwEntityTrapEntPhysicalName',
        ]);

        $trap->log(
            $trap->toString(true),
            $this->mapSeverity($trapOid),
            'trap',
            $reference !== '' ? $reference : null
        );
    }

    private function mapSeverity(string $trapOid): Severity
    {
        $name = Str::after($trapOid, '::');

        if (preg_match('/(?:Resume|Recovery|Recovered|Clear|Cleared|Up|Online|Normal|Success)(?:Trap|Notify|Notification|Alarm)?$/i', $name)) {
            return Severity::Ok;
        }

        if (preg_match('/(?:Warning|Warn|Minor)(?:Trap|Notify|Notification|Alarm)?$/i', $name)) {
            return Severity::Warning;
        }

        if (preg_match('/(?:Alarm|Down|Failure|Failed|Error|Critical|Major|Abnormal|Lost|Loss)(?:Trap|Notify|Notification|Alarm)?$/i', $name)) {
            return Severity::Error;
        }

        return Severity::Info;
    }

    private function firstOidValue(Trap $trap, array $searches): string
    {
        foreach ($searches as $search) {
            $oid = $trap->findOid($search);
            if ($oid !== '') {
                $value = trim($trap->getOidData($oid));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}

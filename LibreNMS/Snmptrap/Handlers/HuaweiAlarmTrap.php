<?php

namespace LibreNMS\Snmptrap\Handlers;

use App\Models\Device;
use Illuminate\Support\Str;
use LibreNMS\Enum\Severity;
use LibreNMS\Interfaces\SnmptrapHandler;
use LibreNMS\Snmptrap\Trap;

class HuaweiAlarmTrap implements SnmptrapHandler
{
    public function handle(Device $device, Trap $trap)
    {
        $trapOid = $trap->getTrapOid();
        $title = $this->formatTrapName($trapOid);
        $entity = $this->firstOidValue($trap, [
            'entPhysicalName',
            'hwIsmReportingAlarmLocationInfo',
            'hwStorageName',
        ]);
        $reason = $this->firstOidValue($trap, [
            'hwBaseTrapReasonDescr',
            'hwIsmReportingAlarmFaultTitle',
            'hwPortPhysicalDownReason',
        ]);
        $resource = $this->firstOidValue($trap, [
            'hwBaseTrapRelativeResource',
            'hwIsmReportingAlarmNodeCode',
            'hwIsmReportingAlarmLocationAlarmID',
        ]);
        $value = $this->firstOidValue($trap, [
            'hwBaseUsageValue',
            'hwBaseMemUsageValue',
            'hwStorageSpaceFree',
            'hwStorageSpace',
        ]);
        $threshold = $this->firstOidValue($trap, [
            'hwBaseUsageThreshold',
            'hwBaseMemUsageThres',
            'hwEntityRatedPower',
        ]);
        $unit = $this->firstOidValue($trap, [
            'hwBaseUsageUnit',
            'hwBaseMemThresUnit',
        ]);

        $details = array_filter([
            $entity ? "object=$entity" : null,
            $reason ? "reason=$reason" : null,
            $resource ? "resource=$resource" : null,
            $value ? "value=$value" . ($unit ? $unit : '') : null,
            $threshold ? "threshold=$threshold" . ($unit ? $unit : '') : null,
        ]);

        $message = trim($title . (empty($details) ? '' : ' [' . implode(', ', $details) . ']'));
        $trap->log($message, $this->mapSeverity($trap, $trapOid), 'trap', $entity ?: $resource);
    }

    private function mapSeverity(Trap $trap, string $trapOid): Severity
    {
        if (Str::contains($trapOid, ['Resume', 'Recovery', 'Clear', 'Online', 'On', 'Success'])) {
            return Severity::Ok;
        }

        $severity = strtolower($this->firstOidValue($trap, [
            'hwBaseTrapSeverity',
            'hwIsmReportingAlarmFaultLevel',
        ]));

        if ($severity !== '') {
            return match ($severity) {
                'critical', 'major', 'alarm', 'error' => Severity::Error,
                'minor', 'warning' => Severity::Warning,
                'notice' => Severity::Notice,
                'cleared', 'clear', 'ok', 'normal', 'resume', 'recovery' => Severity::Ok,
                default => Severity::Info,
            };
        }

        if (Str::contains($trapOid, ['Warning'])) {
            return Severity::Warning;
        }

        return Severity::Error;
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

    private function formatTrapName(string $trapOid): string
    {
        $name = Str::after($trapOid, '::');
        $name = preg_replace('/^hw/', 'Huawei ', $name) ?: $name;
        $name = preg_replace('/([a-z])([A-Z])/', '$1 $2', $name) ?: $name;

        return trim($name);
    }
}

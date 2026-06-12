<?php

namespace LibreNMS\Tests\Feature\SnmpTraps;

use LibreNMS\Enum\Severity;

final class HuaweiAlarmTrapTest extends SnmpTrapTestCase
{
    public function testHuaweiCpuAlarmTrap(): void
    {
        $this->assertTrapLogsMessage('{{ hostname }}
UDP: [{{ ip }}]:44289->[1.1.1.1]:162
DISMAN-EVENT-MIB::sysUpTimeInstance 82:19:24:56.09
SNMPv2-MIB::snmpTrapOID.0 HUAWEI-BASE-TRAP-MIB::hwCPUUtilizationRisingAlarm
HUAWEI-BASE-TRAP-MIB::hwBaseTrapSeverity major
ENTITY-MIB::entPhysicalName MPU-1
HUAWEI-BASE-TRAP-MIB::hwBaseTrapRelativeResource cpu-1
HUAWEI-BASE-TRAP-MIB::hwBaseUsageValue 97
HUAWEI-BASE-TRAP-MIB::hwBaseUsageUnit %
HUAWEI-BASE-TRAP-MIB::hwBaseUsageThreshold 90',
            'Huawei CPU Utilization Rising Alarm [object=MPU-1, resource=cpu-1, value=97%, threshold=90%]',
            'Could not handle HUAWEI-BASE-TRAP-MIB::hwCPUUtilizationRisingAlarm trap',
            [Severity::Error, 'trap', 'MPU-1']
        );
    }

    public function testHuaweiIsmAlarmTrap(): void
    {
        $this->assertTrapLogsMessage('{{ hostname }}
UDP: [{{ ip }}]:44289->[1.1.1.1]:162
DISMAN-EVENT-MIB::sysUpTimeInstance 82:19:24:56.09
SNMPv2-MIB::snmpTrapOID.0 ISM-HUAWEI-MIB::hwIsmAlarmReporting
ISM-HUAWEI-MIB::hwIsmReportingAlarmNodeCode controller-a
ISM-HUAWEI-MIB::hwIsmReportingAlarmLocationInfo Enclosure 1
ISM-HUAWEI-MIB::hwIsmReportingAlarmFaultTitle Disk domain degraded
ISM-HUAWEI-MIB::hwIsmReportingAlarmFaultLevel critical
ISM-HUAWEI-MIB::hwIsmReportingAlarmLocationAlarmID DD-1001',
            'Huawei Ism Alarm Reporting [object=Enclosure 1, reason=Disk domain degraded, resource=controller-a]',
            'Could not handle ISM-HUAWEI-MIB::hwIsmAlarmReporting trap',
            [Severity::Error, 'trap', 'Enclosure 1']
        );
    }
}

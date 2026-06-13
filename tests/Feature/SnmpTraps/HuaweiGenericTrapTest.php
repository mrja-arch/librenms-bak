<?php

namespace LibreNMS\Tests\Feature\SnmpTraps;

use LibreNMS\Enum\Severity;

final class HuaweiGenericTrapTest extends SnmpTrapTestCase
{
    public function testUnknownHuaweiAlarmIsHandled(): void
    {
        $raw = '<UNKNOWN>
UDP: [{{ ip }}]:57123->[192.168.4.4]:162
DISMAN-EVENT-MIB::sysUpTimeInstance 0:0:10:00.00
SNMPv2-MIB::snmpTrapOID.0 HUAWEI-LLDP-MIB::hwExampleFailure
HUAWEI-LLDP-MIB::hwExampleReason.0 "fan stopped"
HUAWEI-LLDP-MIB::hwExampleEntity.0 "fan-1"';

        $this->assertTrapLogsMessage(
            $raw,
            "HUAWEI-LLDP-MIB::hwExampleFailure\n" .
                '{"DISMAN-EVENT-MIB::sysUpTimeInstance":"0:0:10:00.00","HUAWEI-LLDP-MIB::hwExampleReason.0":"fan stopped","HUAWEI-LLDP-MIB::hwExampleEntity.0":"fan-1"}',
            'Unknown Huawei alarm was not handled',
            [Severity::Error, 'trap', null]
        );
    }

    public function testUnknownHuaweiRecoveryIsOk(): void
    {
        $raw = '<UNKNOWN>
UDP: [{{ ip }}]:57123->[192.168.4.4]:162
SNMPv2-MIB::snmpTrapOID.0 HUAWEI-LLDP-MIB::hwExampleRecovery
HUAWEI-LLDP-MIB::hwExampleEntity.0 "fan-1"';

        $this->assertTrapLogsMessage(
            $raw,
            "HUAWEI-LLDP-MIB::hwExampleRecovery\n" .
                '{"HUAWEI-LLDP-MIB::hwExampleEntity.0":"fan-1"}',
            'Unknown Huawei recovery was not handled',
            [Severity::Ok, 'trap', null]
        );
    }
}

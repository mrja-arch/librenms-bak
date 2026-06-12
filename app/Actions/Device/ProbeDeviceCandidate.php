<?php

namespace App\Actions\Device;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use Illuminate\Support\Arr;
use LibreNMS\Modules\Core;
use SnmpQuery;

class ProbeDeviceCandidate
{
    public function execute(string $host): array
    {
        $device = new Device([
            'hostname' => $host,
            'port' => LibrenmsConfig::get('snmp.port', 161),
            'transport' => LibrenmsConfig::get('snmp.transports.0', 'udp'),
        ]);
        $ping = app(DeviceIsPingable::class)->execute($device)->success();
        $snmp = $this->detectCredentials($device);

        if (! $snmp) {
            return ['ping_status' => $ping, 'snmp_status' => false];
        }

        $device->sysName = SnmpQuery::device($device)->get('SNMPv2-MIB::sysName.0')->value();
        $device->os = Core::detectOS($device);

        return [
            'ping_status' => $ping,
            'snmp_status' => true,
            'sys_name' => $device->sysName,
            'sys_descr' => $device->sysDescr,
            'sys_object_id' => $device->sysObjectID,
            'os' => $device->os,
            'device_type' => LibrenmsConfig::get("os.{$device->os}.type"),
        ];
    }

    private function detectCredentials(Device $device): bool
    {
        $versions = Arr::wrap(LibrenmsConfig::get('snmp.version', ['v2c', 'v1']));
        $communities = array_filter(Arr::wrap(LibrenmsConfig::get('snmp.community')), 'is_string');
        $v3Credentials = Arr::wrap(LibrenmsConfig::get('snmp.v3', []));

        foreach ($versions as $version) {
            $device->snmpver = $version;

            if ($version === 'v3') {
                foreach ($v3Credentials as $credentials) {
                    $device->fill(Arr::only($credentials, [
                        'authlevel', 'authname', 'authpass', 'authalgo', 'cryptopass', 'cryptoalgo',
                    ]));
                    if (app(DeviceIsSnmpable::class)->execute($device)) {
                        return true;
                    }
                }
            } elseif (in_array($version, ['v1', 'v2c'], true)) {
                foreach ($communities as $community) {
                    $device->community = $community;
                    if (app(DeviceIsSnmpable::class)->execute($device)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

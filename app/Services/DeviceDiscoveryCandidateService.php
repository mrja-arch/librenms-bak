<?php

namespace App\Services;

use App\Enums\DiscoveryCandidateStatus;
use App\Models\DeviceDiscoveryCandidate;
use App\Models\Port;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DeviceDiscoveryCandidateService
{
    public function record(
        string $hostname,
        string $ip,
        string $method,
        ?int $sourceDeviceId = null,
        array|Port|null $interface = null,
        array $attributes = [],
    ): DeviceDiscoveryCandidate {
        $identity = 'ip:' . Str::lower($ip);
        $candidate = DeviceDiscoveryCandidate::firstOrNew(['identity_key' => $identity]);
        $now = now();
        $methods = array_values(array_unique([...($candidate->source_methods ?? []), Str::upper($method)]));

        $candidate->fill(Arr::only($attributes, [
            'sys_name', 'sys_descr', 'sys_object_id', 'os', 'device_type',
            'ping_status', 'snmp_status', 'metadata', 'last_error',
        ]));
        $candidate->ip = $ip;
        $candidate->hostname = rtrim($hostname, '.');
        $candidate->setAttribute('source_methods', $methods);
        $candidate->source_device_id = $sourceDeviceId;
        $candidate->source_port_id = $interface instanceof Port
            ? $interface->port_id
            : (is_array($interface) ? ($interface['port_id'] ?? null) : null);
        $candidate->first_seen_at ??= $now;
        $candidate->last_seen_at = $now;

        if (! $candidate->exists || $candidate->status === DiscoveryCandidateStatus::Failed) {
            $candidate->setAttribute('status', DiscoveryCandidateStatus::Pending->value);
        }

        $candidate->save();

        return $candidate;
    }
}

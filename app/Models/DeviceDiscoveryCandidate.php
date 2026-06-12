<?php

namespace App\Models;

use App\Enums\DiscoveryCandidateStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceDiscoveryCandidate extends BaseModel
{
    protected $fillable = [
        'identity_key', 'ip', 'hostname', 'sys_name', 'sys_descr', 'sys_object_id',
        'os', 'device_type', 'ping_status', 'snmp_status', 'source_methods',
        'source_device_id', 'source_port_id', 'status', 'metadata', 'first_seen_at',
        'last_seen_at', 'approved_at', 'ignored_at', 'approved_by', 'ignored_by',
        'managed_device_id', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => DiscoveryCandidateStatus::class,
            'source_methods' => 'array',
            'metadata' => 'array',
            'ping_status' => 'boolean',
            'snmp_status' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'approved_at' => 'datetime',
            'ignored_at' => 'datetime',
        ];
    }

    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'source_device_id', 'device_id');
    }

    public function managedDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'managed_device_id', 'device_id');
    }
}

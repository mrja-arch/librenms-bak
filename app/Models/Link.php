<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Link extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'local_port_id',
        'local_device_id',
        'remote_port_id',
        'active',
        'protocol',
        'remote_hostname',
        'remote_device_id',
        'remote_port',
        'remote_platform',
        'remote_version',
        'status',
        'missed_discoveries',
        'first_seen_at',
        'last_seen_at',
        'stale_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'stale_at' => 'datetime',
        ];
    }

    // ---- Define Relationships ----
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'local_device_id', 'device_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Port, $this>
     */
    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'local_port_id', 'port_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\Device, $this>
     */
    public function remoteDevice(): HasOne
    {
        return $this->hasOne(Device::class, 'device_id', 'remote_device_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\Port, $this>
     */
    public function remotePort(): HasOne
    {
        return $this->hasOne(Port::class, 'port_id', 'remote_port_id');
    }
}

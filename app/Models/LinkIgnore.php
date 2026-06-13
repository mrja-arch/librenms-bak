<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkIgnore extends BaseModel
{
    protected $fillable = [
        'local_device_id',
        'local_port_id',
        'protocol',
        'remote_hostname',
        'remote_port',
        'created_by',
        'reason',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'local_device_id', 'device_id');
    }

    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'local_port_id', 'port_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
}

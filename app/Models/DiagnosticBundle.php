<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticBundle extends BaseModel
{
    protected $fillable = [
        'operation_task_id',
        'device_id',
        'requested_by',
        'status',
        'path',
        'filename',
        'size',
        'sha256',
        'error',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'user_id');
    }
}

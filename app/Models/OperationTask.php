<?php

namespace App\Models;

use App\Enums\OperationTaskStatus;
use App\Enums\OperationTaskType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationTask extends BaseModel
{
    protected $fillable = [
        'type', 'status', 'device_id', 'candidate_id', 'scan_id', 'requested_by',
        'input', 'output', 'error', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => OperationTaskType::class,
            'status' => OperationTaskStatus::class,
            'input' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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

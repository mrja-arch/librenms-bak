<?php

namespace App\Models;

use App\Enums\OperationTaskStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceDiscoveryScan extends BaseModel
{
    protected $fillable = [
        'status', 'networks', 'total_hosts', 'processed_hosts', 'candidates_found',
        'requested_by', 'started_at', 'completed_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'status' => OperationTaskStatus::class,
            'networks' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'user_id');
    }
}

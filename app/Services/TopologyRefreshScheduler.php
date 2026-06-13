<?php

namespace App\Services;

use App\Enums\OperationTaskStatus;
use App\Enums\OperationTaskType;
use App\Jobs\RunOperationTask;
use App\Models\Device;
use App\Models\OperationTask;
use Illuminate\Support\Facades\Cache;

class TopologyRefreshScheduler
{
    public function schedule(Device $device, string $source): bool
    {
        if (! Cache::add("topology-refresh:{$device->device_id}", true, now()->addMinutes(5))) {
            return false;
        }

        $task = OperationTask::create([
            'type' => OperationTaskType::Discover,
            'status' => OperationTaskStatus::Queued,
            'device_id' => $device->device_id,
            'requested_by' => null,
            'input' => ['source' => $source],
        ]);
        RunOperationTask::dispatch($task->id);

        return true;
    }
}

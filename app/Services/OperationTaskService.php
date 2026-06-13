<?php

namespace App\Services;

use App\Enums\OperationTaskStatus;
use App\Enums\OperationTaskType;
use App\Jobs\RunOperationTask;
use App\Models\Device;
use App\Models\OperationTask;
use Illuminate\Support\Facades\DB;

class OperationTaskService
{
    public function queue(
        Device $device,
        OperationTaskType $type,
        ?int $requestedBy = null,
        array $input = [],
        ?int $candidateId = null,
        ?int $scanId = null,
    ): array {
        [$task, $created] = DB::transaction(function () use ($device, $type, $requestedBy, $input, $candidateId, $scanId): array {
            $existing = OperationTask::query()
                ->where('device_id', $device->device_id)
                ->where('type', $type->value)
                ->whereIn('status', [
                    OperationTaskStatus::Queued->value,
                    OperationTaskStatus::Running->value,
                ])
                ->where('created_at', '>=', now()->subHour())
                ->lockForUpdate()
                ->latest()
                ->first();

            if ($existing) {
                return [$existing, false];
            }

            $task = OperationTask::create([
                'type' => $type,
                'status' => OperationTaskStatus::Queued,
                'device_id' => $device->device_id,
                'candidate_id' => $candidateId,
                'scan_id' => $scanId,
                'requested_by' => $requestedBy,
                'input' => array_merge([
                    'device_name' => $device->displayName(),
                    'hostname' => $device->hostname,
                ], $input),
            ]);

            return [$task, true];
        });

        if ($created) {
            DB::afterCommit(fn () => RunOperationTask::dispatch($task->id));
        }

        return [$task, $created];
    }
}

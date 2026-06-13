<?php

namespace App\Jobs;

use App\Actions\Device\DeviceIsPingable;
use App\Enums\OperationTaskStatus;
use App\Enums\OperationTaskType;
use App\Models\Device;
use App\Models\OperationTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use LibreNMS\Util\ModuleList;
use RuntimeException;
use Throwable;

class RunOperationTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(public int $taskId)
    {
        $this->onQueue('operations');
    }

    public function handle(DeviceIsPingable $ping): void
    {
        $task = OperationTask::with('device')->findOrFail($this->taskId);
        $task->update(['status' => OperationTaskStatus::Running, 'started_at' => DB::scalar('SELECT CURRENT_TIMESTAMP')]);

        try {
            $type = OperationTaskType::from((string) $task->getRawOriginal('type'));
            $output = match ($type) {
                OperationTaskType::Discover => $this->discover($task),
                OperationTaskType::Poll => $this->poll($task),
                OperationTaskType::Ping => $ping->execute($this->device($task))->success()
                    ? __('Ping succeeded.')
                    : __('Ping failed.'),
                default => throw new RuntimeException(__('Unsupported operation type.')),
            };
            $task->update([
                'status' => OperationTaskStatus::Succeeded,
                'output' => $output,
                'completed_at' => DB::scalar('SELECT CURRENT_TIMESTAMP'),
            ]);
        } catch (Throwable $e) {
            $task->update([
                'status' => OperationTaskStatus::Failed,
                'error' => $e->getMessage(),
                'completed_at' => DB::scalar('SELECT CURRENT_TIMESTAMP'),
            ]);
        }
    }

    private function discover(OperationTask $task): string
    {
        (new DiscoverDevice($task->device_id, ModuleList::fromUserOverrides([])))->handle();

        return __('Device discovery completed.');
    }

    private function poll(OperationTask $task): string
    {
        (new PollDevice($task->device_id, ModuleList::fromUserOverrides([])))->handle();

        return __('Device polling completed.');
    }

    private function device(OperationTask $task): Device
    {
        $device = $task->device;
        if (! $device instanceof Device) {
            throw new RuntimeException(__('Operation device was not found.'));
        }

        return $device;
    }
}

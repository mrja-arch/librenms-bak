<?php

namespace App\Console\Commands;

use App\Console\LnmsCommand;
use App\Enums\OperationTaskStatus;
use App\Models\DiagnosticBundle;
use App\Models\Link;
use App\Models\OperationTask;
use Illuminate\Support\Facades\File;

class MaintenanceCleanupTopologyDiagnostics extends LnmsCommand
{
    protected $name = 'maintenance:cleanup-topology-diagnostics';

    public function handle(): int
    {
        $links = Link::query()
            ->where('status', 'stale')
            ->where('stale_at', '<', now()->subDays(30))
            ->delete();

        $bundles = DiagnosticBundle::query()
            ->where('expires_at', '<', now())
            ->get();
        foreach ($bundles as $bundle) {
            if ($bundle->path) {
                File::delete($bundle->path);
            }
            $bundle->delete();
        }

        $staleTasks = OperationTask::query()
            ->where(function ($query): void {
                $query->where(function ($queued): void {
                    $queued->where('status', OperationTaskStatus::Queued->value)
                        ->where('created_at', '<', now()->subHour());
                })->orWhere(function ($running): void {
                    $running->where('status', OperationTaskStatus::Running->value)
                        ->where('started_at', '<', now()->subHour());
                });
            })
            ->update([
                'status' => OperationTaskStatus::Failed->value,
                'error' => 'Operation timed out or its worker stopped unexpectedly.',
                'completed_at' => now(),
            ]);

        $expiredTasks = OperationTask::query()
            ->whereIn('status', [
                OperationTaskStatus::Succeeded->value,
                OperationTaskStatus::Failed->value,
            ])
            ->where('completed_at', '<', now()->subDays(30))
            ->delete();

        $this->info("Deleted $links stale links, {$bundles->count()} expired diagnostic bundles, and $expiredTasks expired operation tasks; marked $staleTasks stale tasks failed.");

        return 0;
    }
}

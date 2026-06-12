<?php

namespace App\Jobs;

use App\Actions\Device\ProbeDeviceCandidate;
use App\Enums\OperationTaskStatus;
use App\Models\DeviceDiscoveryCandidate;
use App\Models\DeviceDiscoveryScan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProbeDiscoveryCandidate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(public int $candidateId, public int $scanId)
    {
        $this->onQueue('operations');
    }

    public function handle(ProbeDeviceCandidate $probe): void
    {
        $candidate = DeviceDiscoveryCandidate::findOrFail($this->candidateId);

        try {
            $candidate->fill($probe->execute($candidate->ip))->save();
            if (! $candidate->ping_status && ! $candidate->snmp_status && $candidate->source_methods === ['SNMP SCAN']) {
                $candidate->delete();
            }
        } catch (Throwable $e) {
            $candidate->update(['last_error' => $e->getMessage()]);
        } finally {
            $scan = DeviceDiscoveryScan::find($this->scanId);
            if ($scan) {
                $scan->increment('processed_hosts');
                if ($candidate->ping_status || $candidate->snmp_status) {
                    $scan->increment('candidates_found');
                }
                $scan->refresh();
                if ($scan->processed_hosts >= $scan->total_hosts) {
                    $scan->update([
                        'status' => OperationTaskStatus::Succeeded,
                        'completed_at' => now(),
                    ]);
                }
            }
        }
    }
}

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
use Illuminate\Support\Facades\DB;
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
            $scanOnly = count((array) $candidate->source_methods) === 1
                && in_array('SNMP SCAN', (array) $candidate->source_methods, true);
            if ($scanOnly && ! $candidate->snmp_status) {
                $candidate->delete();
            }
        } catch (Throwable $e) {
            $candidate->update(['last_error' => $e->getMessage()]);
        } finally {
            $scan = DeviceDiscoveryScan::find($this->scanId);
            if ($scan) {
                $scan->increment('processed_hosts');
                if ($candidate->snmp_status) {
                    $scan->increment('candidates_found');
                }
                $scan->refresh();
                if ($scan->processed_hosts >= $scan->total_hosts) {
                    $scan->update([
                        'status' => OperationTaskStatus::Succeeded,
                        'completed_at' => DB::scalar('SELECT CURRENT_TIMESTAMP'),
                    ]);
                }
            }
        }
    }
}

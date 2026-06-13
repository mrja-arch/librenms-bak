<?php

namespace App\Jobs;

use App\Enums\OperationTaskStatus;
use App\Facades\LibrenmsConfig;
use App\Models\DeviceDiscoveryScan;
use App\Services\DeviceDiscoveryCandidateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use LibreNMS\Util\IP;
use RuntimeException;
use Throwable;

class RunDiscoveryScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public int $scanId)
    {
        $this->onQueue('operations');
    }

    public function handle(DeviceDiscoveryCandidateService $candidates): void
    {
        // Queue workers are long-lived and may retain settings from before the
        // administrator updated the global discovery networks.
        LibrenmsConfig::invalidateAndReload();

        $scan = DeviceDiscoveryScan::findOrFail($this->scanId);
        $scan->update(['status' => OperationTaskStatus::Running, 'started_at' => DB::scalar('SELECT CURRENT_TIMESTAMP')]);

        try {
            $hosts = $this->expandNetworks((array) $scan->networks);
            $scan->update(['total_hosts' => count($hosts)]);

            if ($hosts === []) {
                $scan->update(['status' => OperationTaskStatus::Succeeded, 'completed_at' => DB::scalar('SELECT CURRENT_TIMESTAMP')]);

                return;
            }

            foreach ($hosts as $host) {
                $candidate = $candidates->record($host, $host, 'SNMP scan');
                ProbeDiscoveryCandidate::dispatch($candidate->id, $scan->id);
            }
        } catch (Throwable $e) {
            $scan->update([
                'status' => OperationTaskStatus::Failed,
                'completed_at' => DB::scalar('SELECT CURRENT_TIMESTAMP'),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function expandNetworks(array $networks): array
    {
        $hosts = [];

        foreach ($networks as $network) {
            if (! preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})\/(\d{1,2})$/', trim($network), $matches)) {
                throw new RuntimeException(__('Only IPv4 CIDR networks are supported by active scanning.'));
            }

            $prefix = (int) $matches[2];
            $ip = ip2long($matches[1]);
            if ($ip === false || $prefix < 20 || $prefix > 32) {
                throw new RuntimeException(__('Each scan network must be between /20 and /32.'));
            }

            $size = 2 ** (32 - $prefix);
            $start = $ip & (-1 << (32 - $prefix));
            $first = $size > 2 ? 1 : 0;
            $last = $size > 2 ? $size - 1 : $size;

            for ($offset = $first; $offset < $last; $offset++) {
                $host = long2ip($start + $offset);
                $parsed = IP::parse($host, true);
                if (! $parsed->inNetworks(LibrenmsConfig::get('nets', []))) {
                    throw new RuntimeException(__('Scan networks must be fully contained in the global nets setting.'));
                }
                if ($parsed->inNetworks(LibrenmsConfig::get('autodiscovery.nets-exclude', []))) {
                    continue;
                }

                $hosts[$host] = true;
                if (count($hosts) > 4096) {
                    throw new RuntimeException(__('A scan may contain at most 4096 addresses.'));
                }
            }
        }

        return array_keys($hosts);
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Device\ValidateDeviceAndCreate;
use App\Enums\DiscoveryCandidateStatus;
use App\Enums\OperationTaskStatus;
use App\Enums\OperationTaskType;
use App\Facades\LibrenmsConfig;
use App\Jobs\RunDiscoveryScan;
use App\Jobs\RunOperationTask;
use App\Models\Device;
use App\Models\DeviceDiscoveryCandidate;
use App\Models\DeviceDiscoveryScan;
use App\Models\OperationTask;
use App\Models\PollerGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use LibreNMS\Enum\PortAssociationMode;
use Throwable;

class DeviceDiscoveryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('create', Device::class);

        return view('device.discovery.index', [
            'candidates' => DeviceDiscoveryCandidate::with(['sourceDevice', 'managedDevice'])
                ->latest('last_seen_at')->paginate(25, ['*'], 'candidates_page'),
            'scans' => DeviceDiscoveryScan::with('requester')->latest()->limit(25)->get(),
            'tasks' => OperationTask::with(['device', 'requester'])->latest()->limit(50)->get(),
            'devices' => Device::orderBy('hostname')->get(['device_id', 'hostname', 'sysName']),
            'pollerGroups' => PollerGroup::list(),
            'configuredNetworks' => LibrenmsConfig::get('nets', []),
        ]);
    }

    public function scan(Request $request): RedirectResponse
    {
        Gate::authorize('create', Device::class);
        $validated = $request->validate([
            'networks' => ['required', 'string', 'max:4096'],
        ]);
        $networks = preg_split('/[\s,]+/', trim($validated['networks']), -1, PREG_SPLIT_NO_EMPTY);

        $scan = DeviceDiscoveryScan::create([
            'status' => OperationTaskStatus::Queued,
            'networks' => array_values(array_unique($networks)),
            'requested_by' => $request->user()->user_id,
        ]);
        RunDiscoveryScan::dispatch($scan->id);

        return back()->with('status', __('Discovery scan queued.'));
    }

    public function approve(Request $request, DeviceDiscoveryCandidate $candidate): RedirectResponse
    {
        Gate::authorize('create', Device::class);
        $validated = $request->validate([
            'hostname' => ['nullable', 'string', 'max:255'],
            'poller_group' => ['nullable', 'integer'],
            'port_association_mode' => ['nullable', 'string', 'max:32'],
            'ping_fallback' => ['nullable', 'boolean'],
        ]);

        return $this->approveCandidate($candidate, $validated, $request->user()->user_id);
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        Gate::authorize('create', Device::class);
        $validated = $request->validate([
            'candidates' => ['required', 'array', 'max:100'],
            'candidates.*' => ['integer', 'exists:device_discovery_candidates,id'],
            'poller_group' => ['nullable', 'integer'],
            'port_association_mode' => ['nullable', 'string', 'max:32'],
            'ping_fallback' => ['nullable', 'boolean'],
        ]);
        $approved = 0;

        foreach (DeviceDiscoveryCandidate::whereIn('id', $validated['candidates'])->get() as $candidate) {
            $this->approveCandidate($candidate, $validated, $request->user()->user_id, false);
            if ($candidate->refresh()->status === DiscoveryCandidateStatus::Managed) {
                $approved++;
            }
        }

        return back()->with('status', __(':count candidates were approved.', ['count' => $approved]));
    }

    public function ignore(Request $request, DeviceDiscoveryCandidate $candidate): RedirectResponse
    {
        Gate::authorize('create', Device::class);
        $candidate->update([
            'status' => DiscoveryCandidateStatus::Ignored,
            'ignored_at' => now(),
            'ignored_by' => $request->user()->user_id,
        ]);

        return back()->with('status', __('Candidate ignored.'));
    }

    public function restore(DeviceDiscoveryCandidate $candidate): RedirectResponse
    {
        Gate::authorize('create', Device::class);
        $candidate->update([
            'status' => DiscoveryCandidateStatus::Pending,
            'ignored_at' => null,
            'ignored_by' => null,
            'last_error' => null,
        ]);

        return back()->with('status', __('Candidate restored.'));
    }

    private function approveCandidate(
        DeviceDiscoveryCandidate $candidate,
        array $options,
        int $userId,
        bool $flashError = true,
    ): RedirectResponse {
        if ($candidate->status === DiscoveryCandidateStatus::Managed) {
            return back()->with('status', __('Candidate is already managed.'));
        }

        $device = new Device([
            'hostname' => ($options['hostname'] ?? null) ?: ($candidate->hostname ?: $candidate->ip),
            'overwrite_ip' => $candidate->ip,
            'poller_group' => (int) ($options['poller_group'] ?? 0),
            'port_association_mode' => PortAssociationMode::getId($options['port_association_mode'] ?? 'ifIndex') ?? 1,
        ]);

        try {
            (new ValidateDeviceAndCreate($device, false, (bool) ($options['ping_fallback'] ?? false)))->execute();
            $candidate->update([
                'status' => DiscoveryCandidateStatus::Managed,
                'approved_at' => now(),
                'approved_by' => $userId,
                'managed_device_id' => $device->device_id,
                'last_error' => null,
            ]);

            foreach ([OperationTaskType::Discover, OperationTaskType::Poll] as $type) {
                $task = OperationTask::create([
                    'type' => $type,
                    'status' => OperationTaskStatus::Queued,
                    'device_id' => $device->device_id,
                    'candidate_id' => $candidate->id,
                    'requested_by' => $userId,
                ]);
                RunOperationTask::dispatch($task->id);
            }

            return back()->with('status', __('Device approved and initial discovery was queued.'));
        } catch (Throwable $e) {
            $candidate->update([
                'status' => DiscoveryCandidateStatus::Failed,
                'last_error' => $e->getMessage(),
            ]);

            return $flashError
                ? back()->with('error', $e->getMessage())
                : back();
        }
    }
}

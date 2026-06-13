<?php

namespace App\Http\Controllers;

use App\Enums\OperationTaskType;
use App\Models\Device;
use App\Models\Link;
use App\Models\LinkIgnore;
use App\Services\OperationTaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TopologyLinkController extends Controller
{
    public function index(Request $request): View
    {
        $links = Link::query()
            ->with(['device', 'port', 'remoteDevice', 'remotePort'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('protocol'), fn ($query) => $query->where('protocol', $request->string('protocol')))
            ->latest('last_seen_at')
            ->paginate(50)
            ->withQueryString();

        return view('topology-links.index', [
            'links' => $links,
            'ignores' => LinkIgnore::with(['device', 'port', 'creator'])->latest()->get(),
            'devices' => Device::orderBy('hostname')->get(['device_id', 'hostname', 'sysName']),
            'protocols' => Link::query()->distinct()->orderBy('protocol')->pluck('protocol'),
        ]);
    }

    public function discover(Request $request, OperationTaskService $tasks): RedirectResponse
    {
        $validated = $request->validate(['device_id' => ['required', 'integer', 'exists:devices,device_id']]);
        $device = Device::findOrFail($validated['device_id']);
        [, $created] = $tasks->queue(
            $device,
            OperationTaskType::Discover,
            $request->user()->user_id,
            ['source' => 'topology-links'],
        );

        return back()->with('status', $created
            ? __('Link discovery queued.')
            : __('An operation of this type is already queued or running for this device.'));
    }

    public function destroy(Link $link): RedirectResponse
    {
        $link->delete();

        return back()->with('status', __('Link deleted. It may be discovered again.'));
    }

    public function ignore(Request $request, Link $link): RedirectResponse
    {
        LinkIgnore::firstOrCreate([
            'local_device_id' => $link->local_device_id,
            'local_port_id' => $link->local_port_id,
            'protocol' => $link->protocol,
            'remote_hostname' => $link->remote_hostname,
            'remote_port' => $link->remote_port,
        ], [
            'created_by' => $request->user()->user_id,
            'reason' => $request->string('reason')->toString() ?: null,
        ]);

        Link::query()
            ->where(function ($query) use ($link): void {
                $query->where('local_port_id', $link->local_port_id)
                    ->where('remote_port_id', $link->remote_port_id);
            })
            ->orWhere(function ($query) use ($link): void {
                $query->where('local_port_id', $link->remote_port_id)
                    ->where('remote_port_id', $link->local_port_id);
            })
            ->delete();

        return back()->with('status', __('Link ignored and removed from topology.'));
    }

    public function restore(LinkIgnore $ignore): RedirectResponse
    {
        $ignore->delete();

        return back()->with('status', __('Link ignore rule restored. Run discovery to recreate the link.'));
    }
}

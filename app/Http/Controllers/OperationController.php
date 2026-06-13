<?php

namespace App\Http\Controllers;

use App\Enums\OperationTaskType;
use App\Models\Device;
use App\Models\DiagnosticBundle;
use App\Models\OperationTask;
use App\Services\OperationTaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OperationController extends Controller
{
    public function index(): View
    {
        Gate::authorize('create', Device::class);

        return view('operations.index', [
            'tasks' => OperationTask::with(['device', 'requester'])->latest()->paginate(50),
            'devices' => Device::orderBy('hostname')->get(['device_id', 'hostname', 'sysName']),
            'diagnosticBundles' => DiagnosticBundle::with(['device', 'requester'])->latest()->limit(50)->get(),
        ]);
    }

    public function store(Request $request, Device $device, string $operation, OperationTaskService $tasks): RedirectResponse
    {
        Gate::authorize('create', Device::class);
        $validated = validator(
            ['operation' => $operation],
            ['operation' => [Rule::in(['discover', 'poll', 'ping'])]]
        )->validate();

        [, $created] = $tasks->queue(
            $device,
            OperationTaskType::from($validated['operation']),
            $request->user()->user_id,
            ['source' => 'operations-center'],
        );

        return back()->with('status', $created
            ? __('Operation queued.')
            : __('An operation of this type is already queued or running for this device.'));
    }
}

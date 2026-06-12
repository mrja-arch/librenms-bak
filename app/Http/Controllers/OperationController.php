<?php

namespace App\Http\Controllers;

use App\Enums\OperationTaskStatus;
use App\Enums\OperationTaskType;
use App\Jobs\RunOperationTask;
use App\Models\Device;
use App\Models\OperationTask;
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
        ]);
    }

    public function store(Request $request, Device $device, string $operation): RedirectResponse
    {
        Gate::authorize('create', Device::class);
        $validated = validator(
            ['operation' => $operation],
            ['operation' => [Rule::in(['discover', 'poll', 'ping'])]]
        )->validate();

        $task = OperationTask::create([
            'type' => OperationTaskType::from($validated['operation']),
            'status' => OperationTaskStatus::Queued,
            'device_id' => $device->device_id,
            'requested_by' => $request->user()->user_id,
        ]);
        RunOperationTask::dispatch($task->id);

        return back()->with('status', __('Operation queued.'));
    }
}

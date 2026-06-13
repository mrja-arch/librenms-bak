<?php

namespace App\Http\Controllers;

use App\Jobs\RunDiagnosticBundle;
use App\Models\Device;
use App\Models\DiagnosticBundle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DiagnosticBundleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'device_id' => ['nullable', 'integer', 'exists:devices,device_id'],
        ]);
        $bundle = DiagnosticBundle::create([
            'device_id' => $validated['device_id'] ?? null,
            'requested_by' => $request->user()->user_id,
            'status' => 'queued',
            'expires_at' => now()->addDays(3),
        ]);
        RunDiagnosticBundle::dispatch($bundle->id);

        return back()->with('status', __('Diagnostic collection queued.'));
    }

    public function download(DiagnosticBundle $bundle): BinaryFileResponse
    {
        abort_unless($bundle->status === 'ready' && $bundle->path && is_file($bundle->path), 404);

        return response()->download($bundle->path, $bundle->filename);
    }
}

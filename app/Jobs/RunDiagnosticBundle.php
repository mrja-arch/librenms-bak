<?php

namespace App\Jobs;

use App\Models\DiagnosticBundle;
use App\Services\DiagnosticBundleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDiagnosticBundle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(public int $bundleId)
    {
        $this->onQueue('operations');
    }

    public function handle(DiagnosticBundleService $service): void
    {
        $service->build(DiagnosticBundle::findOrFail($this->bundleId));
    }
}

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use LibreNMS\Interfaces\SnmptrapHandler;
use LibreNMS\Snmptrap\Handlers\Fallback;
use LibreNMS\Snmptrap\Handlers\HuaweiGenericTrap;

class SnmptrapProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(SnmptrapHandler::class, function ($app, $options) {
            $oid = trim((string) reset($options));
            $handler = config('snmptraps.trap_handlers')[$oid] ?? null;

            if ($handler === null && preg_match('/^(?:HUAWEI|HWMUSA|ISM|OPTIX|NQA)[A-Z0-9-]*::/i', (string) $oid)) {
                $handler = HuaweiGenericTrap::class;
            }

            return $app->make($handler ?? Fallback::class);
        });
    }
}

<?php

namespace LibreNMS\Snmptrap\Handlers;

use App\Models\Device;
use App\Services\TopologyRefreshScheduler;
use LibreNMS\Enum\Severity;
use LibreNMS\Interfaces\SnmptrapHandler;
use LibreNMS\Snmptrap\Trap;

class LldpTopologyChanged implements SnmptrapHandler
{
    public function __construct(private readonly TopologyRefreshScheduler $scheduler)
    {
    }

    public function handle(Device $device, Trap $trap): void
    {
        $this->scheduler->schedule($device, $trap->getTrapOid());
        $trap->log($trap->toString(true), Severity::Info);
    }
}

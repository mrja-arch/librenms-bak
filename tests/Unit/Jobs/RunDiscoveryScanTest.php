<?php

namespace LibreNMS\Tests\Unit\Jobs;

use App\Facades\LibrenmsConfig;
use App\Jobs\RunDiscoveryScan;
use LibreNMS\Tests\TestCase;
use ReflectionMethod;
use RuntimeException;

final class RunDiscoveryScanTest extends TestCase
{
    public function testExpandsUsableIpv4Hosts(): void
    {
        LibrenmsConfig::set('nets', ['10.0.0.0/24']);
        LibrenmsConfig::set('autodiscovery.nets-exclude', []);

        $hosts = $this->expand(['10.0.0.0/30']);

        $this->assertSame(['10.0.0.1', '10.0.0.2'], $hosts);
    }

    public function testRejectsNetworkOutsideConfiguredNets(): void
    {
        LibrenmsConfig::set('nets', ['10.0.0.0/24']);
        LibrenmsConfig::set('autodiscovery.nets-exclude', []);

        $this->expectException(RuntimeException::class);
        $this->expand(['10.0.1.0/30']);
    }

    public function testRejectsNetworkLargerThanMaximumScanSize(): void
    {
        LibrenmsConfig::set('nets', ['10.0.0.0/8']);
        LibrenmsConfig::set('autodiscovery.nets-exclude', []);

        $this->expectException(RuntimeException::class);
        $this->expand(['10.0.0.0/19']);
    }

    private function expand(array $networks): array
    {
        $method = new ReflectionMethod(RunDiscoveryScan::class, 'expandNetworks');

        return $method->invoke(new RunDiscoveryScan(1), $networks);
    }
}

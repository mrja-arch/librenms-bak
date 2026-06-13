<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class DeviceOperationLock
{
    private const LOCK_SECONDS = 1800;

    private const WAIT_SECONDS = 900;

    public function run(int $deviceId, callable $callback): mixed
    {
        try {
            return Cache::lock($this->key($deviceId), self::LOCK_SECONDS)
                ->block(self::WAIT_SECONDS, $callback);
        } catch (LockTimeoutException) {
            throw new RuntimeException(__('Another discovery, polling, or ping operation is already running for this device.'));
        }
    }

    private function key(int $deviceId): string
    {
        return "device-operation:$deviceId";
    }
}

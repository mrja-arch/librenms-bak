<?php

namespace App\Services;

use App\Facades\LibrenmsConfig;
use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BuiltInDashboardService
{
    public const KEY = 'campus-operations';
    public const VERSION = 1;

    public function ensure(User $owner): Dashboard
    {
        return DB::transaction(function () use ($owner): Dashboard {
            $dashboard = Dashboard::query()->firstOrNew(['built_in_key' => self::KEY]);
            $needsSync = ! $dashboard->exists || (int) $dashboard->built_in_version < self::VERSION;

            if (! $dashboard->exists) {
                $dashboard->user_id = $owner->user_id;
            }

            $dashboard->fill([
                'dashboard_name' => '园区运维总览',
                'access' => 1,
                'built_in_key' => self::KEY,
                'built_in_version' => self::VERSION,
            ])->save();

            if ($needsSync) {
                $dashboard->widgets()->delete();
                $dashboard->widgets()->createMany($this->widgets($dashboard, $owner));
            }

            if ((int) LibrenmsConfig::get('webui.default_dashboard_id') !== $dashboard->dashboard_id) {
                LibrenmsConfig::persist('webui.default_dashboard_id', $dashboard->dashboard_id);
            }

            return $dashboard;
        });
    }

    private function widgets(Dashboard $dashboard, User $owner): array
    {
        $base = [
            'user_id' => $owner->user_id,
            'dashboard_id' => $dashboard->dashboard_id,
            'refresh' => 60,
            'title' => '',
        ];

        return [
            $base + ['widget' => 'operations-shortcuts', 'col' => 1, 'row' => 1, 'size_x' => 12, 'size_y' => 2, 'settings' => ['refresh' => 60]],
            $base + ['widget' => 'device-summary-horiz', 'col' => 1, 'row' => 3, 'size_x' => 6, 'size_y' => 3, 'settings' => ['refresh' => 60]],
            $base + ['widget' => 'availability-map', 'col' => 7, 'row' => 3, 'size_x' => 6, 'size_y' => 3, 'settings' => ['refresh' => 60, 'show_totals' => 1]],
            $base + ['widget' => 'alerts', 'col' => 1, 'row' => 6, 'size_x' => 6, 'size_y' => 4, 'settings' => ['refresh' => 60, 'hidenavigation' => 1]],
            $base + ['widget' => 'eventlog', 'col' => 7, 'row' => 6, 'size_x' => 6, 'size_y' => 4, 'settings' => ['refresh' => 60, 'hidenavigation' => 1]],
            $base + ['widget' => 'top-interfaces', 'col' => 1, 'row' => 10, 'size_x' => 6, 'size_y' => 4, 'settings' => ['refresh' => 60, 'interface_count' => 5, 'time_interval' => 15]],
            $base + ['widget' => 'top-devices', 'col' => 7, 'row' => 10, 'size_x' => 6, 'size_y' => 4, 'settings' => ['refresh' => 60, 'top_query' => 'poller', 'sort_order' => 'desc', 'device_count' => 5, 'time_interval' => 15]],
        ];
    }
}

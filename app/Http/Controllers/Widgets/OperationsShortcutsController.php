<?php

namespace App\Http\Controllers\Widgets;

use App\Facades\LibrenmsConfig;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationsShortcutsController extends WidgetController
{
    protected string $name = 'operations-shortcuts';
    protected $defaults = [
        'refresh' => 60,
    ];

    public function getTitle(): string
    {
        return __('operations.dashboard_shortcuts');
    }

    public function getView(Request $request): View
    {
        $user = $request->user();
        $links = [
            ['title' => __('Devices'), 'icon' => 'server', 'url' => url('devices')],
            ['title' => __('Ports'), 'icon' => 'link', 'url' => route('ports')],
            ['title' => __('Network Map'), 'icon' => 'sitemap', 'url' => url('map')],
            ['title' => __('Auto Discovery'), 'icon' => 'binoculars', 'url' => route('devices.discovery')],
            ['title' => __('Operations Center'), 'icon' => 'tasks', 'url' => route('operations.index')],
            ['title' => __('Alerts'), 'icon' => 'bell', 'url' => url('alerts')],
            ['title' => __('Eventlog'), 'icon' => 'bookmark', 'url' => url('eventlog')],
            ['title' => __('Poller'), 'icon' => 'table', 'url' => route('poller.index')],
            ['title' => __('help.title'), 'icon' => 'book', 'url' => route('help.index')],
        ];

        if (LibrenmsConfig::get('oxidized.enabled')) {
            $links[] = ['title' => __('Oxidized'), 'icon' => 'history', 'url' => url('oxidized')];
        }

        if ($user->can('admin')) {
            array_splice($links, 3, 0, [
                ['title' => __('Discovered Links'), 'icon' => 'project-diagram', 'url' => route('topology-links.index')],
            ]);
            $links[] = ['title' => __('Global Settings'), 'icon' => 'cogs', 'url' => route('settings')];
            $links[] = ['title' => __('Validate Config'), 'icon' => 'check-circle', 'url' => route('validate')];
            $links[] = ['title' => __('Diagnostic Collection'), 'icon' => 'archive', 'url' => route('operations.index') . '#diagnostics'];
        }

        return view('widgets.operations-shortcuts', [
            'links' => $links,
        ]);
    }
}

@extends('layouts.librenmsv1')

@section('title', __('Discovered Links'))

@section('content')
<div class="container-fluid">
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

    <div class="row">
        <div class="col-md-8">
            <form method="get" class="form-inline">
                <select name="status" class="form-control">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                    <option value="stale" @selected(request('status') === 'stale')>{{ __('Stale') }}</option>
                </select>
                <select name="protocol" class="form-control">
                    <option value="">{{ __('All protocols') }}</option>
                    @foreach($protocols as $protocol)
                        <option value="{{ $protocol }}" @selected(request('protocol') === $protocol)>{{ strtoupper($protocol) }}</option>
                    @endforeach
                </select>
                <button class="btn btn-default"><i class="fa fa-filter"></i> {{ __('Filter') }}</button>
            </form>
        </div>
        <div class="col-md-4 text-right">
            <form method="post" action="{{ route('topology-links.discover') }}" class="form-inline">
                @csrf
                <select name="device_id" class="form-control" required>
                    <option value="">{{ __('Select a device') }}</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->device_id }}">{{ $device->displayName() }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary"><i class="fa fa-refresh"></i> {{ __('Discover Links') }}</button>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs tw:mt-4">
        <li class="active"><a href="#links" data-toggle="tab">{{ __('Discovered Links') }}</a></li>
        <li><a href="#ignored" data-toggle="tab">{{ __('Ignored Links') }} ({{ $ignores->count() }})</a></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane active" id="links">
            <table class="table table-hover table-condensed">
                <thead><tr>
                    <th>{{ __('Status') }}</th><th>{{ __('Protocol') }}</th><th>{{ __('Local Device') }}</th>
                    <th>{{ __('Local Port') }}</th><th>{{ __('Remote Device') }}</th><th>{{ __('Remote Port') }}</th>
                    <th>{{ __('Last Seen') }}</th><th>{{ __('Misses') }}</th><th>{{ __('Actions') }}</th>
                </tr></thead>
                <tbody>
                @forelse($links as $link)
                    <tr class="{{ $link->status === 'stale' ? 'warning' : '' }}">
                        <td>{{ __($link->status === 'active' ? 'Active' : 'Stale') }}</td>
                        <td>{{ strtoupper($link->protocol) }}</td>
                        <td>{{ $link->device?->displayName() ?? $link->local_device_id }}</td>
                        <td>{{ $link->port?->ifName ?? $link->local_port_id }}</td>
                        <td>{{ $link->remoteDevice?->displayName() ?? $link->remote_hostname }}</td>
                        <td>{{ $link->remotePort?->ifName ?? $link->remote_port }}</td>
                        <td>{{ $link->last_seen_at ?: '-' }}</td>
                        <td>{{ $link->missed_discoveries }}</td>
                        <td class="text-nowrap">
                            <form method="post" action="{{ route('topology-links.ignore', $link) }}" class="tw:inline">
                                @csrf
                                <button class="btn btn-xs btn-warning" title="{{ __('Ignore and suppress rediscovery') }}"><i class="fa fa-ban"></i> {{ __('Ignore') }}</button>
                            </form>
                            <form method="post" action="{{ route('topology-links.destroy', $link) }}" class="tw:inline">
                                @csrf @method('delete')
                                <button class="btn btn-xs btn-danger" title="{{ __('Delete; discovery may recreate it') }}"><i class="fa fa-trash"></i> {{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center">{{ __('No links found.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $links->links() }}
        </div>
        <div class="tab-pane" id="ignored">
            <table class="table table-hover table-condensed">
                <thead><tr><th>{{ __('Protocol') }}</th><th>{{ __('Local Device') }}</th><th>{{ __('Local Port') }}</th><th>{{ __('Remote Device') }}</th><th>{{ __('Remote Port') }}</th><th>{{ __('Created At') }}</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                @forelse($ignores as $ignore)
                    <tr>
                        <td>{{ strtoupper($ignore->protocol) }}</td>
                        <td>{{ $ignore->device?->displayName() ?? $ignore->local_device_id }}</td>
                        <td>{{ $ignore->port?->ifName ?? '*' }}</td>
                        <td>{{ $ignore->remote_hostname }}</td>
                        <td>{{ $ignore->remote_port }}</td>
                        <td>{{ $ignore->created_at }}</td>
                        <td>
                            <form method="post" action="{{ route('topology-links.restore', $ignore) }}">
                                @csrf @method('delete')
                                <button class="btn btn-xs btn-success"><i class="fa fa-undo"></i> {{ __('Restore') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center">{{ __('No ignored links.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

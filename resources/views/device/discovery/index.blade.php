@extends('layouts.librenmsv1')

@section('title', __('Automatic Discovery'))

@section('content')
<div class="container-fluid">
    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <ul class="nav nav-tabs" role="tablist">
        <li class="active"><a href="#candidates" data-toggle="tab">{{ __('Candidates') }}</a></li>
        <li><a href="#scans" data-toggle="tab">{{ __('Scan Records') }}</a></li>
        <li><a href="#operations" data-toggle="tab">{{ __('Operation History') }}</a></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane active" id="candidates">
            <div class="row tw:mt-4">
                <div class="col-md-8">
                    <form action="{{ route('devices.discovery.scans') }}" method="post" class="form-inline">
                        @csrf
                        <div class="form-group">
                            <label for="networks">{{ __('Trusted networks') }}</label>
                            <input id="networks" name="networks" class="form-control tw:min-w-80"
                                   value="{{ implode(' ', $configuredNetworks) }}"
                                   placeholder="172.31.255.0/24" required>
                        </div>
                        <button class="btn btn-primary" type="submit">
                            <i class="fa fa-search" aria-hidden="true"></i> {{ __('Start Scan') }}
                        </button>
                    </form>
                    <p class="help-block">
                        {{ __('Only networks in the global nets setting may be scanned. Maximum 4096 addresses per scan.') }}
                        {{ __('The scan uses the global SNMP credentials and only keeps SNMP-responsive devices.') }}
                    </p>
                </div>
                <div class="col-md-4 text-right">
                    <a class="btn btn-default" href="{{ route('operations.index') }}">
                        <i class="fa fa-tasks" aria-hidden="true"></i> {{ __('Operation Center') }}
                    </a>
                </div>
            </div>

            <form action="{{ route('devices.discovery.candidates.bulk-approve') }}" method="post">
                @csrf
                <div class="table-responsive">
                    <table class="table table-hover table-condensed">
                        <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all" aria-label="{{ __('Select all') }}"></th>
                            <th>{{ __('Status') }}</th>
                            <th>IP / Hostname</th>
                            <th>sysName / OS</th>
                            <th>Ping / SNMP</th>
                            <th>{{ __('Source') }}</th>
                            <th>{{ __('Last Seen') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($candidates as $candidate)
                            <tr>
                                <td><input type="checkbox" name="candidates[]" value="{{ $candidate->id }}" class="candidate-check"></td>
                                <td><span class="label label-default">{{ __($candidate->status->value) }}</span></td>
                                <td>
                                    <strong>{{ $candidate->ip }}</strong><br>
                                    <small>{{ $candidate->hostname }}</small>
                                </td>
                                <td>{{ $candidate->sys_name ?: '-' }}<br><small>{{ $candidate->os ?: '-' }}</small></td>
                                <td>
                                    <span class="label label-{{ $candidate->ping_status ? 'success' : 'default' }}">Ping</span>
                                    <span class="label label-{{ $candidate->snmp_status ? 'success' : 'default' }}">SNMP</span>
                                </td>
                                <td>{{ implode(', ', $candidate->source_methods ?? []) }}</td>
                                <td>{{ $candidate->last_seen_at?->diffForHumans() }}</td>
                                <td class="text-nowrap">
                                    @if($candidate->status->value !== 'managed')
                                        <button type="button" class="btn btn-primary btn-xs" data-toggle="modal"
                                                data-target="#approve-{{ $candidate->id }}">
                                            <i class="fa fa-check"></i> {{ __('Approve') }}
                                        </button>
                                    @endif
                                    @if($candidate->status->value === 'ignored')
                                        <button class="btn btn-default btn-xs" form="restore-{{ $candidate->id }}">
                                            <i class="fa fa-undo"></i> {{ __('Restore') }}
                                        </button>
                                    @elseif($candidate->status->value !== 'managed')
                                        <button class="btn btn-default btn-xs" form="ignore-{{ $candidate->id }}">
                                            <i class="fa fa-ban"></i> {{ __('Ignore') }}
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">{{ __('No discovery candidates.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="form-inline">
                    <select name="poller_group" class="form-control">
                        @foreach($pollerGroups as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <select name="port_association_mode" class="form-control">
                        @foreach(\LibreNMS\Enum\PortAssociationMode::getModes() as $mode)
                            <option value="{{ $mode }}">{{ $mode }}</option>
                        @endforeach
                    </select>
                    <label class="checkbox-inline"><input type="checkbox" name="ping_fallback" value="1"> {{ __('Ping fallback') }}</label>
                    <button class="btn btn-primary" type="submit">{{ __('Bulk Approve') }}</button>
                </div>
            </form>
            {{ $candidates->links() }}
        </div>

        <div class="tab-pane" id="scans">
            @include('device.discovery.scan-table')
        </div>
        <div class="tab-pane" id="operations">
            @include('operations.task-table')
        </div>
    </div>
</div>

@foreach($candidates as $candidate)
    <form id="ignore-{{ $candidate->id }}" action="{{ route('devices.discovery.candidates.ignore', $candidate) }}" method="post">@csrf @method('patch')</form>
    <form id="restore-{{ $candidate->id }}" action="{{ route('devices.discovery.candidates.restore', $candidate) }}" method="post">@csrf @method('patch')</form>
    <div class="modal fade" id="approve-{{ $candidate->id }}" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form action="{{ route('devices.discovery.candidates.approve', $candidate) }}" method="post" class="modal-content">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">{{ __('Approve Device') }}: {{ $candidate->ip }}</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ __('Hostname') }}</label>
                        <input name="hostname" class="form-control" value="{{ $candidate->hostname ?: $candidate->ip }}">
                    </div>
                    <div class="form-group">
                        <label>{{ __('Poller Group') }}</label>
                        <select name="poller_group" class="form-control">
                            @foreach($pollerGroups as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ __('Port Association Mode') }}</label>
                        <select name="port_association_mode" class="form-control">
                            @foreach(\LibreNMS\Enum\PortAssociationMode::getModes() as $mode)<option value="{{ $mode }}">{{ $mode }}</option>@endforeach
                        </select>
                    </div>
                    <label><input type="checkbox" name="ping_fallback" value="1"> {{ __('Allow Ping fallback when SNMP is unavailable') }}</label>
                    <p class="help-block">{{ __('SNMP credentials are read again from global settings during approval and are never stored in the candidate record.') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                    <button class="btn btn-primary" type="submit">{{ __('Approve') }}</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection

@section('scripts')
<script>
    document.getElementById('select-all')?.addEventListener('change', function () {
        document.querySelectorAll('.candidate-check').forEach((item) => item.checked = this.checked);
    });
</script>
@endsection

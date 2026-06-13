@extends('layouts.librenmsv1')

@section('title', __('Operation Center'))

@section('content')
<div class="container-fluid">
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="row">
        <div class="col-md-8">
            <form id="operation-form" method="post" class="form-inline">
                @csrf
                <div class="form-group">
                    <label for="device">{{ __('Device') }}</label>
                    <select id="device" class="form-control" required>
                        <option value="">{{ __('Select a device') }}</option>
                        @foreach($devices as $device)
                            <option value="{{ $device->device_id }}">{{ $device->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary operation-button" data-operation="discover" type="button">
                    <i class="fa fa-search"></i> {{ __('Discover Now') }}
                </button>
                <button class="btn btn-default operation-button" data-operation="poll" type="button">
                    <i class="fa fa-refresh"></i> {{ __('Poll Now') }}
                </button>
                <button class="btn btn-default operation-button" data-operation="ping" type="button">
                    <i class="fa fa-signal"></i> Ping
                </button>
            </form>
            <p class="help-block">{{ __('Only approved operations are available here. Administrative shell commands remain CLI-only.') }}</p>
        </div>
        <div class="col-md-4 text-right">
            <a href="{{ route('devices.discovery') }}" class="btn btn-default">
                <i class="fa fa-binoculars"></i> {{ __('Automatic Discovery') }}
            </a>
        </div>
    </div>

    @can('admin')
    <div class="panel panel-default tw:mt-4">
        <div class="panel-heading"><strong>{{ __('Diagnostic Collection') }}</strong></div>
        <div class="panel-body">
            <form method="post" action="{{ route('diagnostics.store') }}" class="form-inline">
                @csrf
                <label for="diagnostic-device">{{ __('Target') }}</label>
                <select id="diagnostic-device" name="device_id" class="form-control">
                    <option value="">{{ __('System only') }}</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->device_id }}">{{ $device->displayName() }}</option>
                    @endforeach
                </select>
                <button class="btn btn-warning"><i class="fa fa-medkit"></i> {{ __('Collect Diagnostic Bundle') }}</button>
            </form>
            <p class="help-block">{{ __('Device collection uses read-only SSH commands. Archives are redacted and retained for 3 days.') }}</p>

            <table class="table table-condensed table-hover">
                <thead><tr><th>ID</th><th>{{ __('Target') }}</th><th>{{ __('Status') }}</th><th>{{ __('Requested By') }}</th><th>{{ __('Completed') }}</th><th>SHA-256</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                @forelse($diagnosticBundles as $bundle)
                    <tr>
                        <td>{{ $bundle->id }}</td>
                        <td>{{ $bundle->device?->displayName() ?: __('System only') }}</td>
                        <td>{{ __($bundle->status) }}</td>
                        <td>{{ $bundle->requester?->username ?: '-' }}</td>
                        <td>{{ $bundle->completed_at ?: '-' }}</td>
                        <td><code>{{ $bundle->sha256 ? substr($bundle->sha256, 0, 16) . '...' : '-' }}</code></td>
                        <td>
                            @if($bundle->status === 'ready')
                                <a class="btn btn-xs btn-success" href="{{ route('diagnostics.download', $bundle) }}"><i class="fa fa-download"></i> {{ __('Download') }}</a>
                            @elseif($bundle->error)
                                <span class="text-danger">{{ $bundle->error }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center">{{ __('No diagnostic bundles.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endcan

    @include('operations.task-table')
    {{ $tasks->links() }}
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.operation-button').forEach((button) => {
        button.addEventListener('click', () => {
            const device = document.getElementById('device').value;
            if (!device) {
                return;
            }
            const form = document.getElementById('operation-form');
            form.action = '{{ url('operations/devices') }}/' + device + '/' + button.dataset.operation;
            form.submit();
        });
    });
</script>
@endsection

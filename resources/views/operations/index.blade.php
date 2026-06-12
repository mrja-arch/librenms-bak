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

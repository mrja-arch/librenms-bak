<div class="table-responsive tw:mt-4">
    <table class="table table-hover table-condensed">
        <thead><tr>
            <th>ID</th><th>{{ __('Operation') }}</th><th>{{ __('Device') }}</th>
            <th>{{ __('Status') }}</th><th>{{ __('Started By') }}</th>
            <th>{{ __('Completed') }}</th><th>{{ __('Duration') }}</th><th>{{ __('Result') }}</th>
        </tr></thead>
        <tbody>
        @forelse($tasks as $task)
            <tr>
                <td>{{ $task->id }}</td>
                <td>{{ __($task->type->value) }}</td>
                <td>{{ $task->device?->displayName() ?: data_get($task->input, 'device_name', data_get($task->input, 'hostname', '-')) }}</td>
                <td>{{ __($task->status->value) }}</td>
                <td>{{ $task->requester?->username ?: '-' }}</td>
                <td>{{ $task->completed_at ?: '-' }}</td>
                <td>{{ $task->started_at && $task->completed_at ? $task->started_at->diffForHumans($task->completed_at, true) : '-' }}</td>
                <td class="{{ $task->error ? 'text-danger' : '' }}">{{ $task->error ?: $task->output }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted">{{ __('No operation tasks.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

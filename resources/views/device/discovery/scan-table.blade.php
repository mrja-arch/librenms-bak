<div class="table-responsive tw:mt-4">
    <table class="table table-hover table-condensed">
        <thead><tr>
            <th>ID</th><th>{{ __('Networks') }}</th><th>{{ __('Status') }}</th>
            <th>{{ __('Progress') }}</th><th>{{ __('Candidates') }}</th>
            <th>{{ __('Started By') }}</th><th>{{ __('Created') }}</th><th>{{ __('Error') }}</th>
        </tr></thead>
        <tbody>
        @forelse($scans as $scan)
            <tr>
                <td>{{ $scan->id }}</td>
                <td>{{ implode(', ', $scan->networks) }}</td>
                <td>{{ __($scan->status->value) }}</td>
                <td>{{ $scan->processed_hosts }} / {{ $scan->total_hosts }}</td>
                <td>{{ $scan->candidates_found }}</td>
                <td>{{ $scan->requester?->username ?: '-' }}</td>
                <td>{{ $scan->created_at }}</td>
                <td class="text-danger">{{ $scan->error }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted">{{ __('No scan records.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

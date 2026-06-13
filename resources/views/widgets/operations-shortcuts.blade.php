<div class="operations-shortcuts tw:flex tw:flex-wrap tw:gap-2">
    @forelse($links as $link)
        <a class="btn btn-default" href="{{ $link['url'] }}" title="{{ $link['title'] }}">
            <i class="fa fa-{{ $link['icon'] }} fa-fw" aria-hidden="true"></i>
            <span>{{ $link['title'] }}</span>
        </a>
    @empty
        <div class="alert alert-info">{{ __('operations.no_shortcuts') }}</div>
    @endforelse
</div>

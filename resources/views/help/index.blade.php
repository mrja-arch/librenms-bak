@extends('layouts.librenmsv1')

@section('title', __('help.title'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2><i class="fa fa-book" aria-hidden="true"></i> {{ __('help.title') }}</h2>
            <p class="text-muted">{{ __('help.search_description') }}</p>

            <form method="get" action="{{ route('help.index') }}" class="form-inline tw:mb-4">
                <div class="input-group">
                    <input type="search" class="form-control" name="q" value="{{ $query }}" placeholder="{{ __('help.search') }}" aria-label="{{ __('help.search') }}">
                    <span class="input-group-btn">
                        <button class="btn btn-primary" type="submit"><i class="fa fa-search" aria-hidden="true"></i> {{ __('Search') }}</button>
                    </span>
                </div>
                @if($query !== '')
                    <a class="btn btn-default" href="{{ route('help.index') }}">{{ __('Clear') }}</a>
                @endif
            </form>

            <div class="row">
                @forelse($topics as $topic)
                    <div class="col-md-4 col-sm-6">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <strong><a href="{{ $topic['public'] ? route('help.public') : route('help.topic', $topic['slug']) }}">{{ $topic['title'] }}</a></strong>
                            </div>
                            <div class="panel-body">{{ $topic['summary'] }}</div>
                        </div>
                    </div>
                @empty
                    <div class="col-md-12">
                        <div class="alert alert-info">{{ __('help.no_results') }}</div>
                    </div>
                @endforelse
            </div>

            <hr>
            <p class="text-muted">
                {{ __('help.external_reference') }}
                <a href="https://docs.librenms.org/" target="_blank" rel="noopener noreferrer">{{ __('help.official_docs') }}</a>
            </p>
        </div>
    </div>
</div>
@endsection

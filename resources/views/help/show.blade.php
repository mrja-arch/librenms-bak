@extends('layouts.librenmsv1')

@section('title', $topic['title'])

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-10 col-md-offset-1">
            <p><a href="{{ route('help.index') }}"><i class="fa fa-arrow-left" aria-hidden="true"></i> {{ __('help.title') }}</a></p>
            <article class="local-help-content">
                {!! $html !!}
            </article>
            <hr>
            <p class="text-muted">
                {{ __('help.external_reference') }}
                <a href="https://docs.librenms.org/" target="_blank" rel="noopener noreferrer">{{ __('help.official_docs') }}</a>
            </p>
        </div>
    </div>
</div>
@endsection

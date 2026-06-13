<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $topic['title'] }}</title>
    <link href="{{ asset('css/bootstrap.min.css') }}" rel="stylesheet">
</head>
<body>
<main class="container" style="max-width: 960px; padding-top: 30px; padding-bottom: 50px;">
    <article>{!! $html !!}</article>
    <hr>
    @auth
        <a class="btn btn-primary" href="{{ route('help.index') }}">进入本地帮助中心</a>
    @else
        <a class="btn btn-primary" href="{{ route('login') }}">返回登录</a>
    @endauth
</main>
</body>
</html>

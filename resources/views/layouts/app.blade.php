<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'Terra Echo')</title>
    <link rel="stylesheet" href="{{ asset('css/home.css') }}">
    @stack('styles')
</head>
<body>
    @include('partials.navbar')

    @yield('content')
    @stack('scripts')
</body>
</html>
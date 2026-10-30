@php
    $businessName = getSettings('business_name');
    $businessName = $businessName === 'NA' || blank($businessName) ? config('app.name') : $businessName;
    $logoUrl = groupLogoUrl();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $businessName }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v={{ filemtime(public_path('favicon-32x32.png')) }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v={{ filemtime(public_path('favicon-16x16.png')) }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="login-page bg-body-secondary">
    <div class="login-box">
        <div class="login-logo">
            <a href="{{ url('/') }}">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $businessName }}" class="d-block mx-auto mb-2" style="width: 200px; height: auto;">
                @endif
                <b>{{ $businessName }}</b>
            </a>
        </div>

        @yield('content')
    </div>
</body>

</html>

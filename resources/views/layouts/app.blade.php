@php
    $businessName = getSettings('business_name');
    $businessName = $businessName === 'NA' || blank($businessName) ? config('app.name') : $businessName;
    $logo = getSettings('logo');
    $logoUrl = $logo !== 'NA' && filled($logo) && file_exists(public_path('logo/'.$logo)) ? asset('logo/'.$logo) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $businessName }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="login-page bg-body-secondary">
    <div class="login-box">
        <div class="login-logo">
            <a href="{{ url('/') }}">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $businessName }}" class="d-block mx-auto mb-2">
                @endif
                <b>{{ $businessName }}</b>
            </a>
        </div>

        @yield('content')
    </div>
</body>

</html>

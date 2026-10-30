<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in · {{ groupName() }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v={{ filemtime(public_path('favicon-32x32.png')) }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v={{ filemtime(public_path('favicon-16x16.png')) }}">
    @include('partials.pwa_head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
</head>

<body>
    <main class="login">
        <section class="login-brand">
            <img class="login-brand__logo" src="{{ groupLogoWhiteUrl() }}" alt="{{ groupName() }}" width="240" height="121">

            <div class="login-brand__middle">
                <span class="login-brand__bar" aria-hidden="true"></span>
                <h1 class="login-brand__heading">{{ groupName() }}</h1>
                @if (filled(getSettings('slogan')) && getSettings('slogan') !== 'NA')
                    <p class="login-brand__tagline">{{ getSettings('slogan') }}</p>
                @endif
            </div> 

            <p class="login-brand__address">Dar es Salaam</p>
        </section>

        <section class="login-panel">
            <div class="login-form-wrap">
                <header class="login-header">
                    <h2 class="login-title">Sign in</h2>
                    <p class="login-subtitle">Use your username or email to continue.</p>
                </header>

                @if (session('status'))
                    <div class="login-alert" role="alert">{{ session('status') }}</div>
                @endif

                @if (session('error'))
                    <div class="login-alert" role="alert">{{ session('error') }}</div>
                @endif

                <form class="login-form" method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="login-field">
                        <label class="login-label" for="username">Username or email</label>
                        <div class="login-input-wrap">
                            <svg class="login-input-icon" aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="8" r="4"></circle>
                                <path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"></path>
                            </svg>
                            <input id="username" type="text" name="username" value="{{ old('username') }}"
                                class="login-input @error('username') is-invalid @enderror"
                                autocomplete="username" required autofocus
                                @error('username') aria-invalid="true" aria-describedby="username-error" @enderror>
                        </div>
                        @error('username')
                            <div id="username-error" class="login-alert login-alert--field" role="alert">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="login-field">
                        <label class="login-label" for="password">Password</label>
                        <div class="login-input-wrap">
                            <svg class="login-input-icon" aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="4" y="10" width="16" height="11" rx="2"></rect>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                            </svg>
                            <input id="password" type="password" name="password"
                                class="login-input login-input--password @error('password') is-invalid @enderror"
                                autocomplete="current-password" required
                                @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                            <button type="button" class="login-eye" id="toggle-password" aria-label="Show password" aria-controls="password">
                                <svg class="login-eye__show" aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="login-eye__hide" aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 3l18 18"></path>
                                    <path d="M10.6 5.1A10.6 10.6 0 0 1 12 5c6.4 0 10 7 10 7a17.7 17.7 0 0 1-3.2 4.1M6.6 6.6A17.4 17.4 0 0 0 2 12s3.6 7 10 7a9.9 9.9 0 0 0 5.4-1.6"></path>
                                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <div id="password-error" class="login-alert login-alert--field" role="alert">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="login-row">
                        <label class="login-remember" for="remember">
                            <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                            Remember me
                        </label>

                        @if (Route::has('password.request'))
                            <a class="login-link" href="{{ route('password.request') }}">Forgot password?</a>
                        @endif
                    </div>

                    <button type="submit" class="login-submit">
                        Sign in
                        <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14"></path>
                            <path d="M13 6l6 6-6 6"></path>
                        </svg>
                    </button>
                </form>

                <footer class="login-footer">
                    <p class="login-address-mobile">Dar es Salaam</p>
                    <p class="login-copyright">&copy; {{ date('Y') }} {{ groupName() }}</p>
                </footer>
            </div>
        </section>
    </main>

    <script>
        (function () {
            var toggle = document.getElementById('toggle-password');
            var input = document.getElementById('password');

            toggle.addEventListener('click', function () {
                var isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                toggle.classList.toggle('is-visible', isHidden);
            });
        })();
    </script>

    @include('partials.pwa_register')
</body>

</html>

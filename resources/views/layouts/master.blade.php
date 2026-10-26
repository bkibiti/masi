@php
    $businessName = groupName();
    $logoUrl = groupLogoUrl();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <title>{{ $businessName }}</title>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}">
    @include('partials.pwa_head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/abg-theme.css') }}?v={{ filemtime(public_path('css/abg-theme.css')) }}">

    <link rel="stylesheet" href="{{ asset('assets/plugins/daterangepicker-master/css/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/plugins/intl-tel-input/css/intlTelInput.css') }}">

    @yield('page_css')

    {{-- jQuery and its plugins load as classic scripts: page scripts call them inline while the page parses. --}}
    <script src="{{ asset('vendor/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('vendor/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/moment/js/moment.js') }}"></script>
    <script src="{{ asset('assets/plugins/daterangepicker-master/js/daterangepicker.js') }}"></script>
    <script src="{{ asset('assets/plugins/notification/js/bootstrap-growl.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/inputmask/js/inputmask.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/inputmask/js/jquery.inputmask.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/inputmask/js/autoNumeric.js') }}"></script>
    <script src="{{ asset('assets/plugins/intl-tel-input/js/intlTelInput.js') }}"></script>
    <script>
        $.fn.select2.defaults.set('theme', 'bootstrap-5');

        // Bootstrap 5 modals trap focus, so a Select2 inside a modal must render its dropdown in that modal.
        (function ($) {
            const select2 = $.fn.select2;
            $.fn.select2 = function (options) {
                if (options === undefined || $.isPlainObject(options)) {
                    return this.each(function () {
                        const settings = $.extend({}, options);
                        const $modal = $(this).closest('.modal');
                        if ($modal.length && ! settings.dropdownParent) {
                            settings.dropdownParent = $modal;
                        }
                        select2.call($(this), settings);
                    });
                }
                return select2.apply(this, arguments);
            };
            $.extend($.fn.select2, select2);
        })(jQuery);
    </script>
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">

        <nav class="app-header navbar navbar-expand bg-body">
            <div class="container-fluid">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Toggle sidebar">
                            <i class="bi bi-list"></i>
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-lte-toggle="fullscreen" aria-label="Toggle fullscreen">
                            <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                            <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
                        </a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link" data-bs-toggle="dropdown" href="#" aria-label="Notifications">
                            <i class="bi bi-bell-fill"></i>
                            @if (notifications() > 0)
                                <span class="navbar-badge badge text-bg-warning">{{ notifications() }}</span>
                            @endif
                        </a>
                        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                            <span class="dropdown-item dropdown-header">{{ notifications() }} Notifications</span>
                            @can('View Reminders')
                                @foreach (getReminders() as $reminder)
                                    <div class="dropdown-divider"></div>
                                    <a href="{{ route('reminders.index') }}" class="dropdown-item notification-item">
                                        <i class="bi bi-alarm text-warning me-2"></i> {{ $reminder }}
                                    </a>
                                @endforeach
                            @endcan
                        </div>
                    </li>

                    <li class="nav-item dropdown user-menu">
                        @php
                            $userInitials = collect(explode(' ', Auth::user()->name))
                                ->filter()
                                ->map(fn ($part) => mb_substr($part, 0, 1))
                                ->take(2)
                                ->implode('');
                        @endphp
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                            <span class="avatar-initials">{{ mb_strtoupper($userInitials) }}</span>
                            <span class="d-none d-md-inline">{{ Auth::user()->name }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                            <li class="user-header text-bg-primary">
                                <i class="bi bi-person-circle display-4"></i>
                                <p>
                                    {{ Auth::user()->name }}
                                    <small>{{ Auth::user()->position }}</small>
                                </p>
                            </li>
                            <li class="user-body d-none" id="install-app-item">
                                <button type="button" class="btn btn-primary w-100" id="install-app-button">
                                    <i class="bi bi-download"></i> Install app
                                </button>
                            </li>
                            <li class="user-footer">
                                <a href="{{ route('change-pass-form') }}" class="btn btn-outline-secondary">
                                    <i class="bi bi-key"></i> Change Password
                                </a>
                                <a href="{{ route('logout') }}" class="btn btn-outline-secondary float-end"
                                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                            </li>
                        </ul>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </li>
                </ul>
            </div>
        </nav>

        <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
            <div class="sidebar-brand">
                <a href="{{ route('home') }}" class="brand-link">
                    @if ($logoUrl)
                        {{-- <img src="{{ $logoUrl }}" alt="{{ $businessName }}" class="brand-image opacity-75 shadow"> --}}
                    @else
                        <i class="bi bi-people-fill brand-image fs-4 text-warning"></i>
                    @endif
                    <span class="brand-text fw-light">{{ $businessName }}</span>
                </a>
            </div>
            <div class="sidebar-wrapper">
                <nav class="mt-2">
                    <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation"
                        aria-label="Main navigation" data-accordion="false">
                        @include('layouts.menu')
                    </ul>
                </nav>
            </div>
        </aside>

        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-6">
                            <h3 class="mb-0">@yield('content-title')</h3>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                @yield('content-sub-title')
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div class="app-content">
                <div class="container-fluid">
                    <div class="row">
                        @yield('content')
                    </div>
                </div>
            </div>
        </main>

        <footer class="app-footer">
            <strong>&copy; {{ date('Y') }} {{ $businessName }}.</strong>
        </footer>
    </div>

    <script>
        // Tables with id="fixed-header" were initialised by the old theme's demo script.
        $(function () {
            if (! $.fn.dataTable.isDataTable('#fixed-header')) {
                $('#fixed-header').DataTable();
            }
        });
    </script>

    @stack('page_scripts')
    @stack('bk_script')

    @include('partials.pwa_register')
    <script>
        // "Install app" in the user menu: only shown when the browser offers installation
        (function () {
            const item = document.getElementById('install-app-item');
            const button = document.getElementById('install-app-button');
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            let installPrompt = null;

            window.addEventListener('beforeinstallprompt', function (event) {
                if (isStandalone) {
                    return;
                }
                event.preventDefault();
                installPrompt = event;
                item.classList.remove('d-none');
            });

            button.addEventListener('click', async function () {
                if (! installPrompt) {
                    return;
                }
                installPrompt.prompt();
                await installPrompt.userChoice;
                installPrompt = null;
                item.classList.add('d-none');
            });

            window.addEventListener('appinstalled', function () {
                installPrompt = null;
                item.classList.add('d-none');
            });
        })();
    </script>
</body>

</html>

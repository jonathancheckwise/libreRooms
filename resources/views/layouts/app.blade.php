<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'La Pépite — Réservation de salles')</title>
    <link rel="icon" type="image/svg+xml" href="/pepite/favicon.svg">
    @vite('resources/css/app.css')
    {{-- Thème La Pépite (surcharge, chargé après le CSS de base) --}}
    <link rel="stylesheet" href="/pepite/theme.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @yield('page-script')
    @vite(['resources/js/app.js'])

</head>
<body>
<header class="header">
    <nav class="nav @auth nav-authenticated @endauth">
        <a href="/" class="nav-logo">
            <img src="/images/logo-icon.png" id="logo-icon">
            <img src="/images/logo-text.png" id="logo-text">
        </a>

        @auth
            <button type="button" class="nav-toggle" onclick="toggleNavMenu()" aria-label="{{ __('Menu') }}">
                <svg class="nav-toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        @endauth

        <div class="nav-menu" id="nav-menu">
            @auth
                <a href="{{ route('reservations.index') }}" class="nav-link">{{ __('Reservations') }}</a>
                <a href="{{ route('contacts.index') }}" class="nav-link">{{ __('Contacts') }}</a>
                <a href="{{ route('invoices.index') }}" class="nav-link">{{ __('Invoices') }}</a>
                <a href="{{ route('rooms.index') }}" class="nav-link">{{ __('Rooms') }}</a>
                @can('viewany', App\Models\Owner::class)
                    <a href="{{ route('owners.index') }}" class="nav-link">{{ __('Owners') }}</a>
                @endcan
                @if(auth()->user()->is_global_admin)
                    <a href="{{ route('users.index') }}" class="nav-link">{{ __('Users') }}</a>
                    <a href="{{ route('system-settings.edit') }}" class="nav-link" title="{{ __('System settings') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                        </svg>
                    </a>
                @endif
                <a href="{{ route('profile') }}" class="nav-user-link"><span class="nav-user">{{ auth()->user()->name }}</span></a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-action logout">{{ __('Logout') }}</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="nav-action login">{{ __('Login') }}</a>
                <a href="{{ route('register') }}" class="nav-action register">{{ __('Register') }}</a>
            @endauth
        </div>
    </nav>
</header>

<script>
    function toggleNavMenu() {
        document.getElementById('nav-menu').classList.toggle('open');
    }

    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
        const nav = document.querySelector('.nav');
        const menu = document.getElementById('nav-menu');
        if (!nav.contains(e.target) && menu.classList.contains('open')) {
            menu.classList.remove('open');
        }
    });
</script>

@php
    // Handle query parameter messages (for login/logout where session is regenerated)
    $successMessage = session('success');
    if (!$successMessage && request()->query('login_success')) {
        $successMessage = __('Login successful!');
    }
    if (!$successMessage && request()->query('logout_success')) {
        $successMessage = __('You are now logged out.');
    }
@endphp

@if($successMessage)
    <div id="flash-success" class="flash-message flash-success">
        <div class="flash-content">
            <svg class="flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ $successMessage }}</span>
        </div>
        <button type="button" class="flash-close" onclick="this.parentElement.remove()">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
@endif

@if(session('error'))
    <div id="flash-error" class="flash-message flash-error">
        <div class="flash-content">
            <svg class="flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ session('error') }}</span>
        </div>
        <button type="button" class="flash-close" onclick="this.parentElement.remove()">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
@endif

<main class="container">
    @yield('content')
</main>

<footer class="pepite-footer">
    <span>Réservation de salles · La Pépite</span>
    <span class="sep">·</span>
    <span>Propulsé par <a href="https://github.com/theosche/libreRooms" target="_blank" rel="noopener">LibreRooms</a>, plateforme libre de Théophile Schenker</span>
</footer>
</body>
</html>

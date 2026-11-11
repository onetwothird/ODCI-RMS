<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'ODCI Document Hub'))</title>

    {{--
        Static assets are served from public/ directly.
        @vite is intentionally NOT used yet: the Vite manifest
        (public/build/manifest.json) does not exist until `npm run build`
        is run, and @vite throws ViteManifestNotFoundException without it.
    --}}
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="{{ asset('css/cvsu-auth.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="icon" type="image/png" href="{{ asset('img/cvsu-logo.png') }}">
</head>

<body class="app-body">
    <header class="app-header">
        <div class="app-header-inner">
            <a href="{{ url('/') }}" class="app-brand">
                <img src="{{ asset('img/cvsu-logo.png') }}" alt="CVSU Naic Logo" class="app-brand-logo">
                <span class="app-brand-text">
                    <strong>ODCI Document Hub</strong>
                    <small>Office of the Director for Curriculum and Instruction</small>
                </span>
            </a>

            @auth
                <div class="app-header-user">
                    <span class="app-role-badge">{{ str_replace('_', ' ', strtoupper(auth()->user()->role)) }}</span>
                    <span class="app-user-name">{{ auth()->user()->full_name ?: auth()->user()->username }}</span>

                    <form method="POST" action="{{ route('logout') }}" class="app-logout-form">
                        @csrf
                        <button type="submit" class="btn btn-outline">Logout</button>
                    </form>
                </div>
            @endauth
        </div>
    </header>

    <main class="app-main">
        @yield('content')
    </main>

    <footer class="app-footer">
        <p>&copy; {{ date('Y') }} CVSU Naic &mdash; Office of the Director for Curriculum and Instruction</p>
    </footer>
</body>

</html>
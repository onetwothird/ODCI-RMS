<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard - CVSU Naic')</title>

    <link rel="icon" type="image/png" href="{{ asset('img/cvsu-logo.png') }}">

    {{-- Boxicons (legacy version) --}}
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    {{-- Page CSS loaded before the component stylesheets (legacy order) --}}
    @yield('page-css-pre')

    {{-- Component Stylesheets (identical order to legacy roles/superadmin/*.php) --}}
    <link rel="stylesheet" href="{{ asset('css/superadmin/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/superadmin/sidebar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/superadmin/navbar.css') }}">

    {{-- Page-specific Stylesheets --}}
    @yield('page-css')

    {{-- Shared CVSU design system (green / gold / white) - loaded last on purpose --}}
    @vite('resources/css/app.css')
</head>

<body>
    {{-- Sidebar Component --}}
    @include('partials.superadmin.sidebar')

    {{-- Content --}}
    <section id="content">
        {{-- Navbar Component --}}
        @include('partials.superadmin.navbar')

        {{-- Main Content --}}
        <main>
            @yield('content')
        </main>
    </section>

    {{-- Shared CVSU theme JS (sidebar / drawer / active-nav behaviour) --}}
    <script src="{{ asset('js/cvsu-theme.js') }}"></script>

    {{-- Legacy superadmin JS --}}
    <script src="{{ asset('js/superadmin/script.js') }}"></script>

    @yield('scripts')
    @stack('scripts')
</body>

</html>

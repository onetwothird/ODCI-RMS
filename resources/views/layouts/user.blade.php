<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard - CVSU Naic')</title>

    <link rel="icon" type="image/png" href="{{ asset('img/cvsu-logo.png') }}">
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>

    {{-- Component Stylesheets (identical order to legacy roles/user/*.php) --}}
    <link rel="stylesheet" href="{{ asset('css/user/components/sidebar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/user/components/navbar.css') }}">

    {{-- Page-specific CSS --}}
    @yield('page-css')

    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    {{-- Shared CVSU design system (green / gold / white) - loaded last on purpose --}}
    <link rel="stylesheet" href="{{ asset('css/cvsu-theme.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cvsu-product.css') }}">

    {{-- Legacy: department code badge on the profile tile --}}
    @if (auth()->user()?->department?->department_code)
        <style>
            .profile::after {
                content: '{{ auth()->user()->department->department_code }}';
                position: absolute;
                bottom: -2px;
                right: -2px;
                background: var(--blue);
                color: white;
                font-size: 8px;
                padding: 2px 4px;
                border-radius: 4px;
                font-weight: 500;
                min-width: 20px;
                text-align: center;
            }
        </style>
    @endif
</head>

<body class="@yield('body-class')">
    {{-- Sidebar Component --}}
    @include('partials.user.sidebar')

    {{-- Content --}}
    <section id="content">
        {{-- Navbar Component --}}
        @include('partials.user.navbar')

        {{-- Main Content --}}
        <main>
            @yield('content')
        </main>
    </section>

    {{-- Shared CVSU theme JS (sidebar / drawer / active-nav behaviour) --}}
    <script src="{{ asset('js/cvsu-theme.js') }}"></script>

    {{-- Legacy user JS (assets/js/script.js does not exist in the legacy tree either) --}}
    <script src="{{ asset('js/user/pages/dashboard.js') }}"></script>
    <script src="{{ asset('js/user/components/navbar.js') }}"></script>

    @yield('scripts')
</body>

</html>

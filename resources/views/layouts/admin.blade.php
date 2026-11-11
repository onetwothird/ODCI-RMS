<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard - CVSU Naic')</title>

    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" type="image/png" href="{{ asset('img/cvsu-logo.png') }}">

    {{-- Modular CSS - Base & Components (identical order to legacy roles/admin/*.php) --}}
    <link rel="stylesheet" href="{{ asset('css/admin/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/components/sidebar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/components/navbar.css') }}">

    {{-- Page-specific CSS --}}
    @yield('page-css')

    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    {{-- Shared CVSU design system (green / gold / white) - loaded last on purpose --}}
    <link rel="stylesheet" href="{{ asset('css/cvsu-theme.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cvsu-product.css') }}">
</head>

<body class="@yield('body-class')">
    {{-- Sidebar Component --}}
    @include('partials.admin.sidebar')

    {{-- Content --}}
    <section id="content">
        {{-- Navbar Component --}}
        @include('partials.admin.navbar')

        {{-- Main Content --}}
        <main>
            @yield('content')
        </main>
    </section>

    {{-- Shared CVSU theme JS (sidebar / drawer / active-nav behaviour) --}}
    <script src="{{ asset('js/cvsu-theme.js') }}"></script>

    {{-- Legacy admin JS --}}
    <script src="{{ asset('js/admin/components/sidebar.js') }}"></script>
    <script src="{{ asset('js/admin/components/navbar.js') }}"></script>
    <script src="{{ asset('js/admin/pages/dashboard.js') }}"></script>

    @yield('scripts')
</body>

</html>

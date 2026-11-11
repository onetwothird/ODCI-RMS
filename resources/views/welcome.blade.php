@extends('layouts.app')

@section('title', config('app.name', 'ODCI Document Hub'))

@section('content')
    @php
        // Legacy getDashboardUrl() behaviour: an authenticated visitor is sent
        // straight to their own dashboard instead of seeing a landing page.
        $dashboardRoute = match (auth()->user()?->role) {
            'super_admin' => 'superadmin.dashboard',
            'admin' => 'admin.dashboard',
            'user' => 'user.dashboard',
            default => null,
        };
    @endphp

    <div class="card">
        <h1 class="card-title">ODCI Document Hub</h1>
        <p class="card-subtitle">
            Office of the Director for Curriculum and Instruction &mdash; Document Management System
        </p>

        @auth
            <p>You are signed in as <strong>{{ auth()->user()->full_name ?: auth()->user()->username }}</strong>.</p>

            @if ($dashboardRoute)
                <a href="{{ route($dashboardRoute) }}" class="btn btn-primary">Go to my dashboard</a>
            @endif
        @else
            <div class="alert-box alert-box-info">
                This is the Laravel 11 port of the legacy Vanilla PHP system. Migration is in progress;
                the legacy application remains fully available and untouched.
            </div>

            <a href="{{ route('login') }}" class="btn btn-primary">Sign In</a>
        @endauth
    </div>
@endsection
@extends('layouts.user')

@section('title', 'Settings - CVSU Naic')
@section('body-class', 'user-settings-page')

@section('page-css')
@endsection

@section('content')
    <div class="p-6 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-ink">Account Settings</h1>
            <ul class="breadcrumb mt-1">
                <li><a href="{{ route('user.dashboard') }}">Dashboard</a></li>
                <li><i class='bx bx-chevron-right'></i></li>
                <li><a class="active" href="#">Settings</a></li>
            </ul>
        </div>

        @include('partials.profile.settings-page', ['forRole' => 'user'])
    </div>
@endsection
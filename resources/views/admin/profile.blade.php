@extends('layouts.admin')

@section('title', 'My Profile - CVSU Naic')
@section('body-class', 'admin-profile-page')

@section('page-css')
    <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
@endsection

@section('content')
    <div class="p-6 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-ink">My Profile</h1>
            <ul class="breadcrumb mt-1">
                <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li><i class='bx bx-chevron-right'></i></li>
                <li><a class="active" href="#">My Profile</a></li>
            </ul>
        </div>

        @include('partials.profile.settings-page', ['forRole' => 'admin'])
    </div>
@endsection
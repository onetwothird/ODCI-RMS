@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="card">
        <h1 class="card-title">{{ $title }}</h1>
        <p class="card-subtitle">Legacy source file: <code>{{ $legacyFile }}</code></p>

        <div class="alert-box alert-box-info">
            {{ $message }}
        </div>

        <p>
            This page is a deliberate placeholder so the login screen has no broken links.
            The behaviour will be migrated in a later, separately approved stage.
        </p>

        <a href="{{ route('login') }}" class="btn btn-outline">Back to Sign In</a>
    </div>
@endsection
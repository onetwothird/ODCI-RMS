@extends('layouts.superadmin')

@section('title', 'Document Tracker - CVSU Naic')

@section('page-css')
    <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
    <style>
        .fade-in { animation: saFade .35s ease both; }
        @keyframes saFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    </style>
@endsection

@section('content')
    <div class="p-6 lg:p-8 fade-in">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-ink">Document Submission Tracker</h1>
            <ul class="breadcrumb mt-1">
                <li><a href="{{ route('superadmin.dashboard') }}">Superadmin</a></li>
                <li><i class='bx bx-chevron-right'></i></li>
                <li><a class="active" href="#">Document Tracker</a></li>
            </ul>
        </div>

        @if (!empty($error))
            <div class="mb-6 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <i class='bx bx-error-circle text-xl'></i> {{ $error }}
            </div>
        @endif

        <div class="rounded-card border border-line bg-white p-6 shadow-card">
            <div class="mb-5 flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-cvsu-green-100 text-cvsu-green-600">
                    <i class='bx bx-building text-2xl'></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-ink">Select a Department</h3>
                    <p class="text-sm text-muted">Choose a department to view document submission status and track progress.</p>
                </div>
            </div>

            @if ($departments->isEmpty())
                <div class="rounded-lg bg-muted/5 p-8 text-center">
                    <i class='bx bx-building text-4xl text-muted'></i>
                    <p class="mt-2 text-sm text-muted">No active departments found.</p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($departments as $dept)
                        <a href="{{ route('superadmin.document-tracker', ['department' => $dept->id]) }}"
                           class="group rounded-card border border-line bg-white p-5 shadow-card transition hover:-translate-y-0.5 hover:border-cvsu-green-400 hover:shadow-card-md">
                            <div class="flex items-start justify-between">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cvsu-green-100 text-cvsu-green-600">
                                    <i class='bx bxs-buildings text-xl'></i>
                                </div>
                                <i class='bx bx-chevron-right text-xl text-muted transition group-hover:translate-x-0.5 group-hover:text-cvsu-green-600'></i>
                            </div>
                            <h4 class="mt-3 font-bold text-ink group-hover:text-cvsu-green-700">{{ $dept->department_name }}</h4>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                                <span><strong class="text-ink">{{ $dept->user_count }}</strong> Faculty Members</span>
                                <span><strong class="text-ink">{{ $dept->admin_count }}</strong> Department Admins</span>
                            </div>
                            <p class="mt-2 text-xs text-cvsu-green-600">Click to view document status</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
@endsection
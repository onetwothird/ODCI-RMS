@extends('layouts.admin')

@section('title', 'Document Tracker - CVSU Naic')
@section('body-class', 'admin-document-tracker-page')

@section('page-css')
@endsection

@section('content')
    <div class="p-6 lg:p-8">
        <div class="mx-auto max-w-lg rounded-card border border-line bg-white p-8 text-center shadow-card">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-cvsu-gold-100 text-cvsu-gold-600">
                <i class='bx bxs-user-x text-3xl'></i>
            </div>
            <h1 class="text-xl font-bold text-ink">No Department Assigned</h1>
            <p class="mt-2 text-sm text-muted">{{ $message }}</p>
            <p class="mt-1 text-sm text-muted">Ask the system administrator to assign your account to a department before using the Document Tracker.</p>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary mt-6">
                <i class='bx bx-arrow-back'></i> Back to Dashboard
            </a>
        </div>
    </div>
@endsection

@section('scripts')
@endsection
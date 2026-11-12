@extends('layouts.admin')

@section('title', 'All Files - CVSU Naic')
@section('body-class', 'admin-files-page')

@section('page-css')
@endsection

@section('content')
    @include('partials.files.files-list', [
        'pageTitle' => 'All Files',
        'pageIcon' => 'bxs-file',
        'baseRoute' => route('admin.files'),
        'isSuperAdmin' => false,
    ])
@endsection
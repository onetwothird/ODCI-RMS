@extends('layouts.superadmin')

@section('title', 'All Files - CVSU Naic')
@section('body-class', 'superadmin-files-page')

@section('page-css')
@endsection

@section('content')
    @include('partials.files.files-list', [
        'pageTitle' => 'All Files',
        'pageIcon' => 'bxs-file',
        'baseRoute' => route('superadmin.files'),
        'isSuperAdmin' => true,
    ])
@endsection
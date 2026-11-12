@extends('layouts.admin')

@section('title', 'All Folders - CVSU Naic')
@section('body-class', 'admin-folders-page')

@section('page-css')
@endsection

@section('content')
    @include('partials.files.folders-grid', [
        'pageTitle' => 'All Folders',
        'baseRoute' => route('admin.folders'),
    ])
@endsection
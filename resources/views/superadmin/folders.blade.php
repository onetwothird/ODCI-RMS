@extends('layouts.superadmin')

@section('title', 'All Folders - CVSU Naic')
@section('body-class', 'superadmin-folders-page')

@section('page-css')
@endsection

@section('content')
    @include('partials.files.folders-grid', [
        'pageTitle' => 'All Folders',
        'baseRoute' => route('superadmin.folders'),
    ])
@endsection
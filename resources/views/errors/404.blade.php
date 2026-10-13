@extends('layouts.app')
@section('title', 'Page not found')
@section('crumbs')<a href="{{ route('home') }}">Command Center</a> / Not found
@endsection
@section('heading', 'Page not found')
@section('content')
<div class="card"><div class="empty"><b>This page does not exist</b><a class="btn pri" href="{{ route('home') }}">Go to Command Center</a></div></div>
@endsection

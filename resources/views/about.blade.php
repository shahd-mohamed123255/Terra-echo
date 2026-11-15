@extends('layouts.app')

@section('title', 'About Us | Terra Echo')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/about.css') }}">
@endpush

@section('content')
<img src="{{ asset('images/about-page.jpg') }}"
     width="1440"
     height="2921"
     style="width: 100%; height: auto; display: block;"
     alt="About Terra Echo and the Orion A4S team: Shahd (Cloud Engineer), Sara (AI Engineer), Ahmed (UI/UX Designer), Sagda (Data Analyst) and Shahd (Full Stack Developer)">
@endsection
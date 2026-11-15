@extends('layouts.app')

@section('title', 'Terra Echo')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/home-strip.css') }}">
@endpush

@section('content')
<div class="bg"></div>

<div class="hero">
    <p class="tag">— From space to soil</p>
    <h1>Listen to Earth &amp; <span>Shape the future</span></h1>
    <p class="sub">NASA climate intelligence, translated into decisions for your farm.</p>
    <a class="btn" href="{{ route('farm.explore') }}">Explore your farm →</a>
</div>

<section class="home-strip" aria-label="Benefits">
    <div class="home-strip__item">
        <span class="home-strip__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                <path d="M19.5 4.5C12 4.5 6 6.5 6 12.5c0 3 2 5 5 5 6 0 8-6 8.5-13Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M4.5 20c2.5-5 6-8 11-11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
        </span>
        <span class="home-strip__copy">
            <span class="home-strip__title">Healthier fields</span>
            <span class="home-strip__description">Data-driven decisions for higher resilience</span>
        </span>
    </div>
    <div class="home-strip__item">
        <span class="home-strip__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                <path d="M4 20V14h4v6H4Zm6 0V10h4v10h-4Zm6 0V5h4v15h-4Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                <path d="M3 20.75h18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
        </span>
        <span class="home-strip__copy">
            <span class="home-strip__title">Greater efficiency</span>
            <span class="home-strip__description">Do more with fewer resources</span>
        </span>
    </div>
    <div class="home-strip__item">
        <span class="home-strip__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                <circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="1.5"/>
                <circle cx="5.5" cy="10" r="2.25" stroke="currentColor" stroke-width="1.5"/>
                <circle cx="18.5" cy="10" r="2.25" stroke="currentColor" stroke-width="1.5"/>
                <path d="M6.5 19v-1.5a5.5 5.5 0 0 1 11 0V19h-11Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M3 17.5a2.5 2.5 0 0 1 2.5-2.5M21 17.5a2.5 2.5 0 0 0-2.5-2.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
        </span>
        <span class="home-strip__copy">
            <span class="home-strip__title">A more sustainable tomorrow</span>
            {{-- Designer-provided description duplicates item 1; update here when confirmed. --}}
            <span class="home-strip__description">Data-driven decisions for higher resilience</span>
        </span>
    </div>
</section>
@endsection
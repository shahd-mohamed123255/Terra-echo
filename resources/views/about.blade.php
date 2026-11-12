@extends('layouts.app')

@section('title', 'About Us | Terra Echo')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/about.css') }}">
@endpush

@section('content')
<main class="about">

    <header class="about-hero">
        <h1>Orion A4S</h1>
        <p class="about-tagline">Five minds. One mission: farming beyond Earth.</p>
    </header>

    <img class="about-banner"
         src="{{ asset('images/team/team.jpeg') }}"
         alt="Orion A4S team members">

    <section class="about-section">
        <h2>Who We Are</h2>
        <p>
            We are Orion A4S, a team of five students passionate about technology,
            space, and agriculture. Our team combines Cloud, AI, UI/UX, and Data
            Analysis skills to build Terra Echo, a platform that turns NASA
            Earth-observation data into practical farming insights.
        </p>
    </section>

    <section class="about-section">
        <h2>Our Mission</h2>
        <p>
            Farming is changing, on Earth and beyond it. Terra Echo helps farmers
            plan crops, understand soil conditions, and get data-driven
            recommendations using NASA data. Our goal is to make smart agriculture
            simple and accessible for everyone.
        </p>
    </section>

    <section class="about-section">
        <h2>What We Built</h2>
        <p>
            FIELD SHIFT (Farming Beyond Earth): a full-stack web app for managing
            farms, fields, crop plans, and soil reports.
        </p>
    </section>

    <section class="about-section">
        <h2>Built With</h2>
        <div class="about-chips">
            <span>Laravel</span>
            <span>MySQL</span>
            <span>NASA Data</span>
            <span>AI</span>
            <span>Cloud</span>
        </div>
    </section>

</main>
@endsection
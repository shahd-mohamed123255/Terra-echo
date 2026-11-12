@extends('layouts.app')

@section('title', 'Terra Echo')

@section('content')
<div class="bg"></div>

<div class="hero">
    <p class="tag">— From space to soil</p>
    <h1>Listen to Earth &amp; <span>Shape the future</span></h1>
    <p class="sub">NASA climate intelligence, translated into decisions for your farm.</p>
    <a class="btn" href="{{ route('farm.explore') }}">Explore your farm →</a>
</div>
@endsection
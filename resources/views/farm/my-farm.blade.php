@extends('layouts.app')

@section('title', 'My Farm | Terra Echo')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/my-farm.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/chat.js') }}"></script>
@endpush

@section('content')
<main class="myfarm">

    <div class="myfarm-head">
        <div>
            <small class="eyebrow">From space to soil</small>
            <h1>My Farm</h1>
        </div>

        <div class="myfarm-actions">
            <select class="farm-select" onchange="location.href='{{ route('farm.index') }}?farm=' + this.value">
                @foreach ($farms as $f)
                    <option value="{{ $f->id }}" @selected($f->id === $farm->id)>
                        {{ $f->city }} Farm · {{ $f->area + 0 }} {{ $f->area_unit }}
                    </option>
                @endforeach
            </select>

            <a href="{{ route('farm.explore') }}" class="btn-add">+ Add New Farm</a>
        </div>
    </div>

    <div class="farm-strip">
        <div><small>Farm Name</small>{{ $farm->city }} Farm</div>
        <div><small>Location</small>{{ collect([$farm->city, $farm->governorate, $farm->country])->filter()->implode(', ') }}</div>
        <div><small>Total Area</small>{{ $farm->area + 0 }} {{ $farm->area_unit }}</div>
        <div><small>Soil</small>{{ $farm->soilType?->name ?? 'Requires local input' }}</div>
        <div><small>Last Update</small>{{ $farm->updated_at->format('d M Y · H:i') }}</div>
    </div>

</main>
@include('farm.partials.chat-widget')
@endsection
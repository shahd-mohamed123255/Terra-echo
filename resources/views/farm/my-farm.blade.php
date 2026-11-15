@extends('layouts.app')

@section('title', 'My Farm | Terra Echo')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/my-farm.css') }}">
    <link rel="stylesheet" href="{{ asset('css/crop-library.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/chat.js') }}"></script>
    <script src="{{ asset('js/crop-library.js') }}"></script>
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

    <div class="myfarm-layout">
        <div class="myfarm-primary"></div>

        <aside class="crop-library" aria-labelledby="crop-library-title">
            <div class="crop-library-heading">
                <h2 id="crop-library-title">Crop Library</h2>
                <label class="crop-library-search-label" for="crop-library-search">Search crops</label>
                <input
                    class="crop-library-search"
                    id="crop-library-search"
                    type="search"
                    placeholder="Search crops..."
                    autocomplete="off"
                >
            </div>

            <div class="crop-library-filters" aria-label="Filter crops by category">
                <button class="crop-category-chip is-active" type="button" data-category-filter="all" aria-pressed="true">All</button>
                @foreach ($crops->pluck('category')->filter()->unique()->sort() as $category)
                    <button
                        class="crop-category-chip"
                        type="button"
                        data-category-filter="{{ strtolower($category) }}"
                        aria-pressed="false"
                    >{{ ucfirst($category) }}</button>
                @endforeach
            </div>

            <div class="crop-library-list" id="crop-library-list">
                @foreach ($crops as $crop)
                    <article
                        class="crop-card"
                        data-category="{{ strtolower($crop->category ?? '') }}"
                        data-crop-name="{{ strtolower($crop->name) }}"
                    >
                        @if ($crop->image)
                            <img class="crop-card-image" src="{{ asset('images/crops/' . $crop->image) }}" alt="{{ $crop->name }}">
                        @else
                            <div class="crop-card-image crop-card-placeholder" aria-hidden="true"></div>
                        @endif

                        <div class="crop-card-content">
                            <div class="crop-card-title">
                                <h3>{{ $crop->name }}</h3>
                                <span class="crop-card-category">{{ $crop->category !== null ? ucfirst($crop->category) : 'Awaiting data' }}</span>
                            </div>

                            <dl class="crop-card-details">
                                <div>
                                    <dt>Water / season</dt>
                                    <dd>{{ collect([$crop->water_mm !== null ? $crop->water_mm . ' mm' : null, $crop->water_m3_per_ha !== null ? number_format($crop->water_m3_per_ha) . ' m³/ha' : null])->filter(fn ($value) => $value !== null)->implode(' · ') ?: 'Awaiting data' }}</dd>
                                </div>
                                <div>
                                    <dt>Growing season</dt>
                                    <dd>{{ collect([$crop->season !== null ? ucfirst($crop->season) : null, $crop->season_months])->filter(fn ($value) => $value !== null)->implode(' · ') ?: 'Awaiting data' }}</dd>
                                </div>
                                <div>
                                    <dt>Duration</dt>
                                    <dd>
                                        @if ($crop->duration_min_days !== null && $crop->duration_max_days !== null)
                                            {{ $crop->duration_min_days }}–{{ $crop->duration_max_days }} days
                                        @elseif ($crop->duration_min_days !== null)
                                            {{ $crop->duration_min_days }} days
                                        @elseif ($crop->duration_max_days !== null)
                                            {{ $crop->duration_max_days }} days
                                        @else
                                            Awaiting data
                                        @endif
                                    </dd>
                                </div>
                            </dl>

                            <button class="crop-card-add" type="button" disabled>Add to Simulation</button>
                        </div>
                    </article>
                @endforeach
            </div>
            <p class="crop-library-empty" id="crop-library-empty" hidden>No crops match your search.</p>
        </aside>
    </div>

</main>
@include('farm.partials.chat-widget')
@endsection
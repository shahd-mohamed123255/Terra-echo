@extends('layouts.app')

@section('title', 'My Farm | Terra Echo')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/my-farm.css') }}">
    <link rel="stylesheet" href="{{ asset('css/crop-library.css') }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
    <script src="{{ asset('js/chat.js') }}"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    @php
        $myFarmMapData = [
            'latitude' => $farm->latitude,
            'longitude' => $farm->longitude,
            'area' => $farm->area,
            'areaUnit' => $farm->area_unit,
            'plots' => $plots->map(fn ($plot) => [
                'id' => $plot->id,
                'name' => $plot->name,
                'area' => $plot->area,
                'status' => $plot->status,
                'crop' => $plot->crop ? ['id' => $plot->crop->id, 'name' => $plot->crop->name] : null,
                'assignUrl' => route('farm.plots.crop.assign', ['fieldId' => $plot->id]),
                'removeUrl' => route('farm.plots.crop.remove', ['fieldId' => $plot->id]),
            ])->values(),
        ];
    @endphp
    <script>
        window.myFarmMapData = @json($myFarmMapData);
    </script>
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
        <div><small>Soil</small>{{ $farm->soilType?->name ?? 'Awaiting data' }}</div>
        <div><small>Last Update</small>{{ $farm->updated_at->format('d M Y · H:i') }}</div>
    </div>

    <div class="myfarm-layout">
        <section class="myfarm-primary" aria-labelledby="farm-map-title">
            <div class="farm-map-card">
                <h2 id="farm-map-title">Your farm, from above</h2>
                <p class="plot-selection-hint" id="plot-selection-hint">Choose a crop from the library, then select a plot.</p>
                <p class="plot-request-message" id="plot-request-message" role="status" aria-live="polite" hidden></p>

                @if ($farm->latitude !== null && $farm->longitude !== null)
                    <div id="farm-map" class="farm-map" role="region" aria-label="Satellite map with your farm plots"></div>
                    <p class="farm-map-message" id="farm-map-message" role="status" hidden></p>
                @else
                    <p class="farm-map-message">Your farm map will appear here once its location has been saved.</p>
                @endif

                <div class="farm-map-layers" role="tablist" aria-label="Map layers">
                    <button class="farm-map-layer is-active" type="button" role="tab" aria-selected="true">Satellite View</button>
                    <button class="farm-map-layer" type="button" role="tab" aria-disabled="true" disabled>Soil Moisture <span>Awaiting data</span></button>
                    <button class="farm-map-layer" type="button" role="tab" aria-disabled="true" disabled>Soil Health <span>Awaiting data</span></button>
                    <button class="farm-map-layer" type="button" role="tab" aria-disabled="true" disabled>Vegetation Health <span>Awaiting data</span></button>
                    <button class="farm-map-layer" type="button" role="tab" aria-disabled="true" disabled>Water Availability <span>Awaiting data</span></button>
                </div>

                <p class="farm-map-summary" id="farm-map-summary" aria-live="polite"></p>
                <p class="farm-map-caption">Approximate plot layout, not a surveyed boundary</p>
            </div>

            <section class="farm-data-cards" aria-label="Farm conditions">
                <article class="farm-data-card">
                    <h2>Soil</h2>
                    <dl>
                        <div><dt>Soil type</dt><dd>{{ $farm->soilType?->name ?? 'Awaiting data' }}</dd></div>
                        <div><dt>pH</dt><dd>{{ $farm->soilReport?->ph ?? 'Awaiting data' }}</dd></div>
                        <div><dt>Salinity</dt><dd>{{ $farm->soilReport?->salinity ?? 'Awaiting data' }}</dd></div>
                        <div><dt>Nitrogen</dt><dd>{{ $farm->soilReport?->nitrogen ?? 'Awaiting data' }}</dd></div>
                        <div><dt>Phosphorus</dt><dd>{{ $farm->soilReport?->phosphorus ?? 'Awaiting data' }}</dd></div>
                        <div><dt>Potassium</dt><dd>{{ $farm->soilReport?->potassium ?? 'Awaiting data' }}</dd></div>
                    </dl>
                </article>

                <article class="farm-data-card">
                    <h2>Water</h2>
                    <dl>
                        <div><dt>Irrigation source</dt><dd>{{ $farm->irrigation_source ?: 'Awaiting data' }}</dd></div>
                        <div><dt>Water availability</dt><dd>Awaiting data</dd></div>
                    </dl>
                </article>

                <article class="farm-data-card">
                    <h2>Climate</h2>
                    <dl>
                        <div><dt>Climate observations</dt><dd>Awaiting data</dd></div>
                        <div><dt>Farm agent ID</dt><dd>{{ $farm->agent_farmer_id ?: 'Awaiting data' }}</dd></div>
                    </dl>
                </article>
            </section>
        </section>

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

            <section class="selected-crops" aria-labelledby="selected-crops-title">
                <h3 id="selected-crops-title">Selected Crops</h3>
                <div class="selected-crops-list" id="selected-crops-list" aria-live="polite">
                    @forelse ($plots->filter(fn ($plot) => $plot->crop)->values() as $plot)
                        <article class="selected-crop-item" data-selected-plot="{{ $plot->id }}">
                            <div>
                                <strong>{{ $plot->name }} · {{ $plot->crop->name }}</strong>
                                <span>{{ $plot->area }} {{ $farm->area_unit }}</span>
                            </div>
                            <button
                                class="selected-crop-remove"
                                type="button"
                                data-plot-id="{{ $plot->id }}"
                                aria-label="Remove {{ $plot->crop->name }} from {{ $plot->name }}"
                            >×</button>
                        </article>
                    @empty
                        <p class="selected-crops-empty">No crops selected yet.</p>
                    @endforelse
                </div>
            </section>

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

                            <button
                                class="crop-card-add"
                                type="button"
                                data-crop-id="{{ $crop->id }}"
                                data-crop-name="{{ $crop->name }}"
                                aria-pressed="false"
                            >Select for plot</button>
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
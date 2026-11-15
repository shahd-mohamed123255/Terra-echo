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
            'simulationPlots' => $latestCompletedSimulation
                ? data_get($latestCompletedSimulation->results, 'sequence', [])
                : [],
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
        $myFarmSimulationData = $latestSimulation ? [
            'id' => $latestSimulation->id,
            'selected_plan_id' => $latestSimulation->selected_plan_id,
            'status' => $latestSimulation->status,
            'stage' => $latestSimulation->stage,
            'error' => $latestSimulation->error,
            'plots' => $latestSimulation->plots,
            'results' => $latestSimulation->status === 'completed' ? $latestSimulation->results : null,
            'started_at' => $latestSimulation->started_at,
            'finished_at' => $latestSimulation->finished_at,
        ] : null;
    @endphp
    <script>
        window.myFarmMapData = @json($myFarmMapData);
        window.myFarmScenarioData = {
            saveUrl: @json(route('farm.simulations.store', ['farm' => $farm->id])),
            runUrl: @json(route('farm.simulations.run', ['simulation' => '__simulation__'])),
            showUrl: @json(route('farm.simulations.show', ['simulation' => '__simulation__'])),
            cancelUrl: @json(route('farm.simulations.cancel', ['simulation' => '__simulation__'])),
            farmName: @json($farm->city . ' Farm'),
            farmId: @json($farm->id),
            settings: @json($scenarioSettings),
            simulation: @json($myFarmSimulationData),
        };
        window.myFarmSoilValues = {
            soilType: @json($farm->soilType?->name),
            ph: @json($farm->soilReport?->ph),
            salinity: @json($farm->soilReport?->salinity),
            nitrogen: @json($farm->soilReport?->nitrogen),
            phosphorus: @json($farm->soilReport?->phosphorus),
            potassium: @json($farm->soilReport?->potassium),
            irrigationSource: @json($farm->irrigation_source),
        };
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
        <div><small>Total Area</small>{{ $farm->area !== null ? ($farm->area + 0) . ' ' . $farm->area_unit : 'Awaiting data' }}</div>
        <div><small>Agricultural Plots</small>{{ $plots->count() }}</div>
        <div><small>Soil</small>{{ $farm->soilType?->name ?? 'Awaiting data' }}</div>
        <div><small>Farming Plan</small>{{ $selectedCropPlan ? $selectedCropPlan->name : 'None selected' }}</div>
        <div><small>Last Update</small>{{ $farm->updated_at->format('d M Y · H:i') }}</div>
    </div>

    @if ($selectedCropPlan)
        <div class="active-plan-banner" style="margin: 1.25rem 0; padding: 1rem 1.5rem; background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.25); border-radius: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="font-size: 1.75rem;">🌾</span>
                <div>
                    <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #16a34a; font-weight: 700;">Active Farming Plan</div>
                    <strong style="font-size: 1.1rem; color: #1f2937;">{{ $selectedCropPlan->name }}</strong>
                    <span style="margin-left: 0.5rem; font-size: 0.85rem; color: #4b5563;">
                        ({{ ucfirst($selectedCropPlan->season ?? 'season') }}
                        @if(data_get($selectedCropPlan->metrics, 'suitability_score')) · Suitability: <strong>{{ data_get($selectedCropPlan->metrics, 'suitability_score') }}/100</strong>@endif
                        @if(data_get($selectedCropPlan->metrics, 'water_demand_m3')) · Water Demand: <strong>{{ number_format(data_get($selectedCropPlan->metrics, 'water_demand_m3')) }} m³</strong>@endif
                        @if(data_get($selectedCropPlan->metrics, 'total_yield_tons')) · Est. Yield: <strong>{{ data_get($selectedCropPlan->metrics, 'total_yield_tons') }} tons</strong>@endif)
                    </span>
                </div>
            </div>
            <a href="{{ route('plans', ['farm' => $farm->id]) }}" style="padding: 0.5rem 1rem; background: #fff; border: 1px solid #16a34a; color: #16a34a; border-radius: 8px; font-weight: 600; font-size: 0.875rem; text-decoration: none;">
                Change Plan ➔
            </a>
        </div>
    @else
        <div class="active-plan-banner" style="margin: 1.25rem 0; padding: 1rem 1.5rem; background: rgba(234, 179, 8, 0.08); border: 1px solid rgba(234, 179, 8, 0.3); border-radius: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <span style="font-size: 1.75rem;">📋</span>
                <div>
                    <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #ca8a04; font-weight: 700;">No Plan Selected</div>
                    <span style="font-size: 0.95rem; color: #4b5563;">Generate AI-optimized cropping plans based on your soil, climate, and water budget.</span>
                </div>
            </div>
            <a href="{{ route('plans', ['farm' => $farm->id]) }}" style="padding: 0.5rem 1rem; background: #ca8a04; color: #fff; border-radius: 8px; font-weight: 600; font-size: 0.875rem; text-decoration: none;">
                Select Farming Plan ➔
            </a>
        </div>
    @endif

    <div class="myfarm-layout">
        <section class="myfarm-primary" aria-labelledby="farm-map-title">
            @php($insights = $latestCompletedSimulation?->results ?? [])
            <div class="farm-map-card">
                <h2 id="farm-map-title">Your farm, from above</h2>
                @if ($farm->latitude !== null && $farm->longitude !== null)
                    <p class="farm-map-coordinates">{{ $farm->latitude }}, {{ $farm->longitude }}</p>
                @else
                    <p class="farm-map-coordinates">Awaiting data</p>
                @endif
                <p class="plot-selection-hint" id="plot-selection-hint">Choose a crop from the library, then select a plot.</p>
                <p class="plot-request-message" id="plot-request-message" role="status" aria-live="polite" hidden></p>

                @if ($farm->latitude !== null && $farm->longitude !== null)
                    <div id="farm-map" class="farm-map" role="region" aria-label="Satellite map with your farm plots"></div>
                    <p class="farm-map-message" id="farm-map-message" role="status" hidden></p>
                @else
                    <p class="farm-map-message">Your farm map will appear here once its location has been saved.</p>
                @endif

                @if ($farm->latitude !== null && $farm->longitude !== null)
                    <button class="farm-boundary-toggle" id="farm-boundary-toggle" type="button" aria-pressed="true">
                        Hide farm boundary
                    </button>
                @endif
                <section class="plot-details-card" id="plot-details-card" aria-live="polite" hidden>
                    <h3 id="plot-details-name"></h3>
                    <dl>
                        <div><dt>Area</dt><dd id="plot-details-area"></dd></div>
                        <div><dt>Crop</dt><dd id="plot-details-crop"></dd></div>
                        <div><dt>Status</dt><dd id="plot-details-status"></dd></div>
                        <div><dt>Suitability</dt><dd id="plot-details-suitability"></dd></div>
                        <div><dt>Seasonal water demand</dt><dd id="plot-details-water"></dd></div>
                        <div><dt>Comment</dt><dd id="plot-details-comment"></dd></div>
                    </dl>
                    <button class="farm-plot-remove" id="plot-details-remove" type="button" hidden>Remove crop</button>
                </section>

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
                        <div><dt>Soil type</dt><dd data-condition-field="soil.type">{{ data_get($insights, 'soil.type') ?? $farm->soilType?->name ?? 'Awaiting data' }}</dd></div>
                        <div><dt>pH</dt><dd data-condition-field="soil.ph">{{ data_get($insights, 'soil.ph') ?? $farm->soilReport?->ph ?? 'Awaiting data' }}</dd></div>
                        <div><dt>Salinity</dt><dd data-condition-field="soil.salinity">{{ data_get($insights, 'soil.salinity') ?? $farm->soilReport?->salinity ?? 'Awaiting data' }}</dd></div>
                        <div><dt>Moisture</dt><dd data-condition-field="soil.moisture_pct">{{ data_get($insights, 'soil.moisture_pct') ?? 'Awaiting data' }}</dd></div>
                        <div><dt>Soil health</dt><dd data-condition-field="soil.health_score">{{ data_get($insights, 'soil.health_score') ?? 'Awaiting data' }}</dd></div>
                        <div><dt>Nitrogen</dt><dd data-condition-field="soil.nitrogen">{{ data_get($insights, 'soil.nitrogen') ?? $farm->soilReport?->nitrogen ?? 'Awaiting data' }}</dd></div>
                        <div><dt>Phosphorus</dt><dd data-condition-field="soil.phosphorus">{{ data_get($insights, 'soil.phosphorus') ?? $farm->soilReport?->phosphorus ?? 'Awaiting data' }}</dd></div>
                        <div><dt>Potassium</dt><dd data-condition-field="soil.potassium">{{ data_get($insights, 'soil.potassium') ?? $farm->soilReport?->potassium ?? 'Awaiting data' }}</dd></div>
                    </dl>
                </article>

                <article class="farm-data-card">
                    <h2>Water</h2>
                    <dl>
                        <div><dt>Irrigation source</dt><dd data-condition-field="water.irrigation_source">{{ data_get($insights, 'water.irrigation_source') ?? ($farm->irrigation_source ?: 'Awaiting data') }}</dd></div>
                        <div><dt>Water availability</dt><dd data-condition-field="water.available_m3">{{ data_get($insights, 'water.available_m3') ?? 'Awaiting data' }}</dd></div>
                        <div><dt>Water required</dt><dd data-condition-field="water.required_m3">{{ data_get($insights, 'water.required_m3') ?? 'Awaiting data' }}</dd></div>
                        <div><dt>Water balance</dt><dd data-condition-field="water.balance_m3">{{ data_get($insights, 'water.balance_m3') ?? 'Awaiting data' }}</dd></div>
                    </dl>
                </article>

                <article class="farm-data-card">
                    <h2>Climate</h2>
                    <dl>
                        <div><dt>Climate observations</dt><dd data-condition-field="climate.observations">{{ data_get($insights, 'climate.observations') ?? 'Awaiting data' }}</dd></div>
                        <div><dt>Farm agent ID</dt><dd>{{ $farm->agent_farmer_id ?: 'Awaiting data' }}</dd></div>
                    </dl>
                </article>
            </section>

            <section class="scenario-card" aria-labelledby="scenario-title">
                <h2 id="scenario-title">Configure Your Simulation</h2>
                <p class="scenario-description">Review the selected farm and planted plots, then save a scenario configuration.</p>

                <div class="scenario-readonly">
                    <div>
                        <h3>Selected farm</h3>
                        <p>{{ $farm->city }} Farm · {{ $farm->area !== null ? ($farm->area + 0) . ' ' . $farm->area_unit : 'Awaiting data' }}</p>
                    </div>
                    <div>
                        <h3>Selected plots and crops</h3>
                        <ul id="scenario-selected-plots" aria-live="polite">
                            @forelse ($plots->filter(fn ($plot) => $plot->crop)->values() as $plot)
                                <li>{{ $plot->name }} · {{ $plot->crop->name }}</li>
                            @empty
                                <li class="scenario-empty">No plots with assigned crops.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div>
                        <h3>Soil conditions</h3>
                        <dl>
                            <div><dt>Soil type</dt><dd>{{ $farm->soilType?->name ?: 'Awaiting data' }}</dd></div>
                            <div><dt>pH</dt><dd>{{ $farm->soilReport?->ph ?? 'Awaiting data' }}</dd></div>
                            <div><dt>Salinity</dt><dd>{{ $farm->soilReport?->salinity ?? 'Awaiting data' }}</dd></div>
                            <div><dt>Nitrogen</dt><dd>{{ $farm->soilReport?->nitrogen ?? 'Awaiting data' }}</dd></div>
                            <div><dt>Phosphorus</dt><dd>{{ $farm->soilReport?->phosphorus ?? 'Awaiting data' }}</dd></div>
                            <div><dt>Potassium</dt><dd>{{ $farm->soilReport?->potassium ?? 'Awaiting data' }}</dd></div>
                        </dl>
                    </div>
                </div>

                <form id="scenario-form" class="scenario-form">
                    <p class="scenario-readiness" id="scenario-readiness" role="status" aria-live="polite"></p>
                    <div class="scenario-fields">
                        <label>
                            Scenario name (optional)
                            <input
                                type="text"
                                name="name"
                                maxlength="60"
                                value="{{ $latestSimulation?->name ?? '' }}"
                                autocomplete="off"
                            >
                            <span class="scenario-field-error" data-error-for="name"></span>
                        </label>
                        <label>
                            Planting season
                            <select name="planting_season" required>
                                <option value="">Choose a season</option>
                                @foreach ($simulationOptions['planting_seasons'] as $key => $label)
                                    <option value="{{ $key }}" @selected(($scenarioSettings['planting_season'] ?? '') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="scenario-field-error" data-error-for="planting_season"></span>
                        </label>
                        <label>
                            Start date
                            <input type="date" name="start_date" value="{{ $scenarioSettings['start_date'] ?? '' }}" required>
                            <span class="scenario-field-error" data-error-for="start_date"></span>
                        </label>
                        <label>
                            Duration in days
                            <div class="scenario-duration">
                                <input
                                    type="number"
                                    name="duration_days"
                                    min="{{ $simulationOptions['duration_min'] }}"
                                    max="{{ $simulationOptions['duration_max'] }}"
                                    value="{{ $scenarioSettings['duration_days'] ?? '' }}"
                                    required
                                >
                                <span>End date: <strong id="scenario-end-date">Awaiting data</strong></span>
                            </div>
                            <span class="scenario-field-error" data-error-for="duration_days"></span>
                        </label>
                        <label>
                            Water availability scenario
                            <select name="water_scenario" required>
                                <option value="">Choose water availability</option>
                                @foreach ($simulationOptions['water_scenarios'] as $key => $label)
                                    <option value="{{ $key }}" @selected(($scenarioSettings['water_scenario'] ?? '') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="scenario-field-error" data-error-for="water_scenario"></span>
                        </label>
                        <label>
                            Climate scenario
                            <select name="climate_scenario" required>
                                <option value="">Choose a climate scenario</option>
                                @foreach ($simulationOptions['climate_scenarios'] as $key => $label)
                                    <option value="{{ $key }}" @selected(($scenarioSettings['climate_scenario'] ?? '') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="scenario-field-error" data-error-for="climate_scenario"></span>
                        </label>
                        <label>
                            Seasonal reference ET₀ (mm, optional)
                            <input type="number" name="seasonal_et0_mm" min="0" step="any" value="{{ $scenarioSettings['seasonal_et0_mm'] ?? '' }}">
                            <span class="scenario-field-error" data-error-for="seasonal_et0_mm"></span>
                        </label>
                        <label>
                            Seasonal precipitation (mm, optional)
                            <input type="number" name="seasonal_precipitation_mm" min="0" step="any" value="{{ $scenarioSettings['seasonal_precipitation_mm'] ?? '' }}">
                            <span class="scenario-field-error" data-error-for="seasonal_precipitation_mm"></span>
                        </label>
                        <label>
                            Available irrigation (mm, optional)
                            <input type="number" name="seasonal_irrigation_available_mm" min="0" step="any" value="{{ $scenarioSettings['seasonal_irrigation_available_mm'] ?? '' }}">
                            <span class="scenario-field-error" data-error-for="seasonal_irrigation_available_mm"></span>
                        </label>
                        <label>
                            Rainfall change (%)
                            <input type="number" name="rainfall_change_pct" min="-40" max="40" step="any" value="{{ $scenarioSettings['rainfall_change_pct'] ?? 0 }}">
                            <span class="scenario-field-error" data-error-for="rainfall_change_pct"></span>
                        </label>
                        <label>
                            Temperature change (°C)
                            <input type="number" name="temperature_change_c" min="-4" max="6" step="any" value="{{ $scenarioSettings['temperature_change_c'] ?? 0 }}">
                            <span class="scenario-field-error" data-error-for="temperature_change_c"></span>
                        </label>
                        <label>
                            Water availability change (%)
                            <input type="number" name="water_availability_change_pct" min="-50" max="30" step="any" value="{{ $scenarioSettings['water_availability_change_pct'] ?? 0 }}">
                            <span class="scenario-field-error" data-error-for="water_availability_change_pct"></span>
                        </label>
                    </div>

                    <p class="scenario-description">Optional weather and irrigation values are not estimated from sample data. Leave them blank when no verified measurements are available; the result will report what cannot be calculated.</p>
                    <p class="scenario-message" id="scenario-message" role="status" aria-live="polite" hidden></p>
                    <div class="scenario-actions">
                        <button class="scenario-reset" type="reset">Reset Configuration</button>
                        <button class="scenario-save" type="submit">Save Scenario</button>
                        <button class="scenario-run" type="button" disabled title="Save a scenario before running it">Run Simulation</button>
                    </div>
                </form>
            </section>

            <section class="simulation-insights" id="simulation-insights" aria-labelledby="simulation-insights-title">
                <h2 id="simulation-insights-title">Simulation Insights</h2>
                <p class="insights-empty" id="insights-empty" @if ($insights) hidden @endif>Run a simulation to see insights.</p>
                <p class="insight-summary" id="insight-summary" @if (! $insights) hidden @endif></p>
                <div class="insights-grid" id="insights-grid" @if (! $insights) hidden @endif>
                    <article class="insight-card"><h3>Overall suitability</h3><div data-insight-field="overall_suitability_pct"></div></article>
                    <article class="insight-card"><h3>Crop Suitability per plot</h3><div data-insight-field="plots"></div></article>
                    <article class="insight-card"><h3>Water Demand</h3><div data-insight-field="water_demand"></div></article>
                    <article class="insight-card"><h3>Soil Compatibility</h3><div data-insight-field="soil_compatibility"></div></article>
                    <article class="insight-card"><h3>Scenario Comparison</h3><div data-insight-field="scenario_comparison"></div></article>
                    <article class="insight-card"><h3>Climate Risk</h3><div data-insight-field="climate_risk"></div></article>
                    <article class="insight-card"><h3>AI Recommendations</h3><div data-insight-field="ai_recommendations"></div></article>
                    <article class="insight-card"><h3>Crop Rotation</h3><div data-insight-field="crop_rotation"></div></article>
                    <article class="insight-card"><h3>Confidence</h3><div data-insight-field="confidence"></div></article>
                    <article class="insight-card"><h3>Data Sources</h3><div data-insight-field="data_sources"></div></article>
                </div>
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
                <p class="selected-crops-total" id="selected-crops-total" aria-live="polite">0 crops · 0 feddan</p>
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

    <div class="simulation-overlay" id="simulation-overlay" hidden role="dialog" aria-modal="true" aria-labelledby="simulation-overlay-title">
        <section class="simulation-overlay-card">
            <h2 id="simulation-overlay-title">Simulation in progress</h2>
            <p id="simulation-overlay-scenario"></p>
            <ol class="simulation-stages" id="simulation-stages">
                <li data-stage="validating">Validating</li>
                <li data-stage="sending">Sending</li>
                <li data-stage="waiting">Waiting</li>
                <li data-stage="saving">Saving</li>
            </ol>
            <p class="simulation-overlay-error" id="simulation-overlay-error" role="alert" hidden></p>
            <div class="simulation-overlay-actions">
                <button type="button" id="simulation-cancel">Cancel</button>
                <button type="button" id="simulation-close" hidden>Close</button>
                <button type="button" id="simulation-retry" hidden>Retry</button>
            </div>
        </section>
    </div>

</main>
@include('farm.partials.chat-widget')
@endsection
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summary | Terra Echo</title>

        <link rel="stylesheet" href="{{ asset('css/explore.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/details.css') }}?v={{ filemtime(public_path('css/details.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}?v={{ filemtime(public_path('css/responsive.css')) }}">
</head>
<body>

    <div class="bg"></div>
    <div class="bg-overlay"></div>

    @php
        $irrLabels = ['canal' => 'Canal water', 'groundwater' => 'Groundwater well', 'rainfed' => 'Rain-fed', 'mixed' => 'Mixed'];
        $prioLabels = ['balanced' => 'Balanced', 'water_efficiency' => 'Water efficiency', 'soil_health' => 'Soil health', 'local_cost' => 'Local cost'];

        $location = collect([$farm->city, $farm->governorate, $farm->country])->filter()->implode(', ');
        $report = $farm->soilReport;
        $area = rtrim(rtrim(number_format((float) $farm->area, 2, '.', ''), '0'), '.');
        $unknown = 'Requires local input';
    @endphp

    <div class="wizard">

        <aside class="sidebar">
            <a href="{{ route('home') }}" class="sidebar-logo"><img src="{{ asset('images/logo.png') }}" alt="" width="32" height="32">Terra Echo</a>

            <div class="user-badge">Hi, {{ auth()->user()->name }}</div>

            <ol class="steps">
                <li><a href="{{ route('farm.explore') }}"><span>1</span> Location</a></li>
                <li><a href="{{ route('farm.details') }}"><span>2</span> Details</a></li>
                <li><a href="{{ route('farm.water') }}"><span>3</span> Water and Crops</a></li>
                <li class="active"><a href="{{ route('farm.summary') }}"><span>4</span> Summary</a></li>
            </ol>
        </aside>

        <main class="content">

            <span class="step-pill">Step 4</span>
            <h1>Summary</h1>
            <h2>Review your farm profile</h2>
            <p class="hint">Anything marked unknown will appear as "Requires local input" in your results.</p>

            @if ($errors->any())
                <p class="alert error">{{ $errors->first() }}</p>
            @endif

            <div class="sum-card">

                <div class="sum-item">
                    <div>
                        <small>Location</small>
                        {{ $location ?: $unknown }}
                    </div>
                    <a href="{{ route('farm.explore') }}">Edit</a>
                </div>

                <div class="sum-item">
                    <div>
                        <small>Land</small>
                        {{ $area }} {{ $farm->area_unit }}
                        · {{ $farm->soilType?->name ?? $unknown }} soil
                        ·
                        @if ($report)
                            soil analysis: salinity {{ $report->salinity ?? $unknown }},
                            pH {{ $report->ph ?? $unknown }}
                        @else
                            no soil analysis
                        @endif
                    </div>
                    <a href="{{ route('farm.details') }}">Edit</a>
                </div>

                <div class="sum-item">
                    <div>
                        <small>Water and crops</small>
                        {{ $irrLabels[$farm->irrigation_source] ?? $unknown }}
                        · previous crop {{ $previous->implode(', ') ?: $unknown }}
                        · considering {{ $considering->implode(', ') ?: $unknown }}
                    </div>
                    <a href="{{ route('farm.water') }}">Edit</a>
                </div>

                <div class="sum-item">
                    <div>
                        <small>Goals</small>
                        {{ $prioLabels[$farm->priority] ?? $unknown }}
                        · {{ $farm->planning_horizon }} {{ $farm->planning_horizon == 1 ? 'season' : 'seasons' }}
                        · from {{ $farm->target_season }}
                    </div>
                    <a href="{{ route('farm.water') }}">Edit</a>
                </div>

            </div>

            @unless ($report)
                <p class="sum-warn">
                    No soil analysis or cost inputs yet. Soil-health notes will be general,
                    and cost comparison will be unavailable until you add local figures.
                </p>
            @endunless

            <div class="actions">
                <a href="{{ route('farm.water') }}" class="btn btn-light">Back</a>
                <a href="{{ route('plans') }}" class="btn btn-primary">Continue to Plans</a>
            </div>

        </main>
    </div>

</body>
</html>
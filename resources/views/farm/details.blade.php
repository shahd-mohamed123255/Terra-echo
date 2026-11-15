<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Details | Terra Echo</title>

        <link rel="stylesheet" href="{{ asset('css/explore.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/details.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}?v={{ filemtime(public_path('css/responsive.css')) }}">
</head>
<body>

    <div class="bg"></div>
    <div class="bg-overlay"></div>

    @php
        $answered = $farm->area !== null;
        $hasAnalysis = old('has_soil_analysis', $answered ? ($report ? 1 : 0) : null);
    @endphp

    <div class="wizard">

        <aside class="sidebar">
            <a href="{{ route('home') }}" class="sidebar-logo">Terra Echo</a>

            <div class="user-badge">Hi, {{ auth()->user()->name }}</div>

            <ol class="steps">
    <li><a href="{{ route('farm.explore') }}"><span>1</span> Location</a></li>
    <li class="active"><a href="{{ route('farm.details') }}"><span>2</span> Details</a></li>
    <li><a href="{{ route('farm.water') }}"><span>3</span> Water and Crops</a></li>
    <li><a href="{{ route('farm.summary') }}"><span>4</span> Summary</a></li>
</ol>
        </aside>

        <main class="content farm-step">

            <span class="step-pill">Step 2</span>
            <h1>Farm details</h1>
            <p class="hint">Tell Terra Echo about your land and soil.</p>

            @if ($errors->any())
                <p class="alert error">{{ $errors->first() }}</p>
            @endif

            <form method="POST" action="{{ route('farm.details.save') }}">
                @csrf

                <div class="d-block">
                    <h2>Farm Area</h2>
                    <div class="d-row">
                        <input class="d-input" type="number" step="0.01" min="0"
                               name="area" placeholder="Enter area"
                               value="{{ old('area', $farm->area) }}" required>

                        <select class="d-input" name="area_unit">
                            <option value="feddan" selected>Feddan</option>
                        </select>
                    </div>
                </div>

                <div class="d-block">
                    <h2>Do you have a soil analysis?</h2>
                    <div class="d-row">
                        <label class="choice">
                            <input type="radio" name="has_soil_analysis" value="1"
                                   @checked((string) $hasAnalysis === '1') required>
                            <span>Yes</span>
                        </label>
                        <label class="choice">
                            <input type="radio" name="has_soil_analysis" value="0"
                                   @checked((string) $hasAnalysis === '0')>
                            <span>No</span>
                        </label>
                    </div>

                    <div class="d-row" id="analysis-fields">
                        <input class="d-input" type="number" step="0.01" min="0"
                               name="salinity" placeholder="Salinity"
                               value="{{ old('salinity', $report->salinity ?? '') }}">
                        <input class="d-input" type="number" step="0.01" min="0" max="14"
                               name="ph" placeholder="PH"
                               value="{{ old('ph', $report->ph ?? '') }}">
                        <input class="d-input" type="number" step="0.01" min="0"
                               name="nitrogen" placeholder="Nitrogen"
                               value="{{ old('nitrogen', $report->nitrogen ?? '') }}">
                        <input class="d-input" type="number" step="0.01" min="0"
                               name="phosphorus" placeholder="Phosphorus"
                               value="{{ old('phosphorus', $report->phosphorus ?? '') }}">
                        <input class="d-input" type="number" step="0.01" min="0"
                               name="potassium" placeholder="Potassium"
                               value="{{ old('potassium', $report->potassium ?? '') }}">
                    </div>
                </div>

                <div class="d-block">
                    <h2>Soil type</h2>
                    <p class="hint">
                        Soil type affects how well water drains and how nutrients are held.
                        Squeeze a damp handful: gritty is sandy, sticky is clay, in between is loamy.
                    </p>

                    <div class="soil-cards">
                        @foreach ($soilTypes as $soil)
                            <label class="choice">
                                <input type="radio" name="soil_type_id" value="{{ $soil->id }}"
                                       @checked((int) old('soil_type_id', $farm->soil_type_id) === $soil->id) required>
                                                       <span class="soil-card">
                                    @php
    $key = strtolower($soil->slug ?: $soil->name);
    // [لون الإضاءة، تردد الحبيبات، ارتفاع البروز]
    [$tone, $freq, $relief] = match (true) {
        str_contains($key, 'loam') => ['#9a6240', '0.85', '3.2'],
        str_contains($key, 'sand') => ['#f3d595', '1.15', '2.0'],
        str_contains($key, 'clay') => ['#dd7142', '0.30', '5.5'],
        default                    => ['#a07650', '0.8',  '3.0'],
    };
    $id = $soil->id;
@endphp
<svg class="soil-svg" viewBox="0 0 120 100" role="img" aria-label="{{ $soil->name }}">
    <defs>
        <filter id="soil-{{ $id }}" x="-10%" y="-10%" width="120%" height="120%">
            <feTurbulence type="fractalNoise" baseFrequency="0.04" numOctaves="2" seed="{{ $id + 2 }}" result="warp"/>
            <feDisplacementMap in="SourceGraphic" in2="warp" scale="7" result="shape"/>
            <feTurbulence type="fractalNoise" baseFrequency="{{ $freq }}" numOctaves="4" seed="{{ $id }}" result="grain"/>
            <feDiffuseLighting in="grain" lighting-color="{{ $tone }}" surfaceScale="{{ $relief }}" result="lit">
                <feDistantLight azimuth="235" elevation="52"/>
            </feDiffuseLighting>
            <feComposite in="lit" in2="shape" operator="in"/>
        </filter>
        <filter id="shadow-{{ $id }}"><feGaussianBlur stdDeviation="3"/></filter>
        <linearGradient id="depth-{{ $id }}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#fff" stop-opacity=".18"/>
            <stop offset="1" stop-color="#000" stop-opacity=".42"/>
        </linearGradient>
        <linearGradient id="leaf-{{ $id }}" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#9be15d"/>
            <stop offset="1" stop-color="#2e8b3d"/>
        </linearGradient>
    </defs>

    <ellipse cx="60" cy="93" rx="46" ry="5" fill="#000" opacity=".35" filter="url(#shadow-{{ $id }})"/>

    <path d="M8 92 Q16 54 60 50 Q104 54 112 92 Z" fill="#888" filter="url(#soil-{{ $id }})"/>
    <path d="M8 92 Q16 54 60 50 Q104 54 112 92 Z" fill="url(#depth-{{ $id }})"/>

    <path d="M60 52 Q58 40 60 26" stroke="#4f9d3a" stroke-width="2.6" stroke-linecap="round" fill="none"/>
    <path d="M60 34 Q42 14 28 24 Q44 38 60 34Z" fill="url(#leaf-{{ $id }})"/>
    <path d="M60 34 Q44 26 31 24" stroke="#2e8b3d" stroke-width=".8" fill="none" opacity=".7"/>
    <path d="M60 29 Q78 6 93 16 Q77 33 60 29Z" fill="url(#leaf-{{ $id }})"/>
    <path d="M60 29 Q77 18 90 16" stroke="#2e8b3d" stroke-width=".8" fill="none" opacity=".7"/>
</svg>
                                    {{ $soil->name }}
                                </span>         
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="actions">
                    <a href="{{ route('farm.explore') }}" class="btn btn-light">Back</a>
                    <button type="submit" class="btn btn-primary">Continue</button>
                </div>

            </form>
        </main>
    </div>

    <script>
        const box = document.getElementById('analysis-fields');
        const radios = document.querySelectorAll('input[name=has_soil_analysis]');

        function toggleAnalysis() {
            const yes = document.querySelector('input[name=has_soil_analysis]:checked')?.value === '1';
            box.style.display = yes ? 'flex' : 'none';
        }

        radios.forEach(r => r.addEventListener('change', toggleAnalysis));
        toggleAnalysis();
    </script>

</body>
</html>
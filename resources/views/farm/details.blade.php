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
            <a href="{{ route('home') }}" class="sidebar-logo"><img src="{{ asset('images/logo.png') }}" alt="" width="32" height="32">Terra Echo</a>

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
                            <label class="choice soil-card">
                                <input type="radio" name="soil_type_id" value="{{ $soil->id }}"
                                       @checked((int) old('soil_type_id', $farm->soil_type_id) === $soil->id) required>
                                @php
                                    $key = strtolower($soil->slug ?: $soil->name);
                                    $image = match (true) {
                                        str_contains($key, 'clay') => 'clay.jpeg',
                                        str_contains($key, 'loam') => 'loamy.jpeg',
                                        str_contains($key, 'sand') => 'sandy.jpeg',
                                        default => null,
                                    };
                                @endphp
                                @if ($image)
                                    <img class="soil-image" src="{{ asset('images/' . $image) }}" alt="{{ $soil->name }} soil">
                                @endif
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
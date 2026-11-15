<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water and Crops | Terra Echo</title>

    <link rel="stylesheet" href="{{ asset('css/explore.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/details.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}?v={{ filemtime(public_path('css/responsive.css')) }}">
</head>
<body>

    <div class="bg"></div>
    <div class="bg-overlay"></div>

    @php
        $irr    = old('irrigation_source', $farm->irrigation_source);
        $prio   = old('priority', $farm->priority);
        $season = old('target_season', $farm->target_season);
        $prev   = old('previous_crops', $previousIds);
        $cons   = old('considering_crops', $consideringIds);

        $saved  = $farm->planning_horizon;
        $hChoice = old('planning_horizon_choice',
            $saved ? (in_array($saved, [1, 2, 3]) ? (string) $saved : 'custom') : null);
        $hCustom = old('custom_horizon', ($saved && ! in_array($saved, [1, 2, 3])) ? $saved : '');
    @endphp

    <div class="wizard">

        <aside class="sidebar">
            <a href="{{ route('home') }}" class="sidebar-logo"><img src="{{ asset('images/logo.png') }}" alt="" width="32" height="32">Terra Echo</a>

            <div class="user-badge">Hi, {{ auth()->user()->name }}</div>

            <ol class="steps">
                <li><a href="{{ route('farm.explore') }}"><span>1</span> Location</a></li>
                <li><a href="{{ route('farm.details') }}"><span>2</span> Details</a></li>
                <li class="active"><a href="{{ route('farm.water') }}"><span>3</span> Water and Crops</a></li>
                <li><a href="{{ route('farm.summary') }}"><span>4</span> Summary</a></li>
            </ol>
        </aside>

        <main class="content farm-step">

            <span class="step-pill">Step 3</span>
            <h1>Water and Crops</h1>
            <p class="hint">Terra Echo checks candidate crops against local rules before comparing them.</p>

            @if ($errors->any())
                <p class="alert error">{{ $errors->first() }}</p>
            @endif

            <form method="POST" action="{{ route('farm.water.save') }}">
                @csrf

                <div class="d-block">
                    <h2>Main irrigation source</h2>
                    <div class="d-row">
                        @foreach (['canal' => 'Canal Water', 'groundwater' => 'Groundwater Well', 'rainfed' => 'Rain-fed', 'mixed' => 'Mixed'] as $value => $label)
                            <label class="choice">
                                <input type="radio" name="irrigation_source" value="{{ $value }}"
                                       @checked($irr === $value) required>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="d-block">
                    <h2>Current or previous crops</h2>
                    <div class="d-row">
                        @foreach ($crops as $crop)
                            <label class="choice">
                                <input type="checkbox" name="previous_crops[]" value="{{ $crop->id }}"
                                       @checked(in_array($crop->id, array_map('intval', $prev)))>
                                <span>{{ $crop->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="d-block">
                    <h2>Crops you are considering</h2>
                    <div class="d-row">
                        @foreach ($crops as $crop)
                            <label class="choice">
                                <input type="checkbox" name="considering_crops[]" value="{{ $crop->id }}"
                                       @checked(in_array($crop->id, array_map('intval', $cons)))>
                                <span>{{ $crop->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="d-block">
                    <h2>What matters most?</h2>
                    <div class="d-row">
                        @foreach ([
                            'balanced'         => ['Balanced', 'Weigh water, soil and cost equally'],
                            'water_efficiency' => ['Water efficiency', 'Favor plans with lower water demand'],
                            'soil_health'      => ['Soil health', 'Favor plans that support soil over seasons'],
                            'local_cost'       => ['Local cost', 'Needs your local cost input'],
                        ] as $value => [$label, $hintText])
                            <label class="choice priority-choice">
                                <input type="radio" name="priority" value="{{ $value }}"
                                       @checked($prio === $value) required>
                                <span><strong>{{ $label }}</strong><small>{{ $hintText }}</small></span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="d-row" style="gap: 48px;">
                    <div class="d-block">
                        <h2>First target season</h2>
                        <select class="d-input" name="target_season" required>
                            <option value="" disabled @selected(! $season)>Select season</option>
                            @foreach ($seasons as $s)
                                <option value="{{ $s }}" @selected($season === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="d-block">
                        <h2>Planning horizon</h2>
                        <div class="d-row">
                            @foreach (['1' => '1 season', '2' => '2 seasons', '3' => '3 seasons', 'custom' => 'Custom'] as $value => $label)
                                <label class="choice">
                                    <input type="radio" name="planning_horizon_choice" value="{{ $value }}"
                                           @checked((string) $hChoice === (string) $value) required>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <input class="d-input" type="number" min="1" max="12" step="1"
                               id="custom-horizon" name="custom_horizon"
                               placeholder="Number of seasons" value="{{ $hCustom }}"
                               style="display:none; margin-top:12px;">
                    </div>
                </div>

                <div class="actions">
                    <a href="{{ route('farm.details') }}" class="btn btn-light">Back</a>
                    <button type="submit" class="btn btn-primary">Continue</button>
                </div>

            </form>
        </main>
    </div>

    <script>
        const customBox = document.getElementById('custom-horizon');
        const horizonRadios = document.querySelectorAll('input[name=planning_horizon_choice]');

        function toggleCustom() {
            const custom = document.querySelector('input[name=planning_horizon_choice]:checked')?.value === 'custom';
            customBox.style.display = custom ? 'block' : 'none';
        }

        horizonRadios.forEach(r => r.addEventListener('change', toggleCustom));
        toggleCustom();
    </script>

</body>
</html>
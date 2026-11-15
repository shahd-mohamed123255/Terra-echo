<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Terra Echo · Farm Simulation</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#020a17;--card:#10202f;--line:rgba(255,255,255,.09);--sky:#25b8f0;--y:#ffc83d;--mut:#8fa3bd;--ok:#3ddc84;--warn:#ffc83d;--bad:#ff5d5d}
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--bg);color:#fff;font:14px/1.4 Inter,system-ui,sans-serif}
        button,select,input{font:inherit;color:inherit}button{cursor:pointer;background:none;border:0}
        .serif{font-family:'Cormorant Garamond',Georgia,serif}.sky{color:var(--sky)}.mut{color:var(--mut)}
        header{height:84px;border-bottom:1px solid var(--line);display:flex;align-items:center;padding:0 40px;gap:30px}
        header img{width:44px;height:44px;border-radius:50%}
        nav{display:flex;gap:40px;margin:0 auto 0 80px;font-size:16px;color:var(--mut)}
        nav a{color:inherit;text-decoration:none;transition:color .2s}
        nav a:hover{color:#fff}
        nav .on{color:#fff;border-bottom:2px solid var(--sky);padding-bottom:4px}
        .pill{border:1px solid var(--sky);border-radius:40px;padding:12px 28px;font-weight:500;box-shadow:0 0 22px rgba(37,184,240,.35);color:#fff;text-decoration:none}
        .wrap{max-width:1440px;margin:0 auto;padding:0 40px 40px}
        .top{display:flex;justify-content:space-between;align-items:flex-end;padding:32px 0 28px}
        .top h1{font-size:40px;font-weight:400}.top .serif{font-size:17px}
        .y{background:var(--y);color:#050816;border-radius:40px;padding:0 26px;height:46px;font:600 18px 'Cormorant Garamond',serif;box-shadow:0 8px 22px rgba(255,200,61,.28);transition:.2s;text-decoration:none}
        .y:hover{transform:translateY(-2px)}
        .card{background:var(--card);border:1px solid var(--line);border-radius:24px;padding:20px}
        .info{display:grid;grid-template-columns:repeat(5,1fr) 1.2fr;gap:20px;padding:16px 20px;border-radius:20px;margin-bottom:24px}
        .info small{display:block;color:var(--mut);font-size:12px}
        .grid{display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start}
        .main{display:grid;gap:24px;min-width:0}
        .h{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;font-size:20px}
        #map{width:100%;display:block;border-radius:14px;background:#06101c}
        .plot{cursor:pointer;transition:fill .8s}
        .tabs{display:flex;justify-content:space-between;margin-top:12px;color:var(--mut);font-size:13px}
        .tabs button{padding:5px 12px;border-radius:8px;color:inherit}.tabs .on{color:var(--sky);border:1px solid var(--sky)}
        .three{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
        .three .card{padding:16px;border-radius:20px}.three h3{font-weight:400;font-size:17px;margin-bottom:10px}
        .r{display:flex;justify-content:space-between;padding:3px 0;font-size:13px}.r span:first-child{color:var(--mut)}
        .form{display:grid;grid-template-columns:repeat(3,1fr);gap:14px 18px}
        .form label{font-size:12px;color:var(--mut);display:grid;gap:6px}
        .form select,.ro{background:#06121f;border:1px solid var(--line);border-radius:12px;height:44px;padding:0 14px;display:flex;align-items:center;color:#fff;font-size:14px}
        .act{display:flex;justify-content:flex-end;gap:12px;margin-top:18px}.act .g{border:1px solid var(--line);border-radius:30px;padding:0 20px;height:46px}
        .lib{position:sticky;top:16px;max-height:calc(100vh - 32px);overflow:auto}
        .lib h2{font-weight:400;font-size:28px}.crop{background:#14293a;border:1px solid var(--line);border-radius:16px;padding:12px;margin-top:12px}
        .crop h4{font-weight:400;font-size:17px;display:flex;justify-content:space-between}.crop h4 em{font-style:normal;font-size:11px;color:var(--sky)}
        .crop .r{font-size:12px}.crop .y{height:34px;font-size:15px;padding:0 16px;margin-top:8px;width:100%}
        .sel{display:flex;justify-content:space-between;background:#06121f;border:1px solid var(--line);border-radius:14px;padding:10px 14px;margin-top:8px}
        .bar{height:6px;border-radius:6px;background:#0b1928;overflow:hidden;margin:6px 0 3px}.bar i{display:block;height:100%;border-radius:6px;transition:width 1s}
        .two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.sc{border:1px solid var(--line);border-radius:18px;padding:16px;background:#0a1624}.sc.cur{border-color:var(--sky);background:#0f2a3d}
        .big{font-size:38px;color:var(--sky)}.verdict{border-radius:16px;padding:14px 18px;margin:12px 0;border:1px solid;font-size:15px}
        .tag{display:inline-block;background:#0d2a3b;color:var(--sky);border-radius:8px;padding:3px 10px;font-size:12px;margin:8px 6px 0 0}
        #ov{position:fixed;inset:0;background:rgba(2,10,23,.82);backdrop-filter:blur(6px);display:none;place-items:center;z-index:20}
        #ov .card{width:min(640px,92vw);border-color:var(--sky);padding:28px;box-shadow:0 0 50px rgba(37,184,240,.25)}
        .pb{height:8px;background:#0b1928;border-radius:8px;overflow:hidden;margin:12px 0}.pb i{display:block;height:100%;width:0;background:var(--sky)}
        .st{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--line);font-size:13px;color:var(--mut)}.st.on{color:#fff}.st.ok{color:#fff}
        #chat{position:fixed;right:24px;bottom:24px;width:360px;max-width:92vw;display:none;z-index:25;border-color:var(--sky)}
        #msgs{max-height:300px;overflow:auto;display:grid;gap:8px;margin:12px 0}
        .m{background:#14293a;border-radius:12px;padding:10px 12px;font-size:13px}.m.u{background:#1c4f6e;justify-self:end}
        .q{display:block;width:100%;text-align:left;border:1px solid var(--line);border-radius:8px;padding:6px 10px;margin-top:6px;color:var(--sky);font-size:12px}
        #ask{width:100%;background:#06121f;border:1px solid var(--line);border-radius:12px;height:40px;padding:0 12px;margin-top:8px}
        @media(max-width:1100px){.grid{grid-template-columns:1fr}.lib{position:static;max-height:none}.info{grid-template-columns:repeat(2,1fr)}.three,.form,.two{grid-template-columns:1fr}nav{display:none}}
        @media(max-width:1100px){header{height:auto;min-height:84px;gap:12px;padding:12px 20px;flex-wrap:wrap}header nav{display:flex;justify-content:center;flex:1;gap:14px;flex-wrap:wrap}}
        @media(max-width:600px){.wrap{padding-right:16px;padding-left:16px}.main,.three,.form,.two,#res{min-width:0}.three{width:100%}.r{gap:8px;flex-wrap:wrap}.r span:first-child{overflow-wrap:anywhere}}
    </style>
    <link rel="stylesheet" href="{{ asset('files/workspace.css') }}">
</head>
<body class="simulation-page">
<header>
    <a class="topbar-brand" href="{{ route('home') }}" aria-label="Terra Echo home">
        <img src="{{ asset('files/logo.png') }}" alt="Terra Echo">
        <span>Terra Echo</span>
    </a>
    <span class="topbar-title">Farm Simulation</span>
    <nav aria-label="Main">
        <a href="{{ route('home') }}">Dashboard</a>
        <a href="{{ route('plans', ['farm' => $farm->id]) }}">Planning</a>
        <a class="on" href="{{ route('farm.index', ['farm' => $farm->id]) }}" aria-current="page">Simulation</a>
        <a href="{{ route('about') }}">About</a>
    </nav>

    @if($farms->count() > 1)
        <div style="display:flex; align-items:center; gap:6px;">
            <select onchange="window.location.href='?farm='+this.value" style="background:#06121f; border:1px solid var(--line); border-radius:12px; color:#fff; padding:6px 12px; font-size:13px;">
                @foreach($farms as $f)
                    <option value="{{ $f->id }}" {{ $f->id === $farm->id ? 'selected' : '' }}>
                        {{ $f->city ? $f->city . ' Farm' : 'Farm #' . $f->id }} ({{ $f->area + 0 }} {{ $f->area_unit }})
                    </option>
                @endforeach
            </select>
        </div>
    @else
        <span class="mut workspace-label">{{ $farmData['name'] }}</span>
    @endif

    <button class="pill" type="button" onclick="document.getElementById('chat').style.display='block'">Abu Qerdan</button>

    <div class="language-switch" role="group" aria-label="Interface language">
        <button type="button" data-language="en" aria-pressed="true">EN</button>
        <span aria-hidden="true">|</span>
        <button type="button" data-language="ar" aria-pressed="false">AR</button>
    </div>

    @auth
        <form method="POST" action="{{ route('logout') }}" style="display:inline; margin-left:8px;">
            @csrf
            <button type="submit" class="mut" style="font-size:13px; border:1px solid var(--line); padding:6px 14px; border-radius:20px;">Logout</button>
        </form>
    @endauth
</header>

<div class="wrap">
    @if (session('status'))
        <div style="margin-top:16px; padding:12px 20px; background:rgba(61,220,132,.12); border:1px solid rgba(61,220,132,.35); border-radius:14px; color:#3ddc84; font-size:14px;">
            ✓ {{ session('status') }}
        </div>
    @endif

    <div class="top">
        <div>
            <div class="serif sky">From project history to field scenario</div>
            <h1>Farm Simulation</h1>
        </div>
        <a class="y" href="{{ route('plans', ['farm' => $farm->id]) }}" style="display:flex;align-items:center">Edit plan</a>
    </div>

    <div class="card info">
        <div>
            <small>Farm Name</small>
            <span id="farm-name">{{ $farmData['name'] }}</span>
        </div>
        <div>
            <small>Location</small>
            <span id="farm-location">{{ $farmData['location'] }}</span>
        </div>
        <div>
            <small>Total Area</small>
            <span id="farm-area-label">{{ $farmData['area_hectares'] }} ha</span>
        </div>
        <div>
            <small>Assigned Crops</small>
            <span id="farm-crop-count">{{ count($farmData['plots']) }} crops</span>
        </div>
        <div>
            <small>Historical Data Zone</small>
            <span id="farm-zone-label">Loading…</span>
        </div>
        <div class="sky">
            <small class="sky">DATA BOUNDARY</small>
            <small>Historical crop-zone records · no live field sensor</small>
        </div>
    </div>

    <div class="grid">
        <div class="main">
            <div class="card">
                <div class="h">
                    <span>Illustrative plot layout · not a satellite image</span>
                    <small class="sky" id="coord-label">{{ $farmData['coordinates'] }}</small>
                </div>
                <svg id="map" viewBox="0 0 960 400">
                    <defs>
                        <filter id="n">
                            <feTurbulence baseFrequency=".02 .05" numOctaves="4" seed="3"/>
                            <feColorMatrix values="0 0 0 0 .13  0 0 0 0 .17  0 0 0 0 .1  0 0 0 .55 0"/>
                        </filter>
                        <pattern id="rows" width="9" height="9" patternUnits="userSpaceOnUse" patternTransform="rotate(6)">
                            <rect width="9" height="3" fill="rgba(0,0,0,.28)"/>
                        </pattern>
                    </defs>
                    <rect width="960" height="400" fill="#0b1a14"/>
                    <rect width="960" height="400" filter="url(#n)"/>
                    <g id="plots"></g>
                    <g id="sats"></g>
                </svg>
                <div class="tabs" id="tabs"></div>
                <div class="mut" style="margin-top:10px;font-size:13px" id="selinfo"></div>
            </div>

            <div class="three" id="three"></div>

            <div class="card">
                <div class="serif sky" style="font-size:17px">Build your experiment</div>
                <div class="h" style="font-size:26px">
                    <span>Configure Your Simulation</span>
                    <small class="sky" id="current-sim-mode-badge">Mode A / Mode B</small>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px; padding:10px 14px; background:#06121f; border-radius:14px; border:1px solid var(--line);">
                    <span style="font-size:13px; color:var(--mut);">Active Farming Plan: <strong class="sky" id="active-plan-title">{{ $selectedCropPlan?->name ?? 'Balanced Production Plan' }}</strong></span>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <label for="plan-select" style="font-size:12px; color:var(--sky)">Select Plan:</label>
                        <select id="plan-select" style="background:#10202f; border:1px solid var(--sky); border-radius:8px; height:34px; padding:0 10px; color:#fff; font-size:13px;"></select>
                    </div>
                </div>

                <div class="form">
                    <label>Selected Plots<div class="ro" id="fp">P1, P2, P3, P4</div></label>
                    <label>Selected Crops<div class="ro" id="fc"></div></label>
                    <label>Planting Season
                        <select id="season">
                            <option value="winter" {{ $farmData['target_season'] === 'winter' ? 'selected' : '' }}>Winter</option>
                            <option value="summer" {{ $farmData['target_season'] === 'summer' ? 'selected' : '' }}>Summer</option>
                        </select>
                    </label>
                    <label>Available irrigation (mm/season)
                        <input class="ro" id="water" type="number" min="0" step="1" placeholder="Optional · leave blank if unknown">
                    </label>
                    <label>Temperature scenario
                        <select id="temp">
                            <option value="0">Historical baseline</option>
                            <option value="1">+1°C</option>
                            <option value="2">+2°C</option>
                            <option value="-1">−1°C</option>
                        </select>
                    </label>
                    <label>Rainfall scenario
                        <select id="rain">
                            <option value="0">Historical baseline</option>
                            <option value="-10">−10%</option>
                            <option value="-20">−20%</option>
                            <option value="10">+10%</option>
                        </select>
                    </label>
                    <label>Soil Conditions
                        <select id="soil">
                            <option value="clay" {{ $farmData['soil_type'] === 'clay' ? 'selected' : '' }}>Clay</option>
                            <option value="loam" {{ $farmData['soil_type'] === 'loam' ? 'selected' : '' }}>Loam</option>
                            <option value="silt" {{ $farmData['soil_type'] === 'silt' ? 'selected' : '' }}>Silt</option>
                            <option value="sandy" {{ $farmData['soil_type'] === 'sandy' ? 'selected' : '' }}>Sandy</option>
                        </select>
                    </label>
                    <label>Farm Area (ha)
                        <input class="ro" id="area" type="number" min="0.1" step="0.1" value="{{ $farmData['area_hectares'] }}">
                    </label>
                </div>

                <div class="act" style="flex-wrap:wrap; gap:10px;">
                    <button class="g" type="button" onclick="reset()">Reset Configuration</button>
                    <button class="y" type="button" onclick="run()" id="btn-run-individual">▷ RUN SIMULATION (Mode A)</button>
                    <button class="btn-mode-b" type="button" onclick="runAll()" id="btn-run-all">⚡ COMPARE ALL PLANS (Mode B)</button>
                </div>
            </div>

            <div id="res"></div>
        </div>

        <aside class="card lib">
            <h2>Crop Library</h2>
            <p class="mut" style="font-size:12px;margin:6px 0">Choose a crop supported by complete local crop-season records; each plot represents an equal illustrative area share.</p>
            <div class="h" style="margin-top:14px;font-size:17px">Selected Crops</div>
            <div id="selc"></div>
            <div class="h" style="margin-top:18px;font-size:17px">Available Crops</div>
            <div id="lib"></div>
        </aside>
    </div>
</div>

<div id="ov">
    <div class="card">
        <div class="serif sky" style="font-size:17px">From inputs to insight</div>
        <div style="font-size:26px">Simulating your farm’s potential</div>
        <div class="pb"><i id="pi"></i></div>
        <div class="h">
            <span class="mut" id="pt"></span>
            <span class="sky" style="font-size:28px" id="pp">0%</span>
        </div>
        <div id="steps"></div>
        <div class="act">
            <button class="g" type="button" onclick="cancel()">Cancel Simulation</button>
        </div>
    </div>
</div>

<div class="card" id="chat">
    <div class="h">
        <span><b>Abu Qerdan</b><br><small class="sky">Your Smart Agricultural Assistant</small></span>
        <button type="button" onclick="document.getElementById('chat').style.display='none'">✕</button>
    </div>
    <div id="msgs">
        <div class="m">Hello. I’m Abu Qerdan. I understand your farm plans, water balance, and simulation results. How can I help you today?</div>
    </div>
    <div id="qs"></div>
    <input id="ask" placeholder="Ask about your farm or simulation…">
</div>

<!-- JavaScript Bridge connecting Abu Qir Simulation to Terra Echo Backend -->
<script>
    window.terraEchoFarm = @json($farmData);
    window.terraEchoAvailablePlans = @json($availablePlans);
    window.terraEchoSelectedPlanCode = @json($selectedPlanCode);
    window.terraEchoUserPlanId = @json($userPlanId);
    window.terraEchoSelectedCropPlan = @json($selectedCropPlan);
    window.terraEchoLatestSimulation = @json($latestCompletedSimulation);
    window.terraEchoApi = {
        recordUrl: @json(route('farm.simulations.record', ['farm' => $farm->id])),
        chatUrl: @json(route('chat.message')),
        chatStartUrl: @json(route('chat.start')),
        csrfToken: @json(csrf_token())
    };
</script>
<script src="{{ asset('files/app.js') }}"></script>
<script src="{{ asset('files/i18n.js') }}"></script>
<script src="{{ asset('files/simulation.js') }}"></script>
</body>
</html>

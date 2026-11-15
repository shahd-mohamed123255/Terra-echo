
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Farm | Terra Echo</title>

    <link rel="stylesheet" href="{{ asset('css/explore.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}?v={{ filemtime(public_path('css/responsive.css')) }}">

    <!-- Leaflet CSS -->
    <link rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>

    <div class="bg"></div>
    <div class="bg-overlay"></div>

    <div class="wizard">

        <aside class="sidebar">
            <a href="{{ route('home') }}" class="sidebar-logo"><img src="{{ asset('images/logo.png') }}" alt="" width="32" height="32">Terra Echo</a>

            <div class="user-badge">Hi, {{ auth()->user()->name }}</div>

            <ol class="steps">
    <li class="active"><a href="{{ route('farm.explore') }}"><span>1</span> Location</a></li>
    <li><a href="{{ route('farm.details') }}"><span>2</span> Details</a></li>
    <li><a href="{{ route('farm.water') }}"><span>3</span> Water and Crops</a></li>
    <li><a href="{{ route('farm.summary') }}"><span>4</span> Summary</a></li>
</ol>
        </aside>

        <main class="content">

            <span class="step-pill">Step 1</span>
            <h1>Where is your farm?</h1>
            <p class="hint">
                Your location tells Terra Echo which regional climate data is relevant.
            </p>

            @if (session('status'))
                <p class="alert success">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="alert error">{{ $errors->first() }}</p>
            @endif

            <form method="POST"
                  action="{{ route('farm.location.store') }}"
                  id="location-form">

                @csrf

                <div class="selects">

                    <div class="field">
                        <label for="country">Country</label>
                        <select id="country" name="country" required></select>
                    </div>

                    <div class="field">
                        <label for="governorate">Governorate</label>
                        <select id="governorate" name="governorate" required></select>
                    </div>

                    <div class="field">
                        <label for="city">City</label>
                        <select id="city" name="city" required></select>
                    </div>

                </div>

                <div class="map-section">

                    <h2>Pinpoint Your Farm</h2>

                    <p class="map-hint">
                        Click on the map to select your farm location,
                        or drag the marker to adjust it.
                    </p>

                    <button type="button"
                            id="use-my-location"
                            class="btn btn-location">
                        Use My Location
                    </button>

                    <div id="farm-map"></div>

                    <div class="coordinates">
                        <p>
                            Latitude:
                            <span id="latitude-display">Not selected</span>
                        </p>

                        <p>
                            Longitude:
                            <span id="longitude-display">Not selected</span>
                        </p>
                    </div>

                    <input type="hidden"
                           name="latitude"
                           id="latitude"
                           value="{{ old('latitude', $farm->latitude ?? '') }}">

                    <input type="hidden"
                           name="longitude"
                           id="longitude"
                           value="{{ old('longitude', $farm->longitude ?? '') }}">

                </div>

                <p class="location-set" id="location-set"></p>

                <div class="actions">
                    <a href="{{ route('home') }}" class="btn btn-light">Home</a>

                    <button type="submit" class="btn btn-primary">
                        Continue
                    </button>
                </div>

            </form>

        </main>
    </div>

    <script>
        window.exploreData = {
            locations: @json($locations),
                        centers: @json($centers),

            selected: {
                country: @json(old('country', $farm->country ?? 'Egypt')),
                governorate: @json(old('governorate', $farm->governorate ?? 'Menoufia')),
                city: @json(old('city', $farm->city ?? null)),
            },

            coordinates: {
                latitude: @json(old('latitude', $farm->latitude ?? null)),
                longitude: @json(old('longitude', $farm->longitude ?? null)),
            }
        };
    </script>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script src="{{ asset('js/explore.js') }}"></script>

</body>
</html>

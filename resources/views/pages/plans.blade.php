<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plans | Terra Echo</title>

    <link rel="stylesheet" href="{{ asset('css/explore.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/details.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}?v={{ filemtime(public_path('css/responsive.css')) }}">
</head>
<body>

    <div class="bg"></div>
    <div class="bg-overlay"></div>

    <div class="wizard">
        <aside class="sidebar">
            <a href="{{ route('home') }}" class="sidebar-logo">Terra Echo</a>

            <div class="user-badge">Hi, {{ auth()->user()->name }}</div>

            <ol class="steps">
                <li><a href="{{ route('farm.explore') }}"><span>1</span> Location</a></li>
                <li><a href="{{ route('farm.details') }}"><span>2</span> Details</a></li>
                <li><a href="{{ route('farm.water') }}"><span>3</span> Water and Crops</a></li>
                <li><a href="{{ route('farm.summary') }}"><span>4</span> Summary</a></li>
                <li class="active"><span>5</span> Plans</li>
            </ol>
        </aside>

        <main class="content">
            <span class="step-pill">Step 5</span>
            <h1>Choose a plan</h1>
            <p class="hint">Select a plan to continue to My Farm.</p>

            @if ($errors->any())
                <p class="alert error">{{ $errors->first() }}</p>
            @endif

            <div class="d-row">
                @foreach ($plans as $plan)
                    <div class="d-block">
                        <h2>{{ $plan }}</h2>
                        <form method="POST" action="{{ route('plans.select') }}">
                            @csrf
                            <input type="hidden" name="plan" value="{{ $plan }}">
                            <button type="submit" class="btn btn-primary">Get it</button>
                        </form>
                    </div>
                @endforeach
            </div>

            <div class="actions">
                <a href="{{ route('farm.summary') }}" class="btn btn-light">Back to Summary</a>
            </div>
        </main>
    </div>

</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plans | Terra Echo</title>

    <link rel="stylesheet" href="{{ asset('css/home.css') }}">
    <link rel="stylesheet" href="{{ asset('css/plans.css') }}">
</head>
<body>
    <div class="bg" aria-hidden="true"></div>
    @include('partials.navbar')

    <main class="plans-page">
        <section class="plans-page__intro" aria-labelledby="plans-heading">
            <p class="plans-page__eyebrow">From space to soil</p>
            <h1 class="plans-page__title" id="plans-heading">Choose a Farming Plan for Your Field</h1>
            <p class="plans-page__subtitle">A practical plan to help farmers manage their crops and monitor field conditions</p>
        </section>

        @if ($errors->any())
            <p class="plans-page__error" role="alert">{{ $errors->first() }}</p>
        @endif

        <section class="plans-page__selection" aria-labelledby="plans-selection-heading">
            <h2 class="plans-page__section-label" id="plans-selection-heading">Select your plan</h2>
            <div class="plans-page__cards" id="plans-cards">
                @foreach ($plans as $plan)
                    <article class="plans-page__card plans-page__card--{{ $plan }}{{ $plan === 'balanced' ? ' plans-page__card--popular' : '' }}">
                        <div class="plans-page__card-heading">
                            <h3 class="plans-page__card-title">{{ $plan === 'water-saving' ? 'Water-Saving' : ($plan === 'balanced' ? 'Balanced' : 'High-Yield') }}</h3>
                            @if ($plan === 'balanced')
                                <span class="plans-page__badge">(Recommended)</span>
                            @endif
                        </div>
                        @if ($plan === 'balanced')
                            <p class="plans-page__card-note" aria-hidden="true"></p>
                        @endif
                        {{-- designer's feature texts will replace these placeholders --}}
                        <ul class="plans-page__features">
                            @foreach (range(1, 5) as $feature)
                                <li><span>Awaiting analysis</span></li>
                            @endforeach
                        </ul>
                        <form method="POST" action="{{ route('plans.select') }}">
                            @csrf
                            <input type="hidden" name="plan" value="{{ $plan }}">
                            <button type="submit" class="plans-page__button">Select this plan</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="plans-page__comparison" aria-labelledby="plans-comparison-heading">
            <h2 class="plans-page__section-title" id="plans-comparison-heading">Compare plans</h2>
            <div class="plans-page__table-scroll" tabindex="0" aria-label="Plan comparison table, scroll horizontally to see all columns">
                <table class="plans-page__table">
                    <thead>
                        <tr>
                            <th scope="col">Feature</th>
                            <th scope="col">Water-Saving</th>
                            <th scope="col">Balanced</th>
                            <th scope="col">High-Yield</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><th scope="row">Water usage</th><td>Awaiting analysis</td><td>Awaiting analysis</td><td>Awaiting analysis</td></tr>
                        <tr><th scope="row">Expected yield</th><td>Awaiting analysis</td><td>Awaiting analysis</td><td>Awaiting analysis</td></tr>
                        <tr><th scope="row">Risk level</th><td>Awaiting analysis</td><td>Awaiting analysis</td><td>Awaiting analysis</td></tr>
                        <tr><th scope="row">Suggested crops</th><td>Awaiting analysis</td><td>Awaiting analysis</td><td>Awaiting analysis</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="plans-page__cta" aria-labelledby="plans-cta-heading">
            <div class="plans-page__cta-content">
                <h2 class="plans-page__section-title" id="plans-cta-heading">Ready to Transform Your Farm?</h2>
                <a class="plans-page__button" href="#plans-cards">Continue</a>
            </div>
        </section>
    </main>
</body>
</html>

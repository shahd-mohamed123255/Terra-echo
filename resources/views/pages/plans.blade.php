<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farming Plans | Terra Echo</title>

    <link rel="stylesheet" href="{{ asset('css/home.css') }}">
    <link rel="stylesheet" href="{{ asset('css/plans.css') }}">
    <style>
        .plans-page__cards {
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important;
            width: min(1200px, 100%) !important;
        }
        .farm-brief {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin: -20px auto 32px;
            padding: 12px 24px;
            background: rgba(16, 32, 47, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 40px;
            width: fit-content;
            color: #c1d0df;
            font-size: 14px;
        }
        .farm-brief strong {
            color: #25b8f0;
        }
        .score-pill {
            display: inline-block;
            margin: 6px 0 10px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            background: rgba(61, 220, 132, 0.15);
            color: #3ddc84;
            border: 1px solid rgba(61, 220, 132, 0.35);
        }
        .plans-page__desc {
            font-size: 13px;
            line-height: 1.5;
            color: #8fa3bd;
            margin-bottom: 14px;
        }
        .plans-page__features li {
            font-size: 13px;
            line-height: 1.45;
            margin-bottom: 8px;
            color: #e2e8f0;
        }
        .plans-page__features li strong {
            color: #25b8f0;
        }
        .plans-page__table th, .plans-page__table td {
            font-size: 13px;
            padding: 12px 14px;
        }
    </style>
</head>
<body>
    <div class="bg" aria-hidden="true"></div>
    @include('partials.navbar')

    <main class="plans-page">
        <section class="plans-page__intro" aria-labelledby="plans-heading">
            <p class="plans-page__eyebrow">From space to soil · AI Decision Support</p>
            <h1 class="plans-page__title" id="plans-heading">Choose a Farming Plan for Your Field</h1>
            <p class="plans-page__subtitle">Scientific screening plans calculated from historical FAO crop data and soil suitability</p>
        </section>

        @if ($farm)
            <div class="farm-brief">
                <span>Farm: <strong>{{ $farm->city }} Farm</strong></span>
                <span>•</span>
                <span>Location: <strong>{{ collect([$farm->city, $farm->governorate, $farm->country])->filter()->implode(', ') }}</strong></span>
                <span>•</span>
                <span>Area: <strong>{{ $farm->area + 0 }} {{ $farm->area_unit }} (~{{ round($farm->area * 0.42, 1) }} ha)</strong></span>
                <span>•</span>
                <span>Soil: <strong>{{ $farm->soilType?->name ?? 'Clay' }}</strong></span>
            </div>
        @endif

        @if ($errors->any())
            <p class="plans-page__error" role="alert">{{ $errors->first() }}</p>
        @endif

        @if (session('status'))
            <p style="text-align: center; color: #3ddc84; margin-bottom: 20px;">{{ session('status') }}</p>
        @endif

        <section class="plans-page__selection" aria-labelledby="plans-selection-heading">
            <h2 class="plans-page__section-label" id="plans-selection-heading">Select your plan</h2>
            <div class="plans-page__cards" id="plans-cards">
                @foreach ($plans as $plan)
                    <article class="plans-page__card plans-page__card--{{ $plan['id'] }}{{ $plan['is_recommended'] ? ' plans-page__card--popular' : '' }}">
                        <div class="plans-page__card-heading">
                            <h3 class="plans-page__card-title">{{ $plan['name'] }}</h3>
                            @if (! empty($plan['badge']))
                                <span class="plans-page__badge">{{ $plan['badge'] }}</span>
                            @endif
                        </div>

                        <div class="score-pill">Suitability: {{ $plan['suitability_score'] }}/100</div>

                        <p class="plans-page__desc">{{ $plan['description'] }}</p>

                        <ul class="plans-page__features">
                            @foreach ($plan['features'] as $feature)
                                <li><span>{{ $feature }}</span></li>
                            @endforeach
                        </ul>

                        <form method="POST" action="{{ route('plans.select') }}" style="margin-top: auto;" onsubmit="try { localStorage.setItem('fieldShiftPlanId', '{{ $plan['id'] }}'); localStorage.setItem('fieldShiftPlanCode', '{{ $plan['plan_code'] }}'); } catch(e){}">
                            @csrf
                            @if ($farm)
                                <input type="hidden" name="farm_id" value="{{ $farm->id }}">
                            @endif
                            <input type="hidden" name="plan" value="{{ $plan['id'] }}">
                            <button type="submit" class="plans-page__button">
                                {{ ($userSelectedPlan === $plan['id']) ? 'Active Plan · Select Again' : 'Select this plan' }}
                            </button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="plans-page__comparison" aria-labelledby="plans-comparison-heading">
            <h2 class="plans-page__section-title" id="plans-comparison-heading">Compare agricultural strategies</h2>
            <div class="plans-page__table-scroll" tabindex="0" aria-label="Plan comparison table">
                <table class="plans-page__table">
                    <thead>
                        <tr>
                            <th scope="col">Evaluation Metric</th>
                            @foreach ($plans as $plan)
                                <th scope="col">{{ $plan['name'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th scope="row">Water Demand (mm)</th>
                            @foreach ($plans as $plan)
                                <td>{{ $plan['water_demand_mm'] }} mm</td>
                            @endforeach
                        </tr>
                        <tr>
                            <th scope="row">Farm Water Volume</th>
                            @foreach ($plans as $plan)
                                <td>{{ number_format($plan['water_demand_m3']) }} m³</td>
                            @endforeach
                        </tr>
                        <tr>
                            <th scope="row">Expected Yield</th>
                            @foreach ($plans as $plan)
                                <td>{{ $plan['yield_t_ha'] }} t/ha (~{{ $plan['total_tons'] }} tons)</td>
                            @endforeach
                        </tr>
                        <tr>
                            <th scope="row">Suitability Score</th>
                            @foreach ($plans as $plan)
                                <td><strong>{{ $plan['suitability_score'] }}/100</strong></td>
                            @endforeach
                        </tr>
                        <tr>
                            <th scope="row">Plot Allocations</th>
                            @foreach ($plans as $plan)
                                <td>{{ $plan['crop_allocation'] }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <th scope="row">Risk Profile</th>
                            @foreach ($plans as $plan)
                                <td>{{ $plan['risk_level'] }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <th scope="row">Primary Advantage</th>
                            @foreach ($plans as $plan)
                                <td>{{ $plan['pros'][0] ?? 'Optimized rotation' }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="plans-page__cta" aria-labelledby="plans-cta-heading" style="margin-top: 40px; margin-bottom: 60px;">
            <div class="plans-page__cta-content">
                <h2 class="plans-page__section-title" id="plans-cta-heading">Ready to Run Your Simulation?</h2>
                <p style="text-align: center; color: #8fa3bd; margin-bottom: 20px;">
                    Select your preferred plan above. Your farm plots will be assigned and your simulation scenario will be configured automatically.
                </p>
                <div style="text-align: center;">
                    <a class="plans-page__button" href="#plans-cards" style="display: inline-block; text-decoration: none;">Choose a Plan Above</a>
                </div>
            </div>
        </section>
    </main>
</body>
</html>

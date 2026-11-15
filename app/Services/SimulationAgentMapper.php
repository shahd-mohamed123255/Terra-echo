<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Simulation;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class SimulationAgentMapper
{
    public function toAgentRequest(Simulation $simulation, Collection $plots): array
    {
        /** @var Farm $farm */
        $farm = $simulation->farm;
        $farm->loadMissing(['soilType', 'soilReport', 'crops']);
        $settings = $simulation->settings ?? [];
        $selectedPlots = $plots->map(fn ($plot) => [
            'plot_id' => $plot->id,
            'name' => $plot->name,
            'crop' => $plot->crop?->slug,
            'area_hectares' => $this->toHectares($plot->area, strtolower((string) $farm->area_unit)),
        ])->values()->all();
        if ($selectedPlots === []
            || collect($selectedPlots)->contains(fn ($plot) => $plot['crop'] === null
                || $plot['area_hectares'] === null
                || $plot['area_hectares'] <= 0)) {
            throw new RuntimeException('Selected plot inputs are incomplete or use an unsupported area unit.');
        }
        $selectedArea = collect($selectedPlots)->sum(fn ($plot) => $plot['area_hectares'] ?? 0);
        $firstCrop = $plots->first()?->crop;
        $previousCrops = $farm->crops
            ->filter(fn ($crop) => $crop->pivot?->type === 'previous')
            ->map(fn ($crop) => $crop->slug ?: $crop->name)
            ->values()
            ->all();
        $startDate = $settings['start_date'] ?? null;
        $duration = (int) ($settings['duration_days'] ?? 0);
        $endDate = $startDate && $duration > 0
            ? date('Y-m-d', strtotime($startDate.' +'.$duration.' days'))
            : null;
        $soil = array_filter([
            'type' => $farm->soilType?->name,
            'ph' => $farm->soilReport?->ph,
            'salinity' => $farm->soilReport?->salinity,
            'nitrogen' => $farm->soilReport?->nitrogen,
            'phosphorus' => $farm->soilReport?->phosphorus,
            'potassium' => $farm->soilReport?->potassium,
        ], fn ($value) => $value !== null);
        $availableIrrigation = $settings['seasonal_irrigation_available_mm'] ?? null;

        return [
            'summary' => [
                'farm_id' => (string) $farm->id,
                'farm_name' => $farm->city ? $farm->city.' Farm' : null,
                'location' => [
                    'latitude' => $farm->latitude,
                    'longitude' => $farm->longitude,
                    'country' => $farm->country,
                    'governorate' => $farm->governorate,
                    'city' => $farm->city,
                ],
                'farm_area_hectares' => $selectedArea,
                'soil' => $soil,
                'current_crop' => $firstCrop?->slug ?: $firstCrop?->name,
                'crops' => $selectedPlots,
                'crop_history' => $previousCrops,
                'planting_date' => $startDate,
                'analysis_period' => array_filter([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]),
                'irrigation' => array_filter([
                    'source' => $farm->irrigation_source,
                    'seasonal_irrigation_available_mm' => $availableIrrigation,
                ], fn ($value) => $value !== null),
                'planning_context' => [
                    'priority' => $farm->priority,
                    'target_season' => $farm->target_season,
                    'planning_horizon' => $farm->planning_horizon,
                    'selected_season' => $settings['planting_season'] ?? null,
                    'water_scenario_label' => $settings['water_scenario'] ?? null,
                    'climate_scenario_label' => $settings['climate_scenario'] ?? null,
                ],
            ],
            'strategy' => [
                'strategy_id' => (string) ($simulation->selected_plan_id ?? $simulation->id),
                'title' => $simulation->name ?: 'Saved farm plan',
                'duration_days' => $duration,
                'plots' => $selectedPlots,
            ],
            'weather' => array_filter([
                'seasonal_et0_mm' => $settings['seasonal_et0_mm'] ?? null,
                'seasonal_precipitation_mm' => $settings['seasonal_precipitation_mm'] ?? null,
                'seasonal_irrigation_available_mm' => $availableIrrigation,
            ], fn ($value) => $value !== null && $value !== ''),
            'scenario' => [
                'rainfall_change_pct' => (float) ($settings['rainfall_change_pct'] ?? 0),
                'temperature_change_c' => (float) ($settings['temperature_change_c'] ?? 0),
                'water_availability_change_pct' => (float) ($settings['water_availability_change_pct'] ?? 0),
            ],
        ];
    }

    public function fromAgentResponse(array $response, Simulation $simulation, array $inputParameters): array
    {
        $data = $response['data'] ?? null;
        if (($response['success'] ?? false) !== true
            || ! is_array($data)
            || ! is_array($data['sequence'] ?? null)
            || ! is_array($data['assumptions'] ?? null)
            || ! is_array($data['limitations'] ?? null)) {
            throw new RuntimeException('Invalid agent response');
        }

        return [
            ...$data,
            'simulation_id' => $simulation->id,
            'user_id' => $simulation->user_id,
            'farm_id' => $simulation->farm_id,
            'selected_plan_id' => $simulation->selected_plan_id,
            'conversation_id' => $simulation->chatConversation?->agent_conversation_id,
            'status' => 'completed',
            'input_parameters' => $inputParameters,
            'tools_used' => $data['tools_used'] ?? ['calculate_water_balance'],
            'tool_results' => $data['tool_results'] ?? [],
            'data_sources' => $data['data_sources'] ?? [],
            'missing_information' => $data['missing_information'] ?? [],
            'created_at' => now()->toIso8601String(),
        ];
    }

    private function toHectares(mixed $area, string $unit): ?float
    {
        if ($area === null) {
            return null;
        }

        $factor = match ($unit) {
            'feddan' => 0.42,
            'hectare', 'hectares', 'ha' => 1.0,
            'acre', 'acres' => 0.40468564224,
            default => null,
        };

        return $factor === null ? null : (float) $area * $factor;
    }
}

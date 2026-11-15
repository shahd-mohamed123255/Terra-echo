<?php

namespace App\Services;

use App\Models\Crop;
use App\Models\Farm;
use App\Models\Simulation;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class SimulationAgentMapper
{
    private const RESPONSE_FIELDS = [
        'simulation_id',
        'status',
        'error',
        'summary',
        'overall_suitability_pct',
        'plots',
        'soil',
        'water',
        'climate',
        'scenarios',
        'recommendations',
        'rotation',
        'confidence',
        'data_sources',
        'generated_at',
    ];

    public function toAgentRequest(Simulation $simulation, Collection $plots): array
    {
        /** @var Farm $farm */
        $farm = $simulation->farm;
        $farm->loadMissing('soilType');
        $areaUnit = strtolower((string) $farm->area_unit);

        return [
            'simulation_id' => $simulation->id,
            'farm' => [
                'name' => $farm->city ? $farm->city.' Farm' : null,
                'latitude' => $farm->latitude,
                'longitude' => $farm->longitude,
                'governorate' => $farm->governorate,
                'total_area_feddan' => $this->toFeddan($farm->area, $areaUnit),
                'soil_type' => $farm->soilType?->name,
                'irrigation_source' => $farm->irrigation_source,
            ],
            'plots' => $plots->map(fn ($plot) => [
                'name' => $plot->name,
                'area_feddan' => $this->toFeddan($plot->area, $areaUnit),
                'crop' => $plot->crop?->slug,
            ])->values()->all(),
            'available_crops' => Crop::query()
                ->whereNotNull('slug')
                ->orderBy('slug')
                ->pluck('slug')
                ->all(),
            'config' => [
                'planting_season' => data_get($simulation->settings, 'planting_season'),
                'start_date' => data_get($simulation->settings, 'start_date'),
                'duration_days' => data_get($simulation->settings, 'duration_days'),
                'water_scenario' => data_get($simulation->settings, 'water_scenario'),
                'climate_scenario' => data_get($simulation->settings, 'climate_scenario'),
            ],
        ];
    }

    public function fromAgentResponse(array $response, int $simulationId): array
    {
        if ((string) ($response['simulation_id'] ?? '') !== (string) $simulationId
            || ! in_array($response['status'] ?? null, ['completed', 'failed'], true)) {
            throw new RuntimeException('Invalid agent response');
        }

        $mapped = [];
        foreach (self::RESPONSE_FIELDS as $field) {
            $mapped[$field] = array_key_exists($field, $response) ? $response[$field] : null;
        }

        $cropSlugs = [];
        $this->collectCropField($mapped['plots'], 'crop', $cropSlugs);
        $this->collectCropField(data_get($mapped, 'soil.compatibility'), 'crop', $cropSlugs);
        $this->collectCropField($mapped['rotation'], 'now', $cropSlugs);
        $this->collectCropField($mapped['rotation'], 'next', $cropSlugs);

        $cropNames = Crop::query()
            ->whereIn('slug', array_values(array_unique($cropSlugs)))
            ->pluck('name', 'slug')
            ->all();

        $mapped['plots'] = $this->mapPlotCrops($mapped['plots'], $cropNames);
        if (is_array($mapped['soil']) && array_key_exists('compatibility', $mapped['soil'])) {
            $mapped['soil']['compatibility'] = $this->mapCropRows($mapped['soil']['compatibility'], $cropNames, ['crop']);
        }
        $mapped['scenarios'] = $this->mapCropRows($mapped['scenarios'], $cropNames, []);
        $mapped['rotation'] = $this->mapCropRows($mapped['rotation'], $cropNames, ['now', 'next']);

        return $mapped;
    }

    private function toFeddan(mixed $area, string $unit): ?float
    {
        if ($area === null) {
            return null;
        }

        $factor = match ($unit) {
            'feddan' => 1.0,
            'hectare', 'hectares', 'ha' => 10000 / 4200,
            'acre', 'acres' => 4046.8564224 / 4200,
            default => null,
        };

        return $factor === null ? null : (float) $area * $factor;
    }

    private function collectCropField(mixed $rows, string $field, array &$slugs): void
    {
        if (! is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            if (is_array($row) && is_string($row[$field] ?? null)) {
                $slugs[] = $row[$field];
            }
        }
    }

    private function mapPlotCrops(mixed $plots, array $cropNames): mixed
    {
        if (! is_array($plots)) {
            return $plots;
        }

        return array_map(function ($plot) use ($cropNames) {
            if (is_array($plot) && is_string($plot['crop'] ?? null)) {
                $plot['crop'] = $cropNames[$plot['crop']] ?? $plot['crop'];
            }

            return $plot;
        }, $plots);
    }

    private function mapCropRows(mixed $rows, array $cropNames, array $fields): mixed
    {
        if (! is_array($rows)) {
            return $rows;
        }

        return array_map(function ($row) use ($cropNames, $fields) {
            if (! is_array($row)) {
                return $row;
            }
            foreach ($fields as $field) {
                if (is_string($row[$field] ?? null)) {
                    $row[$field] = $cropNames[$row[$field]] ?? $row[$field];
                }
            }

            return $row;
        }, $rows);
    }
}

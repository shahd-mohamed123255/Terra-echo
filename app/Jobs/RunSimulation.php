<?php

namespace App\Jobs;

use App\Models\Simulation;
use App\Services\AgentService;
use App\Services\SimulationAgentMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunSimulation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public int $simulationId)
    {
        $this->timeout = max(1, (int) config('services.agent.simulation_timeout', 60)) + 5;
    }

    public function handle(AgentService $agent, SimulationAgentMapper $mapper): void
    {
        if (! $this->setStageIfRunning('validating')) {
            return;
        }

        $simulation = Simulation::query()->with(['farm', 'chatConversation'])->find($this->simulationId);
        if (! $simulation || $simulation->status !== 'running') {
            return;
        }

        $plotIds = collect($simulation->plots ?? [])
            ->pluck('id')
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)
            ->unique()
            ->values();

        $plots = $simulation->farm
            ->fields()
            ->with('crop')
            ->whereIn('id', $plotIds)
            ->get();
        $savedPlots = collect($simulation->plots ?? [])->keyBy(fn ($plot) => (string) ($plot['id'] ?? ''));

        if ($plotIds->isEmpty()
            || $plots->count() !== $plotIds->count()
            || $plots->contains(function ($plot) use ($savedPlots): bool {
                $saved = $savedPlots->get((string) $plot->id);

                return $plot->crop === null
                    || ! is_array($saved)
                    || (int) ($saved['crop_id'] ?? 0) !== (int) $plot->crop_id
                    || (float) ($saved['area'] ?? 0) !== (float) $plot->area;
            })) {
            $this->failIfRunning('Selected plots are no longer available with crops.');

            return;
        }

        if (! $this->setStageIfRunning('sending')) {
            return;
        }
        if (! $this->isRunning()) {
            return;
        }
        if (! $this->setStageIfRunning('waiting')) {
            return;
        }

        try {
            $results = $agent->simulate($simulation, $plots, $mapper);
        } catch (Throwable $exception) {
            $error = $exception instanceof \RuntimeException
                ? $exception->getMessage()
                : 'Simulation failed';
            $this->failIfRunning($error);

            return;
        }

        if ($results['status'] === 'failed') {
            $this->failIfRunning($results['error'], $results);

            return;
        }

        if (! $this->isRunning() || ! $this->setStageIfRunning('saving')) {
            return;
        }

        DB::transaction(function () use ($results): void {
            $current = Simulation::query()
                ->whereKey($this->simulationId)
                ->lockForUpdate()
                ->first();

            if (! $current || $current->status !== 'running') {
                return;
            }

            $current->update([
                'results' => $results,
                'status' => 'completed',
                'error' => null,
                'finished_at' => now(),
            ]);
        });
    }

    private function setStageIfRunning(string $stage): bool
    {
        return Simulation::query()
            ->whereKey($this->simulationId)
            ->where('status', 'running')
            ->update(['stage' => $stage]) === 1;
    }

    private function isRunning(): bool
    {
        return Simulation::query()
            ->whereKey($this->simulationId)
            ->where('status', 'running')
            ->exists();
    }

    private function failIfRunning(?string $error, ?array $results = null): void
    {
        Simulation::query()
            ->whereKey($this->simulationId)
            ->where('status', 'running')
            ->update([
                'status' => 'failed',
                'error' => $error,
                'results' => $results,
                'finished_at' => now(),
            ]);
    }
}

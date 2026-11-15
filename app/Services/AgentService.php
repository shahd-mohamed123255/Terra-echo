<?php

namespace App\Services;

use App\Models\Simulation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AgentService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(trim((string) config('services.agent.url')), '/');
    }

    public function health(): array
    {
        return $this->request('GET', '/api/health', [], 5);
    }

    public function start(string $farmerId, ?string $name = null, string $language = 'ar'): array
    {
        return $this->request('POST', '/api/agent/start', array_filter([
            'farmer_id' => $farmerId,
            'name' => $name,
            'language' => $language,
        ]), 30);
    }

    public function chat(string $farmerId, string $conversationId, string $message): array
    {
        return $this->request('POST', '/api/agent/chat', [
            'farmer_id' => $farmerId,
            'conversation_id' => $conversationId,
            'message' => $message,
        ], 60);
    }

    public function history(string $conversationId): array
    {
        return $this->request('GET', '/api/agent/conversations/'.urlencode($conversationId), [], 15);
    }

    public function simulate(
        Simulation $simulation,
        Collection $plots,
        SimulationAgentMapper $mapper
    ): array {
        if ($this->baseUrl === '') {
            throw new RuntimeException('Agent not connected');
        }

        $timeout = max(1, (int) config('services.agent.simulation_timeout', 60));

        try {
            $response = Http::acceptJson()
                ->connectTimeout(min(3, $timeout))
                ->timeout($timeout)
                ->post($this->baseUrl.'/simulate', $mapper->toAgentRequest($simulation, $plots));
        } catch (ConnectionException $exception) {
            $message = strtolower($exception->getMessage());
            $reason = str_contains($message, 'timed out') || str_contains($message, 'timeout')
                ? 'Agent request timed out'
                : 'Agent not connected';

            Log::warning('Simulation agent request failed', [
                'simulation_id' => $simulation->id,
                'reason' => $reason,
            ]);

            throw new RuntimeException($reason, previous: $exception);
        } catch (\Throwable $exception) {
            Log::warning('Simulation agent request could not be completed', [
                'simulation_id' => $simulation->id,
                'reason' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Agent not connected', previous: $exception);
        }

        if (! $response->successful()) {
            $reason = 'Agent returned HTTP '.$response->status();
            Log::warning('Simulation agent returned a non-success status', [
                'simulation_id' => $simulation->id,
                'status' => $response->status(),
            ]);

            throw new RuntimeException($reason);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Invalid agent response');
        }

        return $mapper->fromAgentResponse($payload, (int) $simulation->id);
    }

    protected function request(string $method, string $path, array $data = [], int $timeout = 30): array
    {
        try {
            $options = $method === 'GET' ? [] : ['json' => $data];

            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout($timeout)
                ->send($method, $this->baseUrl.$path, $options);

            $json = $response->json();

            if ($response->successful() && ($json['success'] ?? false)) {
                return ['ok' => true, 'data' => $json['data'] ?? []];
            }

            $code = $json['error']['code'] ?? 'unknown_error';
            Log::warning('Agent error', ['path' => $path, 'status' => $response->status(), 'code' => $code]);

            return [
                'ok' => false,
                'status' => $response->status(),
                'code' => $code,
                'message' => $this->friendly($code),
            ];
        } catch (ConnectionException $e) {
            Log::error('Agent offline: '.$e->getMessage());

            return [
                'ok' => false,
                'status' => 503,
                'code' => 'agent_offline',
                'message' => $this->friendly('agent_offline'),
            ];
        } catch (\Throwable $e) {
            Log::error('Agent unexpected: '.$e->getMessage());

            return [
                'ok' => false,
                'status' => 500,
                'code' => 'unexpected',
                'message' => $this->friendly('unexpected'),
            ];
        }
    }

    protected function friendly(string $code): string
    {
        return match ($code) {
            'agent_offline' => 'أبو قردان مش متاح دلوقتي، جرب كمان شوية.',
            'validation_error' => 'الرسالة مش مقبولة، اتأكد إنها مش فاضية وأقل من 2000 حرف.',
            'forbidden' => 'مش مسموح لك بالوصول للمحادثة دي.',
            'farmer_not_found', 'conversation_not_found' => 'المحادثة مش موجودة.',
            'ai_provider_error' => 'في مشكلة مؤقتة في الـ AI، جرب تاني بعد شوية.',
            'database_unavailable', 'missing_api_key' => 'الخدمة بتتصلح دلوقتي، جرب بعدين.',
            default => 'حصلت مشكلة غير متوقعة، جرب تاني.',
        };
    }
}

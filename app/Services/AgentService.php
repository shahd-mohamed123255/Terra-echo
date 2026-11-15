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
        return $this->request('GET', '/health', [], 5);
    }

    public function start(array $summary): array
    {
        return $this->request('POST', '/api/agent/start', ['summary' => $summary], 30);
    }

    public function chat(string $conversationId, string $message, array $summary): array
    {
        return $this->request('POST', '/api/agent/chat', [
            'conversation_id' => $conversationId,
            'message' => $message,
            'summary' => $summary,
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
        $input = $mapper->toAgentRequest($simulation, $plots);

        try {
            $response = Http::acceptJson()
                ->withHeaders($this->authorizationHeaders())
                ->connectTimeout(min(3, $timeout))
                ->timeout($timeout)
                ->post($this->baseUrl.'/api/agent/simulate', $input);
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

        return $mapper->fromAgentResponse($payload, $simulation, $input);
    }

    public function analyze(array $summary): array
    {
        return $this->request('POST', '/api/agent/analyze', ['summary' => $summary], 60);
    }

    public function recommend(array $summary): array
    {
        return $this->request('POST', '/api/agent/recommend', ['summary' => $summary], 60);
    }

    public function plan(array $summary): array
    {
        return $this->request('POST', '/api/agent/plan', ['summary' => $summary], 60);
    }

    public function compare(array $summary, ?array $strategies = null, ?array $weather = null): array
    {
        return $this->request('POST', '/api/agent/compare', array_filter([
            'summary' => $summary,
            'strategies' => $strategies,
            'weather' => $weather,
        ], fn ($value) => $value !== null), 60);
    }

    public function predict(array $payload): array
    {
        return $this->request('POST', '/api/agent/predict', $payload, 60);
    }

    public function environmentRetrieve(array $summary, array $parameters = []): array
    {
        return $this->request('POST', '/api/agent/environment/retrieve', [
            'summary' => $summary,
            'parameters' => $parameters,
        ], 60);
    }

    public function knowledgeSearch(string $query, bool $external = false): array
    {
        return $this->request('POST', '/api/agent/knowledge/search', [
            'query' => $query,
            'external' => $external,
        ], 60);
    }

    protected function request(string $method, string $path, array $data = [], int $timeout = 30): array
    {
        if ($this->baseUrl === '') {
            return [
                'ok' => false,
                'status' => 503,
                'code' => 'agent_not_configured',
                'message' => $this->friendly('agent_not_configured'),
            ];
        }

        try {
            $options = $method === 'GET' ? [] : ['json' => $data];

            $response = Http::acceptJson()
                ->withHeaders($this->authorizationHeaders())
                ->connectTimeout(3)
                ->timeout($timeout)
                ->send($method, $this->baseUrl.$path, $options);

            $json = $response->json();

            if ($response->successful() && ($json['success'] ?? false) === true) {
                if (! is_array($json['data'] ?? null)) {
                    Log::warning('Agent returned a success response with an invalid data payload', [
                        'path' => $path,
                        'status' => $response->status(),
                    ]);

                    return [
                        'ok' => false,
                        'status' => 502,
                        'code' => 'invalid_agent_response',
                        'message' => $this->friendly('invalid_agent_response'),
                    ];
                }

                return ['ok' => true, 'data' => $json['data']];
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
            'agent_not_configured' => 'أبو قردان مش متصل. راجع إعداد AGENT_BASE_URL.',
            'agent_offline' => 'أبو قردان مش متاح دلوقتي، جرب كمان شوية.',
            'invalid_agent_response' => 'وصل رد غير صالح من خدمة أبو قردان.',
            'validation_error' => 'الرسالة مش مقبولة، اتأكد إنها مش فاضية وأقل من 2000 حرف.',
            'forbidden' => 'مش مسموح لك بالوصول للمحادثة دي.',
            'farmer_not_found', 'conversation_not_found' => 'المحادثة مش موجودة.',
            'ai_provider_error' => 'في مشكلة مؤقتة في الـ AI، جرب تاني بعد شوية.',
            'database_unavailable', 'missing_api_key' => 'الخدمة بتتصلح دلوقتي، جرب بعدين.',
            default => 'حصلت مشكلة غير متوقعة، جرب تاني.',
        };
    }

    protected function authorizationHeaders(): array
    {
        $token = trim((string) config('services.agent.token'));

        return $token === '' ? [] : ['Authorization' => 'Bearer '.$token];
    }
}

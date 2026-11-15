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
        $timeout = max(1, (int) config('services.agent.simulation_timeout', 60));
        $input = $mapper->toAgentRequest($simulation, $plots);

        if ($this->baseUrl === '') {
            if (config('services.agent.local_fallback', false)) {
                $local = $this->runLocalRunner('simulate', $input);
                if ($local['ok']) {
                    return $mapper->fromAgentResponse(['success' => true, 'data' => $local['data']], $simulation, $input);
                }
            }

            throw new RuntimeException('Agent not connected');
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders($this->authorizationHeaders())
                ->connectTimeout(min(3, $timeout))
                ->timeout($timeout)
                ->post($this->baseUrl.'/api/agent/simulate', $input);
        } catch (ConnectionException $exception) {
            if (config('services.agent.local_fallback', false)) {
                $local = $this->runLocalRunner('simulate', $input);
                if ($local['ok']) {
                    return $mapper->fromAgentResponse(['success' => true, 'data' => $local['data']], $simulation, $input);
                }
            }

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
            if (config('services.agent.local_fallback', false)) {
                $local = $this->runLocalRunner('simulate', $input);
                if ($local['ok']) {
                    return $mapper->fromAgentResponse(['success' => true, 'data' => $local['data']], $simulation, $input);
                }
            }

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
            if (config('services.agent.local_fallback', false)) {
                $fallback = $this->runLocalFallback($method, $path, $data);
                if ($fallback !== null) {
                    return $fallback;
                }
            }

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

            if (config('services.agent.local_fallback', false)) {
                $fallback = $this->runLocalFallback($method, $path, $data);
                if ($fallback !== null) {
                    return $fallback;
                }
            }

            return [
                'ok' => false,
                'status' => 503,
                'code' => 'agent_offline',
                'message' => $this->friendly('agent_offline'),
            ];
        } catch (\Throwable $e) {
            Log::error('Agent unexpected: '.$e->getMessage());

            if (config('services.agent.local_fallback', false)) {
                $fallback = $this->runLocalFallback($method, $path, $data);
                if ($fallback !== null) {
                    return $fallback;
                }
            }

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

    public function runLocalRunner(string $action, array $data = []): array
    {
        $runnerScript = base_path('../abu qerdan/runner.py');
        if (! file_exists($runnerScript)) {
            $runnerScript = dirname(base_path()).'/abu qerdan/runner.py';
        }
        if (! file_exists($runnerScript)) {
            return [
                'ok' => false,
                'status' => 503,
                'code' => 'agent_offline',
                'message' => 'Agent runner script not found.',
            ];
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'terra_echo_');
        file_put_contents($tmpFile, json_encode($data, JSON_UNESCAPED_UNICODE));

        $process = new \Symfony\Component\Process\Process([
            'python',
            $runnerScript,
            $action,
            '@'.$tmpFile,
        ], dirname($runnerScript));

        $process->setTimeout(60);
        $process->run();

        if (file_exists($tmpFile)) {
            @unlink($tmpFile);
        }

        if (! $process->isSuccessful()) {
            Log::error('Local Agent runner error', [
                'action' => $action,
                'error' => $process->getErrorOutput(),
            ]);

            return [
                'ok' => false,
                'status' => 500,
                'code' => 'runner_error',
                'message' => 'Agent execution failed.',
            ];
        }

        $output = trim($process->getOutput());
        $json = json_decode($output, true);

        if (is_array($json) && ($json['success'] ?? false) === true && isset($json['data'])) {
            return ['ok' => true, 'data' => $json['data']];
        }

        return [
            'ok' => false,
            'status' => 500,
            'code' => $json['error']['code'] ?? 'invalid_agent_response',
            'message' => $json['error']['message'] ?? 'Invalid agent response',
        ];
    }

    protected function runLocalFallback(string $method, string $path, array $data): ?array
    {
        $action = match (true) {
            $path === '/health' => 'health',
            $path === '/api/agent/start' => 'start',
            $path === '/api/agent/chat' => 'chat',
            str_starts_with($path, '/api/agent/conversations/') => 'history',
            $path === '/api/agent/plan' => 'plan',
            $path === '/api/agent/analyze' => 'analyze',
            $path === '/api/agent/recommend' => 'recommend',
            $path === '/api/agent/compare' => 'compare',
            $path === '/api/agent/predict' => 'predict',
            $path === '/api/agent/knowledge/search' => 'knowledge',
            default => null,
        };

        if ($action === null) {
            return null;
        }

        if ($action === 'history') {
            $parts = explode('/', $path);
            $convId = urldecode(end($parts));
            $data = ['conversation_id' => $convId];
        }

        $res = $this->runLocalRunner($action, $data);
        return $res['ok'] ? $res : null;
    }
}

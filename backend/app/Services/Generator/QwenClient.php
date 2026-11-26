<?php

namespace App\Services\Generator;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Talks to the Qwen model running on this machine through an
 * OpenAI-compatible server (LM Studio, Ollama, llama.cpp, vLLM…).
 * Only loopback / private addresses are accepted, so generation never
 * leaves the computer for a hosted AI API.
 */
class QwenClient
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.local_ai.base_url', 'http://127.0.0.1:1234/v1'), '/');
    }

    public function model(): string
    {
        return (string) config('services.local_ai.model', 'qwen2.5-coder-7b-instruct');
    }

    public function isLocal(): bool
    {
        $host = strtolower((string) parse_url($this->baseUrl(), PHP_URL_HOST));
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]', 'host.docker.internal'], true)) {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP)
            && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /** @return array{online: bool, model: string, base_url: string, models: array<int, string>, error: ?string} */
    public function health(): array
    {
        $status = ['online' => false, 'model' => $this->model(), 'base_url' => $this->baseUrl(), 'models' => [], 'error' => null];

        if (! $this->isLocal()) {
            $status['error'] = 'LOCAL_AI_BASE_URL must point to this computer (localhost / 127.0.0.1).';

            return $status;
        }

        try {
            $response = Http::connectTimeout(2)->timeout(4)->get($this->baseUrl().'/models');
            if ($response->successful()) {
                $status['online'] = true;
                $status['models'] = collect($response->json('data', []))->pluck('id')->filter()->values()->all();
            } else {
                $status['error'] = 'The local model server answered HTTP '.$response->status().'.';
            }
        } catch (Throwable) {
            $status['error'] = 'Nothing is listening at '.$this->baseUrl().'. Start LM Studio (or Ollama) and load the Qwen model.';
        }

        return $status;
    }

    /** One chat completion; returns the assistant text. */
    public function chat(string $system, string $user, int $maxTokens, float $temperature = 0.6): string
    {
        if (! $this->isLocal()) {
            throw new RuntimeException('LOCAL_AI_BASE_URL must point to a model running on this computer.');
        }

        @set_time_limit(0);

        try {
            $response = Http::connectTimeout(5)
                ->timeout((int) config('services.local_ai.timeout', 600))
                ->post($this->baseUrl().'/chat/completions', [
                    'model' => $this->model(),
                    'temperature' => $temperature,
                    'top_p' => 0.9,
                    'max_tokens' => $maxTokens,
                    'stream' => false,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ])->throw();
        } catch (Throwable $e) {
            throw new RuntimeException('Qwen could not be reached at '.$this->baseUrl().': '.$e->getMessage(), 0, $e);
        }

        $text = (string) $response->json('choices.0.message.content', '');
        // Reasoning builds of Qwen wrap their thinking in <think> tags.
        $text = trim(preg_replace('/<think>.*?<\/think>/s', '', $text) ?? $text);

        if ($text === '') {
            throw new RuntimeException('Qwen returned an empty answer.');
        }

        return $text;
    }
}

<?php

namespace App\Services\Ai;

use App\Support\OpenAiRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks an AI model for a JSON answer. Same provider order and settings as the Listing Quality Score audit:
 * Gemini first, then OpenAI, then Claude - the first one that answers wins.
 */
class AiJsonClient
{
    /**
     * @return array{0: ?array<string, mixed>, 1: string, 2: string} [parsed JSON, error, provider that answered]
     */
    public function ask(string $system, string $user, int $maxTokens = 1800): array
    {
        $errors = [];
        foreach (['gemini' => 'askGemini', 'openai' => 'askOpenAi', 'claude' => 'askClaude'] as $provider => $method) {
            [$text, $error] = $this->{$method}($system, $user, $maxTokens);
            if ($text === null) {
                $errors[] = $error;

                continue;
            }
            $parsed = $this->parseJson($text);
            if (is_array($parsed)) {
                return [$parsed, '', $provider];
            }
            $errors[] = ucfirst($provider).' did not return valid JSON.';
        }

        return [null, implode(' | ', array_filter($errors)) ?: 'AI request failed.', ''];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function askGemini(string $system, string $user, int $maxTokens): array
    {
        $key = '';
        foreach ([
            config('services.gemini.key'),
            config('services.raw_images_ai.gemini_key'),
            $_ENV['GEMINI_API_KEY'] ?? null,
            $_SERVER['GEMINI_API_KEY'] ?? null,
            getenv('GEMINI_API_KEY') ?: null,
        ] as $candidate) {
            if (trim((string) $candidate) !== '') {
                $key = trim((string) $candidate);
                break;
            }
        }
        if ($key === '') {
            return [null, 'GEMINI_API_KEY is not configured.'];
        }

        $models = [];
        foreach ([
            (string) config('services.gemini.text_model', 'gemini-3.5-flash-lite'),
            'gemini-3.5-flash-lite',
            'gemini-3.5-flash',
            'gemini-flash-lite-latest',
            'gemini-flash-latest',
        ] as $name) {
            $name = trim($name);
            if (str_starts_with($name, 'models/')) {
                $name = substr($name, 7);
            }
            if ($name !== '' && ! in_array($name, $models, true)) {
                $models[] = $name;
            }
        }

        $lastError = 'Gemini request failed.';
        foreach ($models as $model) {
            try {
                $response = Http::timeout(90)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent?key='.urlencode($key), [
                        'contents' => [['role' => 'user', 'parts' => [['text' => $system."\n\n".$user]]]],
                        'generationConfig' => [
                            'temperature' => 0.3,
                            'maxOutputTokens' => $maxTokens,
                            'responseMimeType' => 'application/json',
                        ],
                    ]);
            } catch (\Throwable $e) {
                $lastError = 'Gemini error: '.$e->getMessage();

                continue;
            }
            if (! $response->successful()) {
                $msg = data_get($response->json(), 'error.message') ?? ('HTTP '.$response->status());
                $lastError = 'Gemini error: '.(is_string($msg) ? $msg : 'Request failed');
                Log::warning('AI JSON client: Gemini error', ['model' => $model, 'status' => $response->status()]);

                continue;
            }
            $text = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
            if ($text === '') {
                $lastError = 'Gemini returned an empty response.';

                continue;
            }

            return [$text, ''];
        }

        return [null, $lastError];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function askOpenAi(string $system, string $user, int $maxTokens): array
    {
        $headers = OpenAiRequest::authHeaders();
        if ($headers === []) {
            return [null, 'OPENAI_API_KEY is not configured.'];
        }
        try {
            $response = Http::timeout(90)->withHeaders($headers)->post('https://api.openai.com/v1/chat/completions', [
                'model' => (string) config('services.openai.title_master_stack_model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'max_tokens' => $maxTokens,
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);
        } catch (\Throwable $e) {
            return [null, 'OpenAI error: '.$e->getMessage()];
        }
        if (! $response->successful()) {
            $msg = $response->json('error.message') ?? ('HTTP '.$response->status());
            Log::warning('AI JSON client: OpenAI error', ['status' => $response->status()]);

            return [null, 'OpenAI error: '.(is_string($msg) ? $msg : 'Request failed')];
        }
        $content = data_get($response->json(), 'choices.0.message.content');
        $text = is_array($content)
            ? trim(implode("\n", array_map(static fn ($p) => is_array($p) ? (string) ($p['text'] ?? '') : (string) $p, $content)))
            : trim((string) ($content ?? ''));

        return $text === '' ? [null, 'OpenAI returned an empty response.'] : [$text, ''];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function askClaude(string $system, string $user, int $maxTokens): array
    {
        $apiKey = trim((string) (config('services.anthropic.key') ?: config('services.claude.key') ?: ''));
        if ($apiKey === '') {
            return [null, 'ANTHROPIC_API_KEY is not configured.'];
        }
        try {
            $response = Http::timeout(90)->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => (string) config('services.anthropic.version', '2023-06-01'),
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                'max_tokens' => $maxTokens,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $user]],
            ]);
        } catch (\Throwable $e) {
            return [null, 'Claude error: '.$e->getMessage()];
        }
        if (! $response->successful()) {
            $msg = $response->json('error.message') ?? ('HTTP '.$response->status());
            Log::warning('AI JSON client: Claude error', ['status' => $response->status()]);

            return [null, 'Claude error: '.(is_string($msg) ? $msg : 'Request failed')];
        }
        $text = trim((string) data_get($response->json(), 'content.0.text', ''));

        return $text === '' ? [null, 'Claude returned an empty response.'] : [$text, ''];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJson(string $text): ?array
    {
        $text = trim($text);
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if (preg_match('/\{.*\}/s', $text, $m) === 1) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}

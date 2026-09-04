<?php

namespace App\Support;

use App\Models\GoogleYoutubeVideoAiAudit as YoutubeVideoAiAuditRow;
use App\Models\GoogleYoutubeVideoAiPrompt;
use App\Support\OpenAiRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Gemini-first YouTube video audit. Claude / OpenAI are text fallbacks.
 */
final class GoogleYoutubeVideoAiAudit
{
    public static function defaultPrompt(): string
    {
        return <<<'TXT'
You are a senior YouTube performance-creative auditor for paid video ads.

Score EVERY checkpoint below as pass, fail, or na.
For each fail, give:
- error: short label of what is wrong
- reason: why this likely causes the campaign to fail (skip, no click, no sale)
- direction: the exact creative or targeting change to make next

Checkpoints (use these keys verbatim):
- hook_3s: Hook in the first 3 seconds
- product_visible: Product is clearly on screen
- value_prop: Benefit / value proposition is stated
- offer_price: Offer or price is shown when relevant
- cta_clear: Call to action is spoken and/or on-screen
- brand_recall: Brand or logo is visible
- audio_quality: Audio / voiceover is clear
- length_fit: Length fits the format
- mobile_safe: Text and product stay in a mobile-safe frame
- thumbnail: Opening frame / thumbnail matches the ad
- landing_match: Landing page matches the ad promise
- targeting_fit: Audience / targeting matches the creative
- end_card: End card or final CTA is present
- performance: Spend vs sales / ACOS is acceptable

If a YouTube / video URL is provided, analyze the actual video.
If not, infer from campaign name + metrics and mark video-only items na unless the name makes the issue obvious.

Return ONLY valid JSON (no markdown) with this shape:
{
  "summary": "2-4 sentences on the main failure reason and the first fix",
  "checks": [
    {
      "key": "hook_3s",
      "verdict": "pass|fail|na",
      "error": "",
      "reason": "",
      "direction": ""
    }
  ]
}
Include every checkpoint key exactly once.
TXT;
    }

    public static function currentPrompt(): string
    {
        if (! Schema::hasTable('google_youtube_video_ai_prompts')) {
            return self::defaultPrompt();
        }
        $row = GoogleYoutubeVideoAiPrompt::query()->orderByDesc('id')->first();
        $prompt = $row !== null ? trim((string) $row->prompt) : '';

        return $prompt !== '' ? $prompt : self::defaultPrompt();
    }

    /**
     * @return list<array{id:int, prompt:string, saved_by_name:?string, created_at:?string}>
     */
    public static function promptHistory(int $limit = 20): array
    {
        if (! Schema::hasTable('google_youtube_video_ai_prompts')) {
            return [];
        }

        return GoogleYoutubeVideoAiPrompt::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'prompt', 'saved_by_name', 'created_at'])
            ->map(static fn (GoogleYoutubeVideoAiPrompt $r) => [
                'id' => (int) $r->id,
                'prompt' => (string) $r->prompt,
                'saved_by_name' => $r->saved_by_name,
                'created_at' => optional($r->created_at)->format('Y-m-d H:i'),
            ])
            ->all();
    }

    public static function persistPrompt(string $prompt, ?int $userId, ?string $userName): void
    {
        if (! Schema::hasTable('google_youtube_video_ai_prompts')) {
            throw new \RuntimeException('Table google_youtube_video_ai_prompts does not exist. Run migrations.');
        }
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new \InvalidArgumentException('Prompt cannot be empty.');
        }
        $latest = GoogleYoutubeVideoAiPrompt::query()->orderByDesc('id')->first();
        if ($latest !== null && trim((string) $latest->prompt) === $prompt) {
            return;
        }
        GoogleYoutubeVideoAiPrompt::query()->create([
            'prompt' => $prompt,
            'saved_by' => $userId,
            'saved_by_name' => $userName,
        ]);
    }

    /**
     * @return array<string, bool>
     */
    public static function filledByCampaignId(): array
    {
        if (! Schema::hasTable('google_youtube_video_ai_audits')) {
            return [];
        }
        $ids = YoutubeVideoAiAuditRow::query()
            ->selectRaw('MAX(id) as max_id')
            ->groupBy('campaign_id')
            ->pluck('max_id');
        $map = [];
        foreach (
            YoutubeVideoAiAuditRow::query()
                ->whereIn('id', $ids)
                ->get(['campaign_id', 'result']) as $row
        ) {
            $map[(string) $row->campaign_id] = is_array($row->result) && $row->result !== [];
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{result: array<string, mixed>, model: string, fail_count: int}
     */
    public static function analyze(string $prompt, string $videoUrl, array $context): array
    {
        $payload = self::buildUserPayload($prompt, $videoUrl, $context);
        $errors = [];

        $geminiKey = self::geminiApiKey();
        if ($geminiKey !== '') {
            return self::fromGemini($geminiKey, $payload, $videoUrl);
        }

        $claudeKey = trim((string) (config('services.anthropic.key') ?: config('services.claude.key') ?: ''));
        if ($claudeKey !== '') {
            try {
                return self::fromClaude($claudeKey, $payload);
            } catch (\Throwable $e) {
                $errors[] = 'Claude: '.$e->getMessage();
                Log::warning('YouTube AI audit Claude failed', ['error' => $e->getMessage()]);
            }
        }

        $openaiKey = trim((string) (config('services.openai.key') ?: ''));
        if ($openaiKey !== '') {
            try {
                return self::fromOpenAi($payload);
            } catch (\Throwable $e) {
                $errors[] = 'OpenAI: '.$e->getMessage();
                Log::warning('YouTube AI audit OpenAI failed', ['error' => $e->getMessage()]);
            }
        }

        if ($errors === []) {
            throw new \RuntimeException('AI is not configured. Set GEMINI_API_KEY (best for video), or CLAUDE_API_KEY / OPENAI_API_KEY.');
        }

        throw new \RuntimeException(implode(' | ', $errors));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function buildUserPayload(string $prompt, string $videoUrl, array $context): string
    {
        $checks = [];
        foreach (GoogleYoutubeVideoAuditChecklist::items() as $item) {
            $checks[] = $item['key'].' — '.$item['label'];
        }

        $metrics = [
            'campaign_id' => $context['campaign_id'] ?? '',
            'campaign_name' => $context['campaign_name'] ?? '',
            'spend_lt' => $context['spend_lt'] ?? null,
            'sales_lt' => $context['sales_lt'] ?? null,
            'sold_lt' => $context['sold_lt'] ?? null,
            'acos_lt' => $context['acos_lt'] ?? null,
            'views_lt' => $context['views_lt'] ?? null,
            'spend_l30' => $context['spend'] ?? null,
            'sales_l30' => $context['ad_sales_L30'] ?? null,
            'acos_l30' => $context['acos_l30'] ?? null,
            'video_url' => $videoUrl,
        ];

        return $prompt."\n\nCampaign metrics (JSON):\n"
            .json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n"
            ."Checkpoint keys:\n- ".implode("\n- ", $checks)."\n";
    }

    private static function geminiApiKey(): string
    {
        foreach ([
            config('services.gemini.key'),
            config('services.raw_images_ai.gemini_key'),
            $_ENV['GEMINI_API_KEY'] ?? null,
            $_SERVER['GEMINI_API_KEY'] ?? null,
            getenv('GEMINI_API_KEY') ?: null,
        ] as $candidate) {
            $key = trim((string) $candidate);
            if ($key !== '') {
                return $key;
            }
        }

        return '';
    }

    /**
     * @return array{result: array<string, mixed>, model: string, fail_count: int}
     */
    private static function fromGemini(string $key, string $payload, string $videoUrl): array
    {
        $lastError = 'Gemini request failed.';
        foreach (self::geminiModelsToTry($key) as $model) {
            try {
                return self::fromGeminiModel($key, $model, $payload, $videoUrl);
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning('YouTube AI audit Gemini model failed', [
                    'model' => $model,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new \RuntimeException($lastError);
    }

    /**
     * @return list<string>
     */
    private static function geminiModelsToTry(string $key): array
    {
        $preferred = trim((string) config('services.gemini.video_model', 'gemini-flash-latest'));
        $models = [];
        foreach ([
            $preferred,
            'gemini-flash-latest',
            'gemini-3.5-flash',
            'gemini-3.6-flash',
            'gemini-3.1-flash-lite',
        ] as $name) {
            $name = self::normalizeGeminiModel($name);
            if ($name !== '' && ! in_array($name, $models, true)) {
                $models[] = $name;
            }
        }

        foreach (self::listGeminiGenerateModels($key) as $name) {
            if (! in_array($name, $models, true)) {
                $models[] = $name;
            }
        }

        return $models;
    }

    private static function normalizeGeminiModel(string $name): string
    {
        $name = trim($name);
        if (str_starts_with($name, 'models/')) {
            $name = substr($name, 7);
        }

        return $name;
    }

    /**
     * @return list<string>
     */
    private static function listGeminiGenerateModels(string $key): array
    {
        try {
            $response = Http::timeout(20)
                ->get('https://generativelanguage.googleapis.com/v1beta/models', [
                    'key' => $key,
                ]);
            if (! $response->successful()) {
                return [];
            }
            $out = [];
            foreach ((array) $response->json('models') as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $methods = $row['supportedGenerationMethods'] ?? [];
                if (! in_array('generateContent', (array) $methods, true)) {
                    continue;
                }
                $name = self::normalizeGeminiModel((string) ($row['name'] ?? ''));
                if ($name === '' || preg_match('/image|tts|transcribe|computer-use|lyria|robotics|deep-research|omni/i', $name)) {
                    continue;
                }
                $out[] = $name;
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return array{result: array<string, mixed>, model: string, fail_count: int}
     */
    private static function fromGeminiModel(string $key, string $model, string $payload, string $videoUrl): array
    {
        $attempts = [false];
        if (self::isYoutubeUrl($videoUrl)) {
            array_unshift($attempts, true);
        }
        $lastError = 'Gemini request failed.';
        foreach ($attempts as $attachVideo) {
            try {
                return self::postGeminiGenerate($key, $model, $payload, $attachVideo ? $videoUrl : '');
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new \RuntimeException($lastError);
    }

    /**
     * @return array{result: array<string, mixed>, model: string, fail_count: int}
     */
    private static function postGeminiGenerate(string $key, string $model, string $payload, string $videoUrl): array
    {
        $parts = [['text' => $payload]];
        if (self::isYoutubeUrl($videoUrl)) {
            $parts[] = [
                'file_data' => [
                    'file_uri' => $videoUrl,
                    'mime_type' => 'video/mp4',
                ],
            ];
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            .$model.':generateContent?key='.urlencode($key);

        $response = Http::timeout(180)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'contents' => [[
                    'role' => 'user',
                    'parts' => $parts,
                ]],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status().' '.$response->body());
        }
        $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');

        return self::parseModelJson($text, $model);
    }

    /**
     * @return array{result: array<string, mixed>, model: string, fail_count: int}
     */
    private static function fromClaude(string $key, string $payload): array
    {
        $model = (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001');
        $version = (string) config('services.anthropic.version', '2023-06-01');
        $response = Http::timeout(120)
            ->withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => $version,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 4096,
                'temperature' => 0.2,
                'messages' => [[
                    'role' => 'user',
                    'content' => $payload,
                ]],
            ]);
        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status().' '.$response->body());
        }
        $text = (string) data_get($response->json(), 'content.0.text', '');

        return self::parseModelJson($text, $model);
    }

    /**
     * @return array{result: array<string, mixed>, model: string, fail_count: int}
     */
    private static function fromOpenAi(string $payload): array
    {
        $model = (string) config('services.openai.title_master_stack_model', 'gpt-4o-mini');
        $response = Http::timeout(120)
            ->withHeaders(OpenAiRequest::authHeaders())
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Return only valid JSON for a YouTube video ad audit.'],
                    ['role' => 'user', 'content' => $payload],
                ],
            ]);
        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status().' '.$response->body());
        }
        $text = (string) data_get($response->json(), 'choices.0.message.content', '');

        return self::parseModelJson($text, $model);
    }

    /**
     * @return array{result: array<string, mixed>, model: string, fail_count: int}
     */
    private static function parseModelJson(string $text, string $model): array
    {
        $text = trim($text);
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
            $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        }
        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('AI did not return valid JSON.');
        }

        $byKey = [];
        foreach (($decoded['checks'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = (string) ($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $verdict = strtolower(trim((string) ($row['verdict'] ?? '')));
            if (! in_array($verdict, ['pass', 'fail', 'na'], true)) {
                $verdict = 'na';
            }
            $byKey[$key] = [
                'key' => $key,
                'verdict' => $verdict,
                'error' => trim((string) ($row['error'] ?? '')),
                'reason' => trim((string) ($row['reason'] ?? '')),
                'direction' => trim((string) ($row['direction'] ?? '')),
            ];
        }

        $checks = [];
        $fail = 0;
        foreach (GoogleYoutubeVideoAuditChecklist::items() as $item) {
            $row = $byKey[$item['key']] ?? [
                'key' => $item['key'],
                'verdict' => 'na',
                'error' => '',
                'reason' => '',
                'direction' => '',
            ];
            $row['label'] = $item['label'];
            if ($row['verdict'] === 'fail') {
                $fail++;
            }
            $checks[] = $row;
        }

        return [
            'result' => [
                'summary' => trim((string) ($decoded['summary'] ?? '')),
                'checks' => $checks,
            ],
            'model' => $model,
            'fail_count' => $fail,
        ];
    }

    private static function isYoutubeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        return (bool) preg_match('~^https?://(www\.)?(youtube\.com|youtu\.be)/~i', $url);
    }
}

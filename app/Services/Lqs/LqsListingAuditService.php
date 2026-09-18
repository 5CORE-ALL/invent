<?php

namespace App\Services\Lqs;

use App\Models\EbayMetric;
use App\Models\LqsMarketplaceAuditPrompt;
use App\Models\LqsMarketplaceScore;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Support\Lqs\LqsMarketplaceCatalog;
use App\Support\OpenAiRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LqsListingAuditService
{
    public function defaultPrompt(string $slug): string
    {
        $label = LqsMarketplaceCatalog::find($slug)['label'] ?? strtoupper($slug);

        if ($slug === 'ebay') {
            return <<<'PROMPT'
You are an eBay listing quality auditor for a US marketplace seller. Score this listing from 0 to 100 and tell a content editor exactly what to fix.

Score against eBay requirements:
1. Title — up to 80 characters; brand + product type + 2–4 high-intent keywords first; no keyword stuffing, all caps, or promotional claims.
2. Item specifics — Brand, MPN, Type, Color, Material, Compatible Brand/Model, and other category-required fields must be filled and accurate.
3. Description / bullets — scannable benefits, condition, what’s included, dimensions, and compatibility; no HTML clutter or policy-violating claims.
4. Images — enough angles, clean background, first image is the product only.
5. Price and offer — price present and reasonable vs recent sales; shipping/returns mentioned if known.
6. Discoverability — title + specifics should match how buyers search on eBay.
7. Compliance — no replica language, medical claims, or misleading condition.

Use the listing data provided after this prompt. If a field is missing, treat it as a deduction and say so.

Return ONLY valid JSON with:
- lqs_percent: number 0–100
- findings: short audit of what is weak or missing (plain text, 3–6 sentences)
- suggestions: concrete rewrite steps for the content editor (plain text, numbered)
PROMPT;
        }

        return "You are a {$label} listing quality auditor. Score the listing 0–100 and return JSON with lqs_percent, findings, and suggestions for the content editor.";
    }

    public function getOrCreatePrompt(string $slug): array
    {
        $existing = LqsMarketplaceAuditPrompt::query()->where('marketplace', $slug)->first();
        if ($existing) {
            return [
                'prompt' => $existing->prompt,
                'is_default' => false,
            ];
        }

        $prompt = $this->defaultPrompt($slug);
        if (Schema::hasTable('lqs_marketplace_audit_prompts')) {
            LqsMarketplaceAuditPrompt::create([
                'marketplace' => $slug,
                'prompt' => $prompt,
            ]);
        }

        return [
            'prompt' => $prompt,
            'is_default' => true,
        ];
    }

    public function savePrompt(string $slug, string $prompt): string
    {
        $prompt = trim($prompt);
        $row = LqsMarketplaceAuditPrompt::updateOrCreate(
            ['marketplace' => $slug],
            ['prompt' => $prompt]
        );

        return $row->prompt;
    }

    public function run(string $slug, string $sku, ?string $prompt = null): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            throw new \InvalidArgumentException('SKU is required.');
        }

        $saved = $this->getOrCreatePrompt($slug);
        $prompt = trim((string) ($prompt ?: $saved['prompt']));
        if ($prompt === '') {
            $prompt = $this->defaultPrompt($slug);
        }
        $this->savePrompt($slug, $prompt);

        $listing = $this->listingContext($slug, $sku);
        $userMessage = $prompt."\n\nLISTING DATA:\n".json_encode($listing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        [$text, $error] = $this->requestGemini($userMessage, $sku);
        if ($text === null) {
            [$text, $error] = $this->requestOpenAi($userMessage, $sku);
        }
        if ($text === null) {
            $claudeKey = trim((string) (config('services.anthropic.key') ?: config('services.claude.key') ?: ''));
            if ($claudeKey !== '') {
                [$text, $error] = $this->requestClaude($userMessage, $sku, $claudeKey);
            }
        }
        if ($text === null) {
            throw new \RuntimeException($error ?: 'AI audit failed.');
        }

        $parsed = $this->parseJson($text);
        if (! is_array($parsed)) {
            throw new \RuntimeException('AI did not return valid JSON.');
        }

        $percent = $this->normalizePercent($parsed['lqs_percent'] ?? $parsed['lqs'] ?? null);
        $findings = trim((string) ($parsed['findings'] ?? ''));
        $suggestions = trim((string) ($parsed['suggestions'] ?? ''));

        $score = LqsMarketplaceScore::updateOrCreate(
            ['marketplace' => $slug, 'sku' => strtoupper($sku)],
            [
                'lqs' => $percent,
                'listing_id' => $listing['listing_id'] ?? null,
                'audit_findings' => $findings !== '' ? $findings : null,
                'audit_suggestions' => $suggestions !== '' ? $suggestions : null,
            ]
        );

        return [
            'sku' => $sku,
            'lqs' => (float) $score->lqs,
            'findings' => $score->audit_findings,
            'suggestions' => $score->audit_suggestions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listingContext(string $slug, string $sku): array
    {
        $normalize = static fn ($value) => strtoupper(str_replace("\u{00a0}", ' ', trim((string) $value)));
        $key = $normalize($sku);

        $product = ProductMaster::query()
            ->whereRaw('UPPER(TRIM(REPLACE(sku, CHAR(160), " "))) = ?', [$key])
            ->first();

        $shopify = ShopifySku::query()
            ->whereRaw('UPPER(TRIM(REPLACE(sku, CHAR(160), " "))) = ?', [$key])
            ->first();

        $context = [
            'channel' => $slug,
            'sku' => $product->sku ?? $sku,
            'parent' => $product->parent ?? null,
            'category' => $product->category ?? null,
            'inventory' => $shopify->inv ?? null,
            'shopify_sold_l30' => $shopify->quantity ?? null,
            'image' => $shopify->image_src ?? null,
            'listing_id' => null,
            'title' => null,
            'url' => null,
            'price' => null,
            'l30' => null,
            'sessions' => null,
            'bullet_points' => null,
            'listing_status' => null,
        ];

        if ($slug === 'ebay' && Schema::hasTable('ebay_metrics')) {
            $metric = EbayMetric::query()
                ->whereRaw('UPPER(TRIM(REPLACE(sku, CHAR(160), " "))) = ?', [$key])
                ->orderByDesc('id')
                ->first();
            if ($metric) {
                $context['listing_id'] = $metric->item_id;
                $context['title'] = $metric->ebay_title;
                $context['url'] = $metric->ebay_link;
                $context['price'] = $metric->ebay_price;
                $context['l30'] = $metric->ebay_l30;
                $context['sessions'] = $metric->views;
                $context['bullet_points'] = $metric->bullet_points;
                $context['listing_status'] = $metric->listing_status;
            }
        }

        return $context;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function geminiApiKey(): string
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

    private function requestGemini(string $prompt, string $sku): array
    {
        $key = $this->geminiApiKey();
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
        $instruction = "You output only valid JSON for listing quality audits. No markdown. Keys: lqs_percent (0-100 number), findings (string), suggestions (string).\n\n";

        foreach ($models as $model) {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
                .$model.':generateContent?key='.urlencode($key);

            $response = Http::timeout(90)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $instruction.$prompt]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'maxOutputTokens' => 1400,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (! $response->successful()) {
                $bodyJson = $response->json();
                $errorMsg = data_get($bodyJson, 'error.message') ?? ('HTTP '.$response->status());
                $lastError = 'Gemini error: '.(is_string($errorMsg) ? $errorMsg : 'Request failed');
                Log::warning('LQS audit Gemini error', [
                    'sku' => $sku,
                    'model' => $model,
                    'status' => $response->status(),
                ]);
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
    private function requestOpenAi(string $prompt, string $sku): array
    {
        $headers = OpenAiRequest::authHeaders();
        if ($headers === []) {
            return [null, 'OPENAI_API_KEY is not configured.'];
        }

        $model = (string) config('services.openai.title_master_stack_model', 'gpt-4o-mini');
        $response = Http::timeout(90)
            ->withHeaders($headers)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You output only valid JSON for listing quality audits. No markdown. Keys: lqs_percent (0-100 number), findings (string), suggestions (string).',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens' => 1400,
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);

        if (! $response->successful()) {
            $bodyJson = $response->json();
            $errorMsg = $bodyJson['error']['message'] ?? ('HTTP '.$response->status());
            Log::warning('LQS audit OpenAI error', [
                'sku' => $sku,
                'status' => $response->status(),
                'error' => $bodyJson['error'] ?? mb_substr($response->body(), 0, 500),
            ]);

            return [null, 'OpenAI error: '.(is_string($errorMsg) ? $errorMsg : 'Request failed')];
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        $text = $this->contentToText($content);
        if ($text === '') {
            return [null, 'OpenAI returned an empty response.'];
        }

        return [$text, ''];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function requestClaude(string $prompt, string $sku, string $apiKey): array
    {
        $model = (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001');
        $version = (string) config('services.anthropic.version', '2023-06-01');

        $response = Http::timeout(90)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => $version,
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 1400,
                'system' => 'You output only valid JSON for listing quality audits. No markdown. Keys: lqs_percent (0-100 number), findings (string), suggestions (string).',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            $bodyJson = $response->json();
            $errorMsg = $bodyJson['error']['message'] ?? ('HTTP '.$response->status());
            Log::warning('LQS audit Claude error', [
                'sku' => $sku,
                'status' => $response->status(),
            ]);

            return [null, 'Claude error: '.(is_string($errorMsg) ? $errorMsg : 'Request failed')];
        }

        $text = trim((string) data_get($response->json(), 'content.0.text', ''));
        if ($text === '') {
            return [null, 'Claude returned an empty response.'];
        }

        return [$text, ''];
    }

    private function contentToText($content): string
    {
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_string($part)) {
                    $parts[] = $part;
                } elseif (is_array($part) && isset($part['text'])) {
                    $parts[] = (string) $part['text'];
                }
            }

            return trim(implode("\n", $parts));
        }

        return trim((string) ($content ?? ''));
    }

    private function parseJson(string $text): ?array
    {
        $text = trim($text);
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $text, $match)) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function normalizePercent($value): float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return 0;
        }

        $number = (float) $value;
        if ($number <= 10) {
            $number *= 10;
        }

        return max(0, min(100, round($number, 1)));
    }
}

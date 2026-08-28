<?php

namespace App\Services;

use App\Support\OpenAiRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * High-quality product image generation used only by the Raw Images page.
 * Gemini edits the hero into a raw-shoot still. Claude can refine the prompt.
 */
class RawImagesAiImageService
{
    /**
     * @param  list<string>  $logoBytes
     */
    public function generateFromHeroBytes(string $heroBytes, string $prompt, string $sku, array $logoBytes = []): string
    {
        $analysis = $this->analyzeHeroWithClaude($heroBytes, $sku);
        $fullPrompt = $this->buildGenerationPrompt($prompt, $analysis, $sku, $logoBytes !== []);

        $gemini = $this->geminiKey();
        if ($gemini !== null) {
            $bytes = $this->editWithGemini($heroBytes, $fullPrompt, $gemini, $logoBytes);
            if (is_string($bytes) && $bytes !== '') {
                return $this->resizeToSquareJpeg($bytes, 2000);
            }
        }

        $openai = $this->openaiKey();
        if ($openai !== null) {
            $png = $this->bytesToPng($heroBytes);
            $tmpPng = tempnam(sys_get_temp_dir(), 'ri_hero_');
            if ($tmpPng === false) {
                throw new \RuntimeException('Could not create a temp file.');
            }
            file_put_contents($tmpPng, $png);
            try {
                $bytes = $this->editWithGptImage($tmpPng, $fullPrompt, $openai)
                    ?? $this->generateWithDalle3Hd($fullPrompt, $openai);
                if (is_string($bytes) && $bytes !== '') {
                    return $this->resizeToSquareJpeg($bytes, 2000);
                }
            } finally {
                @unlink($tmpPng);
            }
        }

        if ($gemini === null && $openai === null) {
            throw new \RuntimeException('Set GEMINI_API_KEY in .env for this page.');
        }

        return $this->composeStudioRawShoot($heroBytes, $analysis);
    }

    /**
     * @return array<string, string>
     */
    private function analyzeHeroWithClaude(string $heroBytes, string $sku): array
    {
        $apiKey = trim((string) (config('services.anthropic.key') ?: config('services.claude.key') ?: ''));
        if ($apiKey === '') {
            return [];
        }

        $jpeg = $this->bytesToJpeg($heroBytes, 1200);
        if ($jpeg === '') {
            return [];
        }

        $model = (string) config('services.anthropic.hero_vision_model', 'claude-sonnet-4-5-20250929');
        $version = (string) config('services.anthropic.version', '2023-06-01');

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => $version,
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 400,
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => 'image/jpeg',
                                    'data' => base64_encode($jpeg),
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => 'This is the product hero photo for SKU '.$sku.'. '
                                    .'Describe it for a photoreal raw-studio reshoot. Return ONLY JSON: '
                                    .'{"product":"","finish":"","is_dark":true,"bg_hex":"#F4F1EA","background":"","lighting":"","keep":""}. '
                                    .'is_dark true if the product is dark (then use a light bg_hex) or false (use a slightly darker cream/gray bg_hex). '
                                    .'keep = what must stay identical (shape, ports, logo, color). No markdown.',
                            ],
                        ],
                    ]],
                ]);

            if (! $response->successful()) {
                Log::warning('Raw images AI Claude vision failed', ['status' => $response->status()]);

                return [];
            }

            $text = trim((string) data_get($response->json(), 'content.0.text', ''));
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text) ?? $text;
            $decoded = json_decode($text, true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            Log::warning('Raw images AI Claude vision error', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function buildGenerationPrompt(string $userPrompt, array $analysis, string $sku, bool $hasLogos = false): string
    {
        $parts = [trim($userPrompt)];
        $product = trim((string) ($analysis['product'] ?? ''));
        $finish = trim((string) ($analysis['finish'] ?? ''));
        $keep = trim((string) ($analysis['keep'] ?? ''));
        $lighting = trim((string) ($analysis['lighting'] ?? ''));
        $background = trim((string) ($analysis['background'] ?? ''));
        $isDark = filter_var($analysis['is_dark'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($product !== '') {
            $parts[] = 'Product: '.$product.($finish !== '' ? ' ('.$finish.')' : '').'.';
        }
        if ($keep !== '') {
            $parts[] = 'Keep identical: '.$keep.'.';
        }
        if ($isDark === true) {
            $parts[] = 'Product is dark — use a light, natural studio background.';
        } elseif ($isDark === false) {
            $parts[] = 'Product is light — use a slightly darker or cream natural studio background.';
        } elseif ($background !== '') {
            $parts[] = 'Background: '.$background.'.';
        }
        if ($lighting !== '') {
            $parts[] = 'Lighting: '.$lighting.'.';
        }

        if ($hasLogos) {
            $parts[] = 'After the product photo are logo reference images uploaded by the user. '
                .'Place those exact logos on the generated image (same artwork, colors, and lettering — do not invent a different logo). '
                .'Follow the user prompt for logo size and position. Keep the product photoreal.';
        }

        $parts[] = 'Keep the same product from the reference image (SKU '.$sku.'). '
            .'Photoreal raw-shoot, square 2000x2000, natural lighting, no watermark, no mannequin hands, no extra products.'
            .($hasLogos ? '' : ' No text, no logo overlay.');

        return trim(implode("\n", array_filter($parts)));
    }

    /**
     * Claude cannot emit pixels. Build a 2000x2000 studio still from the real hero
     * using Claude's light/dark + background guidance.
     *
     * @param  array<string, mixed>  $analysis
     */
    private function composeStudioRawShoot(string $heroBytes, array $analysis): string
    {
        $src = @imagecreatefromstring($heroBytes);
        if (! $src) {
            throw new \RuntimeException('Hero image could not be read.');
        }

        $size = 2000;
        $isDark = filter_var($analysis['is_dark'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isDark === null) {
            $isDark = $this->averageLuma($src) < 90;
        }

        $hex = strtoupper(trim((string) ($analysis['bg_hex'] ?? '')));
        if (! preg_match('/^#?[0-9A-F]{6}$/', $hex)) {
            $hex = $isDark ? '#F3F0E8' : '#8A847C';
        }
        $rgb = $this->hexToRgb($hex);

        $dst = imagecreatetruecolor($size, $size);
        $this->fillStudioBackground($dst, $size, $rgb, $isDark);

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(($size * 0.78) / max($w, 1), ($size * 0.78) / max($h, 1));
        $nw = (int) max(1, round($w * $scale));
        $nh = (int) max(1, round($h * $scale));
        $x = (int) (($size - $nw) / 2);
        $y = (int) (($size - $nh) / 2) - (int) round($size * 0.02);

        $shadow = imagecolorallocatealpha($dst, 20, 18, 16, 100);
        imagefilledellipse($dst, (int) ($size / 2), $y + $nh - 8, (int) ($nw * 0.72), (int) max(24, $nh * 0.08), $shadow);

        imagecopyresampled($dst, $src, $x, $y, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        ob_start();
        imagejpeg($dst, null, 92);
        $out = (string) ob_get_clean();
        imagedestroy($dst);
        if ($out === '') {
            throw new \RuntimeException('Could not write the studio image.');
        }

        return $out;
    }

    /**
     * @param  array{r:int,g:int,b:int}  $rgb
     */
    private function fillStudioBackground(\GdImage $dst, int $size, array $rgb, bool $lightBg): void
    {
        $base = $rgb;
        $edge = $lightBg
            ? [
                'r' => max(0, $base['r'] - 22),
                'g' => max(0, $base['g'] - 22),
                'b' => max(0, $base['b'] - 20),
            ]
            : [
                'r' => min(255, $base['r'] + 28),
                'g' => min(255, $base['g'] + 26),
                'b' => min(255, $base['b'] + 24),
            ];

        for ($y = 0; $y < $size; $y++) {
            $t = $y / max($size - 1, 1);
            $r = (int) round($base['r'] * (1 - $t) + $edge['r'] * $t);
            $g = (int) round($base['g'] * (1 - $t) + $edge['g'] * $t);
            $b = (int) round($base['b'] * (1 - $t) + $edge['b'] * $t);
            $color = imagecolorallocate($dst, $r, $g, $b);
            imageline($dst, 0, $y, $size, $y, $color);
        }
    }

    /**
     * @return array{r:int,g:int,b:int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            'r' => (int) hexdec(substr($hex, 0, 2)),
            'g' => (int) hexdec(substr($hex, 2, 2)),
            'b' => (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private function averageLuma(\GdImage $src): float
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $step = max(1, (int) floor(min($w, $h) / 24));
        $sum = 0.0;
        $n = 0;
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $rgb = imagecolorat($src, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $sum += 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
                $n++;
            }
        }

        return $n > 0 ? $sum / $n : 128.0;
    }

    private function geminiKey(): ?string
    {
        $key = trim((string) (config('services.raw_images_ai.gemini_key') ?: config('services.gemini.key') ?: ''));

        return $key !== '' ? $key : null;
    }

    private function editWithGemini(string $heroBytes, string $prompt, string $key, array $logoBytes = []): ?string
    {
        $jpeg = $this->bytesToJpeg($heroBytes, 1600);
        if ($jpeg === '') {
            return null;
        }
        $logoJpegs = [];
        foreach ($logoBytes as $logo) {
            if (! is_string($logo) || $logo === '') {
                continue;
            }
            $logoJpeg = $this->bytesToJpeg($logo, 800);
            if ($logoJpeg !== '') {
                $logoJpegs[] = $logoJpeg;
            }
        }

        $model = (string) config('services.raw_images_ai.gemini_model', 'gemini-3.1-flash-image-preview');
        $tried = [];

        try {
            $bytes = $this->geminiGenerateContent($key, $model, $jpeg, $prompt, $logoJpegs);
            if (is_string($bytes) && $bytes !== '') {
                return $bytes;
            }
        } catch (\Throwable $e) {
            $tried[$model] = true;
            $this->throwIfGeminiQuota($e->getMessage());
            if (preg_match("/Did you mean '([^']+)'/", $e->getMessage(), $m)) {
                $suggested = trim($m[1]);
                if ($suggested !== '' && ! isset($tried[$suggested])) {
                    try {
                        $bytes = $this->geminiGenerateContent($key, $suggested, $jpeg, $prompt, $logoJpegs);
                        if (is_string($bytes) && $bytes !== '') {
                            return $bytes;
                        }
                    } catch (\Throwable $retry) {
                        $this->throwIfGeminiQuota($retry->getMessage());
                        throw new \RuntimeException($this->friendlyGeminiError($retry->getMessage()));
                    }
                }
            }

            throw new \RuntimeException($this->friendlyGeminiError($e->getMessage()));
        }

        return null;
    }

    private function throwIfGeminiQuota(string $message): void
    {
        if (! preg_match('/quota|resource.?exhausted|rate.?limit|free_tier/i', $message)) {
            return;
        }

        throw new \RuntimeException($this->friendlyGeminiError($message));
    }

    private function friendlyGeminiError(string $message): string
    {
        if (preg_match('/free_tier|limit:\s*0/i', $message)) {
            return 'Gemini blocked image generation on this API key (free-tier image quota is 0). '
                .'Enable billing in Google AI Studio, create a new Gemini key, and set GEMINI_API_KEY.';
        }

        if (preg_match('/quota|resource.?exhausted|rate.?limit/i', $message)) {
            $wait = '';
            if (preg_match('/retry in\s+([0-9.]+)\s*s/i', $message, $m)) {
                $wait = ' Wait about '.max(1, (int) ceil((float) $m[1])).' seconds, then try again.';
            }

            return 'Gemini rate limit hit.'.$wait
                .' Image models need a billing-enabled Gemini key in GEMINI_API_KEY.';
        }

        $first = trim((string) preg_replace('/\s+/', ' ', $message));
        if (strlen($first) > 240) {
            $first = substr($first, 0, 237).'...';
        }

        return $first !== '' ? $first : 'Gemini image request failed.';
    }

    /**
     * @param  list<string>  $extraJpegs
     */
    private function geminiGenerateContent(string $key, string $model, string $jpeg, string $prompt, array $extraJpegs = []): ?string
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent';
        $generationConfig = [
            'responseModalities' => ['TEXT', 'IMAGE'],
            'imageConfig' => [
                'aspectRatio' => '1:1',
            ],
        ];
        if (str_contains($model, '3.1') || str_contains($model, '3-pro') || str_contains($model, '3.x')) {
            $generationConfig['imageConfig']['imageSize'] = '2K';
        }

        $parts = [
            ['text' => $prompt],
            [
                'inline_data' => [
                    'mime_type' => 'image/jpeg',
                    'data' => base64_encode($jpeg),
                ],
            ],
        ];
        foreach (array_values($extraJpegs) as $i => $logoJpeg) {
            $n = $i + 1;
            $parts[] = ['text' => 'Logo '.$n.' — use this exact logo artwork. Do not redraw or invent a different logo.'];
            $parts[] = [
                'inline_data' => [
                    'mime_type' => 'image/jpeg',
                    'data' => base64_encode($logoJpeg),
                ],
            ];
        }

        $response = Http::timeout(180)
            ->withHeaders([
                'x-goog-api-key' => $key,
                'Content-Type' => 'application/json',
            ])
            ->post($url, [
                'contents' => [[
                    'role' => 'user',
                    'parts' => $parts,
                ]],
                'generationConfig' => $generationConfig,
            ]);

        if (! $response->successful()) {
            $msg = trim((string) data_get($response->json(), 'error.message', ''));
            throw new \RuntimeException($msg !== '' ? $msg : 'Gemini generateContent failed (HTTP '.$response->status().').');
        }

        return $this->bytesFromGeminiResponse($response->json());
    }

    private function geminiInteractions(string $key, string $model, string $jpeg, string $prompt): ?string
    {
        $response = Http::timeout(180)
            ->withHeaders([
                'x-goog-api-key' => $key,
                'Content-Type' => 'application/json',
            ])
            ->post('https://generativelanguage.googleapis.com/v1beta/interactions', [
                'model' => $model,
                'input' => [
                    ['type' => 'text', 'text' => $prompt],
                    [
                        'type' => 'image',
                        'mime_type' => 'image/jpeg',
                        'data' => base64_encode($jpeg),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            $msg = trim((string) data_get($response->json(), 'error.message', ''));
            throw new \RuntimeException($msg !== '' ? $msg : 'Gemini interactions failed (HTTP '.$response->status().').');
        }

        return $this->bytesFromGeminiResponse($response->json());
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function bytesFromGeminiResponse(?array $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        $paths = [
            'output_image.data',
            'outputs.0.image.data',
            'outputs.0.content.0.inline_data.data',
            'outputs.0.content.0.inlineData.data',
            'candidates.0.content.parts',
        ];
        foreach ($paths as $path) {
            $value = data_get($json, $path);
            if (is_string($value) && $value !== '') {
                $bytes = base64_decode($value, true);
                if (is_string($bytes) && $this->looksLikeImageBytes($bytes)) {
                    return $bytes;
                }
            }
            if (is_array($value)) {
                $fromParts = $this->bytesFromGeminiParts($value);
                if ($fromParts !== null) {
                    return $fromParts;
                }
            }
        }

        return $this->bytesFromGeminiParts($json);
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $node
     */
    private function bytesFromGeminiParts(array $node): ?string
    {
        $b64 = (string) ($node['inlineData']['data'] ?? $node['inline_data']['data'] ?? '');
        if ($b64 === '' && isset($node['data']) && is_string($node['data']) && strlen($node['data']) > 200) {
            $b64 = $node['data'];
        }
        if ($b64 !== '') {
            $bytes = base64_decode($b64, true);
            if (is_string($bytes) && $this->looksLikeImageBytes($bytes)) {
                return $bytes;
            }
        }

        foreach ($node as $value) {
            if (! is_array($value)) {
                continue;
            }
            $found = $this->bytesFromGeminiParts($value);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function looksLikeImageBytes(string $bytes): bool
    {
        if (strlen($bytes) < 32) {
            return false;
        }

        return str_starts_with($bytes, "\xFF\xD8\xFF")
            || str_starts_with($bytes, "\x89PNG")
            || str_starts_with($bytes, 'RIFF')
            || str_starts_with($bytes, 'GIF8');
    }

    private function editWithGptImage(string $pngPath, string $prompt, string $key): ?string
    {
        $handle = fopen($pngPath, 'r');
        if ($handle === false) {
            return null;
        }

        $model = (string) config('services.raw_images_ai.image_model', 'gpt-image-1');
        $quality = (string) config('services.raw_images_ai.image_quality', 'high');
        $size = (string) config('services.raw_images_ai.image_size', '1024x1024');

        $response = Http::timeout(180)
            ->withHeaders($this->openaiMultipartHeaders($key))
            ->attach('image', $handle, 'hero.png')
            ->post('https://api.openai.com/v1/images/edits', [
                'model' => $model,
                'prompt' => $prompt,
                'n' => '1',
                'size' => $size,
                'quality' => $quality,
                'input_fidelity' => 'high',
            ]);

        if (! $response->successful()) {
            Log::warning('Raw images AI gpt-image-1 failed', [
                'status' => $response->status(),
                'error' => data_get($response->json(), 'error.message'),
            ]);

            return null;
        }

        return $this->bytesFromImageResponse($response->json());
    }

    private function generateWithDalle3Hd(string $prompt, string $key): ?string
    {
        $response = Http::timeout(180)
            ->withHeaders($this->openaiJsonHeaders($key))
            ->post('https://api.openai.com/v1/images/generations', [
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
                'quality' => 'hd',
                'response_format' => 'b64_json',
            ]);

        if (! $response->successful()) {
            $apiMsg = trim((string) data_get($response->json(), 'error.message', ''));
            throw new \RuntimeException($apiMsg !== '' ? $apiMsg : 'AI image request failed (HTTP '.$response->status().').');
        }

        return $this->bytesFromImageResponse($response->json());
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function bytesFromImageResponse(?array $json): ?string
    {
        $b64 = (string) data_get($json, 'data.0.b64_json', '');
        if ($b64 !== '') {
            $bytes = base64_decode($b64, true);

            return is_string($bytes) && $bytes !== '' ? $bytes : null;
        }

        $url = trim((string) data_get($json, 'data.0.url', ''));
        if ($url === '') {
            return null;
        }

        $file = Http::timeout(60)->get($url);
        if (! $file->successful() || strlen($file->body()) < 32) {
            return null;
        }

        return $file->body();
    }

    /**
     * @return array<string, string>
     */
    private function openaiMultipartHeaders(string $key): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$key,
        ];
        $org = OpenAiRequest::normalizeApiKey(config('services.openai.organization'));
        if (is_string($org) && $org !== '') {
            $headers['OpenAI-Organization'] = $org;
        }
        $project = OpenAiRequest::normalizeApiKey(config('services.openai.project'));
        if (is_string($project) && $project !== '') {
            $headers['OpenAI-Project'] = $project;
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private function openaiJsonHeaders(string $key): array
    {
        $headers = $this->openaiMultipartHeaders($key);
        $headers['Content-Type'] = 'application/json';

        return $headers;
    }

    private function openaiKey(): ?string
    {
        $key = config('services.raw_images_ai.openai_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    private function bytesToPng(string $bytes): string
    {
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            throw new \RuntimeException('Hero image could not be read.');
        }
        ob_start();
        imagepng($src);
        $png = (string) ob_get_clean();
        imagedestroy($src);
        if ($png === '') {
            throw new \RuntimeException('Could not convert the hero image.');
        }

        return $png;
    }

    private function bytesToJpeg(string $bytes, int $maxEdge = 1200): string
    {
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return '';
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $maxEdge / max($w, $h, 1));
        $nw = (int) max(1, round($w * $scale));
        $nh = (int) max(1, round($h * $scale));
        if ($nw !== $w || $nh !== $h) {
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }
        ob_start();
        imagejpeg($src, null, 85);
        $out = (string) ob_get_clean();
        imagedestroy($src);

        return $out;
    }

    private function resizeToSquareJpeg(string $bytes, int $size = 2000): string
    {
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return $bytes;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $dst = imagecreatetruecolor($size, $size);
        $bg = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $size, $size, $bg);
        $scale = min($size / max($w, 1), $size / max($h, 1));
        $nw = (int) max(1, round($w * $scale));
        $nh = (int) max(1, round($h * $scale));
        $x = (int) (($size - $nw) / 2);
        $y = (int) (($size - $nh) / 2);
        imagecopyresampled($dst, $src, $x, $y, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagejpeg($dst, null, 92);
        $out = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return $out !== '' ? $out : $bytes;
    }
}

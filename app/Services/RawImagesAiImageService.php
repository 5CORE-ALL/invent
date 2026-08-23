<?php

namespace App\Services;

use App\Support\OpenAiRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * High-quality product image generation used only by the Raw Images page.
 * Claude studies the hero photo; OpenAI gpt-image-1 edits it into a raw-shoot still.
 */
class RawImagesAiImageService
{
    public function generateFromHeroBytes(string $heroBytes, string $prompt, string $sku): string
    {
        $key = $this->openaiKey();
        if ($key === null) {
            throw new \RuntimeException('Set RAW_IMAGES_OPENAI_API_KEY or OPENAI_API_KEY in .env for this page.');
        }

        $png = $this->bytesToPng($heroBytes);
        $analysis = $this->analyzeHeroWithClaude($heroBytes, $sku);
        $fullPrompt = $this->buildGenerationPrompt($prompt, $analysis, $sku);

        $tmpPng = tempnam(sys_get_temp_dir(), 'ri_hero_');
        if ($tmpPng === false) {
            throw new \RuntimeException('Could not create a temp file.');
        }
        file_put_contents($tmpPng, $png);

        try {
            $bytes = $this->editWithGptImage($tmpPng, $fullPrompt, $key)
                ?? $this->generateWithDalle3Hd($fullPrompt, $key);

            if (! is_string($bytes) || $bytes === '') {
                throw new \RuntimeException('The image API did not return a file.');
            }

            return $this->resizeToSquareJpeg($bytes, 2000);
        } finally {
            @unlink($tmpPng);
        }
    }

    /**
     * @return array<string, string>
     */
    private function analyzeHeroWithClaude(string $heroBytes, string $sku): array
    {
        $apiKey = trim((string) (config('services.anthropic.key') ?: ''));
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
                                    .'{"product":"","finish":"","is_dark":true,"background":"","lighting":"","keep":""}. '
                                    .'is_dark true if the product is dark (then recommend a light background) or false (darker/cream background). '
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
    private function buildGenerationPrompt(string $userPrompt, array $analysis, string $sku): string
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

        $parts[] = 'Keep the same product from the reference image (SKU '.$sku.'). '
            .'Photoreal raw-shoot, square 2000x2000, natural lighting, no text, no watermark, no logo overlay, no mannequin hands, no extra products.';

        return trim(implode("\n", array_filter($parts)));
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

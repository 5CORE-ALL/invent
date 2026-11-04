<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GeminiNineRatioService
{
    public function changeToNineSixteen(string $sourceUrl, string $sku): string
    {
        $key = trim((string) config('services.gemini.key'));
        if ($key === '') {
            throw new RuntimeException('GEMINI_API_KEY is not set.');
        }

        $temp = $this->downloadSource($sourceUrl);
        try {
            $file = $this->uploadToGemini($key, $temp['path'], $temp['mime']);
            $this->waitUntilActive($key, $file['name']);
            $video = $this->generateNineSixteen($key, $file['uri']);
            $relative = 'nine-ration/'.preg_replace('/[^A-Za-z0-9._-]+/', '-', $sku).'-'.time().'.mp4';
            Storage::disk('public')->put($relative, $video);

            return asset('storage/'.$relative);
        } finally {
            if (is_file($temp['path'])) {
                @unlink($temp['path']);
            }
        }
    }

    /**
     * @return array{path: string, mime: string}
     */
    private function downloadSource(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('The 9:16 cell needs a direct http or https video link.');
        }
        if (str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) {
            throw new RuntimeException('YouTube page links cannot be converted. Use a direct video file link.');
        }

        $path = tempnam(sys_get_temp_dir(), 'nine-ratio-');
        if ($path === false) {
            throw new RuntimeException('Could not create a temp file for the video.');
        }

        $response = Http::timeout(60)
            ->withOptions(['sink' => $path])
            ->get($url);

        if (! $response->successful() || ! is_file($path) || filesize($path) < 1024) {
            @unlink($path);
            throw new RuntimeException('Could not download the video from that link.');
        }
        if (filesize($path) > 30 * 1024 * 1024) {
            @unlink($path);
            throw new RuntimeException('Video is larger than 30 MB. Use a shorter clip.');
        }

        $mime = strtolower((string) $response->header('Content-Type'));
        $mime = trim(explode(';', $mime)[0]);
        if (! str_starts_with($mime, 'video/')) {
            $mime = 'video/mp4';
        }

        return ['path' => $path, 'mime' => $mime];
    }

    /**
     * @return array{name: string, uri: string}
     */
    private function uploadToGemini(string $key, string $path, string $mime): array
    {
        $bytes = filesize($path);
        $start = Http::timeout(60)
            ->withHeaders([
                'x-goog-api-key' => $key,
                'X-Goog-Upload-Protocol' => 'resumable',
                'X-Goog-Upload-Command' => 'start',
                'X-Goog-Upload-Header-Content-Length' => (string) $bytes,
                'X-Goog-Upload-Header-Content-Type' => $mime,
                'Content-Type' => 'application/json',
            ])
            ->post('https://generativelanguage.googleapis.com/upload/v1beta/files', [
                'file' => ['display_name' => basename($path)],
            ]);

        $uploadUrl = $start->header('x-goog-upload-url');
        if (! $start->successful() || ! $uploadUrl) {
            throw new RuntimeException($this->apiError('Gemini could not start the video upload.', $start->json()));
        }

        $upload = Http::timeout(120)
            ->withHeaders([
                'X-Goog-Upload-Offset' => '0',
                'X-Goog-Upload-Command' => 'upload, finalize',
                'Content-Type' => $mime,
            ])
            ->withBody((string) file_get_contents($path), $mime)
            ->post($uploadUrl);

        $file = $upload->json('file') ?? [];
        if (! $upload->successful() || empty($file['name']) || empty($file['uri'])) {
            throw new RuntimeException($this->apiError('Gemini could not accept the video.', $upload->json()));
        }

        return ['name' => $file['name'], 'uri' => $file['uri']];
    }

    private function waitUntilActive(string $key, string $name): void
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/'.ltrim($name, '/');
        for ($i = 0; $i < 12; $i++) {
            $file = Http::timeout(30)->withHeaders(['x-goog-api-key' => $key])->get($url)->json();
            $state = strtoupper((string) ($file['state'] ?? ''));
            if ($state === 'ACTIVE') {
                return;
            }
            if ($state === 'FAILED') {
                throw new RuntimeException('Gemini could not process that video file.');
            }
            sleep(5);
        }

        throw new RuntimeException('Gemini is still processing the video. Try again in a moment.');
    }

    private function generateNineSixteen(string $key, string $fileUri): string
    {
        $response = Http::timeout(180)->withHeaders([
            'x-goog-api-key' => $key,
            'Content-Type' => 'application/json',
        ])->post('https://generativelanguage.googleapis.com/v1beta/interactions', [
            'model' => 'gemini-omni-1.1-flash',
            'input' => [
                ['type' => 'video', 'uri' => $fileUri],
                ['type' => 'text', 'text' => 'Recreate this product video as a vertical 9:16 shoppable video. Keep the same product, action, and message. Frame it for a phone screen.'],
            ],
            'response_format' => [
                'type' => 'video',
                'aspect_ratio' => '9:16',
                'duration' => '8s',
            ],
        ]);

        $body = $response->json() ?? [];
        if (! $response->successful()) {
            throw new RuntimeException($this->apiError('Gemini could not change the video ratio.', $body));
        }

        $interaction = $body;
        $id = (string) ($body['id'] ?? '');
        $status = strtolower((string) ($body['status'] ?? 'completed'));
        for ($i = 0; $id !== '' && in_array($status, ['in_progress', 'processing', 'queued'], true) && $i < 24; $i++) {
            sleep(5);
            $interaction = Http::timeout(30)
                ->withHeaders(['x-goog-api-key' => $key])
                ->get('https://generativelanguage.googleapis.com/v1beta/interactions/'.$id)
                ->json() ?? [];
            $status = strtolower((string) ($interaction['status'] ?? ''));
        }
        if (in_array($status, ['failed', 'error', 'cancelled'], true)) {
            throw new RuntimeException($this->apiError('Gemini could not change the video ratio.', $interaction));
        }

        $part = $this->videoPart($interaction);
        if ($part === null) {
            throw new RuntimeException('Gemini did not return a video.');
        }
        if (! empty($part['data'])) {
            $binary = base64_decode((string) $part['data'], true);
            if ($binary === false || $binary === '') {
                throw new RuntimeException('Gemini returned a video that could not be saved.');
            }

            return $binary;
        }
        if (! empty($part['uri'])) {
            $downloaded = Http::timeout(120)->get($part['uri']);
            if (! $downloaded->successful() || $downloaded->body() === '') {
                throw new RuntimeException('Gemini returned a video link that could not be downloaded.');
            }

            return $downloaded->body();
        }

        throw new RuntimeException('Gemini did not return a video.');
    }

    /**
     * @param  array<string, mixed>  $interaction
     * @return array<string, mixed>|null
     */
    private function videoPart(array $interaction): ?array
    {
        foreach ($interaction['steps'] ?? [] as $step) {
            if (($step['type'] ?? '') !== 'model_output') {
                continue;
            }
            foreach ($step['content'] ?? [] as $part) {
                if (($part['type'] ?? '') === 'video') {
                    return $part;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function apiError(string $fallback, ?array $body): string
    {
        $message = $body['error']['message'] ?? $body['message'] ?? null;
        if (is_string($message) && $message !== '') {
            return $fallback.' '.$message;
        }

        return $fallback;
    }
}

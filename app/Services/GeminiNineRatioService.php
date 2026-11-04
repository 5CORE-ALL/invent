<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GeminiNineRatioService
{
    public function changeToNineSixteen(string $sourceUrl, string $sku): string
    {
        $source = $this->resolveSource($sourceUrl);
        $output = tempnam(sys_get_temp_dir(), 'nine-out-');
        $mp4 = $output.'.mp4';
        try {
            $this->convertWithFfmpeg($source['path'], $mp4);
            $relative = 'nine-ration/'.preg_replace('/[^A-Za-z0-9._-]+/', '-', $sku).'-'.time().'.mp4';
            $stream = fopen($mp4, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Could not read the converted video.');
            }
            Storage::disk('public')->put($relative, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            return asset('storage/'.$relative);
        } finally {
            if ($source['temporary'] && is_file($source['path'])) {
                @unlink($source['path']);
            }
            if (is_string($output) && is_file($output)) {
                @unlink($output);
            }
            if (is_file($mp4)) {
                @unlink($mp4);
            }
        }
    }

    /**
     * @return array{path: string, temporary: bool}
     */
    private function resolveSource(string $url): array
    {
        $url = trim($url);
        $local = $this->localVideoPath($url);
        if ($local !== null) {
            return ['path' => $local, 'temporary' => false];
        }
        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) ? $path : $url;
        if (preg_match('#/(storage|video-ads-master/file)/#', $path)) {
            throw new RuntimeException('That video link points at this server, but the file is not in the video folder.');
        }

        $downloaded = $this->downloadSource($url);

        return ['path' => $downloaded['path'], 'temporary' => true];
    }

    private function localVideoPath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? rawurldecode($path) : $url;
        if (str_contains($path, '..')) {
            return null;
        }

        if (preg_match('#/video-ads-master/file/\d+/([A-Za-z0-9._-]+)$#', $path, $match)) {
            $file = storage_path('app/video-ads/'.$match[1]);

            return is_file($file) ? $file : null;
        }

        if (preg_match('#/storage/(.+)$#', $path, $match)) {
            $file = Storage::disk('public')->path($match[1]);

            return is_file($file) ? $file : null;
        }

        return null;
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
            throw new RuntimeException('Paste a direct video file link, such as an mp4 address.');
        }
        if (str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) {
            throw new RuntimeException('YouTube page links cannot be converted. Use a direct video file link.');
        }
        if (str_contains($host, 'dropbox.com')) {
            $url = str_replace('www.dropbox.com', 'dl.dropboxusercontent.com', $url);
            $url = str_replace('dl=0', 'dl=1', $url);
            if (! str_contains($url, 'dl=1')) {
                $url .= (str_contains($url, '?') ? '&' : '?').'dl=1';
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'nine-ratio-');
        if ($path === false) {
            throw new RuntimeException('Could not create a temp file for the video.');
        }

        $response = Http::timeout(180)
            ->withOptions(['sink' => $path])
            ->get($url);

        if (! $response->successful() || ! is_file($path) || filesize($path) < 1024) {
            @unlink($path);
            throw new RuntimeException('Could not download the video from that link.');
        }
        if (filesize($path) > 500 * 1024 * 1024) {
            @unlink($path);
            throw new RuntimeException('Video is larger than 500 MB.');
        }

        $this->assertDownloadedVideo($path);

        return ['path' => $path, 'mime' => 'video/mp4'];
    }

    private function assertDownloadedVideo(string $path): void
    {
        $head = strtolower(ltrim((string) file_get_contents($path, false, null, 0, 64)));
        if (str_starts_with($head, '<!doctype') || str_starts_with($head, '<html') || str_starts_with($head, '<?xml')) {
            throw new RuntimeException('That link opened a web page, not a video file. Paste the direct file address.');
        }
    }

    private function convertWithFfmpeg(string $input, string $output): void
    {
        $ffmpeg = $this->ffmpegBinary();
        if ($ffmpeg === null) {
            throw new RuntimeException('ffmpeg is not installed on this server, so the video ratio cannot be changed.');
        }

        $run = Process::timeout(300)->run([
            $ffmpeg, '-y', '-i', $input,
            '-vf', 'scale=720:1280:force_original_aspect_ratio=increase,crop=720:1280',
            '-map', '0:v:0', '-map', '0:a:0?',
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23',
            '-c:a', 'aac', '-movflags', '+faststart',
            $output,
        ]);
        if (! $run->successful() || ! is_file($output) || filesize($output) < 1024) {
            $detail = trim($run->errorOutput());
            throw new RuntimeException('Could not convert that video to 9:16. '.substr($detail, -400));
        }
    }

    private function ffmpegBinary(): ?string
    {
        foreach (['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg'] as $binary) {
            $check = Process::run([$binary, '-version']);
            if ($check->successful()) {
                return $binary;
            }
        }

        return null;
    }

    private function convertWithGemini(string $key, string $path, string $mime): string
    {
        $file = $this->uploadToGemini($key, $path, $mime);
        $this->waitUntilActive($key, $file['name']);

        return $this->generateNineSixteen($key, $file['uri']);
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
                $reason = $file['error']['message'] ?? null;
                throw new RuntimeException(
                    'Gemini could not process that video file.'
                    .(is_string($reason) && $reason !== '' ? ' '.$reason : ' Use a short mp4, under 10 seconds.')
                );
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

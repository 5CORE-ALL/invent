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
        return $this->storeConverted($sourceUrl, $sku, '1080:1920', 'nine-ration', '9:16');
    }

    public function changeToSixteenNine(string $sourceUrl, string $sku): string
    {
        return $this->storeConverted($sourceUrl, $sku, '1920:1080', 'sixteen-ration', '16:9');
    }

    private function storeConverted(string $sourceUrl, string $sku, string $size, string $folder, string $label): string
    {
        $source = $this->resolveSource($sourceUrl);
        $output = tempnam(sys_get_temp_dir(), 'ratio-out-');
        $mp4 = $output.'.mp4';
        try {
            $this->convertWithFfmpeg($source['path'], $mp4, $size, $label);
            $relative = $folder.'/'.preg_replace('/[^A-Za-z0-9._-]+/', '-', $sku).'-'.time().'.mp4';
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

        $candidates = [];
        if (preg_match('#/video-ads-master/file/\d+/([A-Za-z0-9._-]+)$#', $path, $match)) {
            $candidates[] = storage_path('app/video-ads/'.$match[1]);
        }
        if (preg_match('#/storage/(.+)$#', $path, $match)) {
            $relative = ltrim(str_replace('\\', '/', $match[1]), '/');
            $candidates[] = Storage::disk('public')->path($relative);
            $candidates[] = public_path('storage/'.$relative);
        }
        $relative = ltrim($path, '/');
        if ($relative !== '' && ! str_contains($relative, '..')) {
            $candidates[] = public_path($relative);
        }

        foreach ($candidates as $file) {
            if (is_string($file) && is_file($file)) {
                return $file;
            }
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
        $url = $this->directVideoUrl($url);

        $path = tempnam(sys_get_temp_dir(), 'nine-ratio-');
        if ($path === false) {
            throw new RuntimeException('Could not create a temp file for the video.');
        }

        $status = $this->downloadToFile($url, $path);
        $this->normalizeDownloadedFile($path);
        if ($this->isHtmlFile($path)) {
            $media = $this->videoUrlInsidePage($path, $url);
            if ($media !== null) {
                $status = $this->downloadToFile($media, $path);
                $this->normalizeDownloadedFile($path);
            }
        }
        if ($status < 200 || $status >= 300 || ! is_file($path) || filesize($path) < 1024) {
            @unlink($path);
            throw new RuntimeException('Could not download the video from that link (HTTP '.$status.'). The link has to open the video file itself.');
        }
        if (filesize($path) > 500 * 1024 * 1024) {
            @unlink($path);
            throw new RuntimeException('Video is larger than 500 MB.');
        }
        $this->extractZipVideo($path);
        $this->alignVideoStart($path);

        if ($this->isHtmlFile($path) || ! $this->looksLikeVideo($path)) {
            @unlink($path);
            throw new RuntimeException('That link did not return a video file. Open the link and copy the address of the mp4 itself.');
        }

        return ['path' => $path, 'mime' => 'video/mp4'];
    }

    private function directVideoUrl(string $url): string
    {
        if (preg_match('#drive\.google\.com/file/d/([a-zA-Z0-9_-]+)#', $url, $drive)) {
            return 'https://drive.google.com/uc?export=download&confirm=t&id='.$drive[1];
        }
        if (stripos($url, 'dropbox.com') === false) {
            return $url;
        }

        $url = preg_replace('/([?&])(?:dl|raw)=\d+/i', '$1dl=1', $url) ?? $url;
        if (! preg_match('/[?&]dl=1(&|$)/i', $url)) {
            $url .= (str_contains($url, '?') ? '&' : '?').'dl=1';
        }

        return $url;
    }

    private function videoUrlInsidePage(string $path, string $pageUrl): ?string
    {
        $html = (string) file_get_contents($path, false, null, 0, 1024 * 1024);
        if (preg_match('#https://drive\.google\.com/uc\?[^\"\'\s<]+#i', $html, $drive)) {
            $next = html_entity_decode($drive[0]);
            if (! str_contains($next, 'confirm=')) {
                $next .= '&confirm=t';
            }

            return $next;
        }

        if (preg_match('#https://[^\"\'\s<]*dropboxusercontent\.com/[^\"\'\s<]+#i', $html, $dropbox)) {
            return html_entity_decode($dropbox[0]);
        }

        if (preg_match('#https?://[^\"\'\s<]+\.(?:mp4|webm|mov|m4v|mkv)(?:\?[^\"\'\s<]*)?#i', $html, $file)) {
            return html_entity_decode($file[0]);
        }

        $parts = parse_url($pageUrl);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        if (preg_match('#(?:src|href)=[\"\']([^\"\']+\.(?:mp4|webm|mov|m4v|mkv)(?:\?[^\"\']*)?)[\"\']#i', $html, $relative)) {
            $found = html_entity_decode($relative[1]);
            if (str_starts_with($found, '//')) {
                return 'https:'.$found;
            }
            if (str_starts_with($found, '/')) {
                return $origin.$found;
            }
            if (str_starts_with($found, 'http')) {
                return $found;
            }
        }

        return null;
    }

    private function isHtmlFile(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }
        $head = strtolower(ltrim((string) file_get_contents($path, false, null, 0, 512)));

        return str_starts_with($head, '<!doctype')
            || str_starts_with($head, '<html')
            || str_starts_with($head, '<head')
            || str_starts_with($head, '<?xml')
            || str_starts_with($head, '{');
    }

    private function normalizeDownloadedFile(string $path): void
    {
        if (! is_file($path) || filesize($path) < 2) {
            return;
        }
        $magic = (string) file_get_contents($path, false, null, 0, 3);
        if (str_starts_with($magic, "\x1f\x8b")) {
            $raw = $path.'.raw';
            $in = gzopen($path, 'rb');
            $out = fopen($raw, 'wb');
            if ($in !== false && $out !== false) {
                while (! gzeof($in)) {
                    $chunk = gzread($in, 1024 * 1024);
                    if ($chunk === false) {
                        break;
                    }
                    fwrite($out, $chunk);
                }
                gzclose($in);
                fclose($out);
                rename($raw, $path);
            }
        }
        $start = (string) file_get_contents($path, false, null, 0, 3);
        if ($start === "\xEF\xBB\xBF") {
            $raw = $path.'.raw';
            $in = fopen($path, 'rb');
            $out = fopen($raw, 'wb');
            if ($in !== false && $out !== false) {
                fseek($in, 3);
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
                rename($raw, $path);
            }
        }
    }

    private function extractZipVideo(string $path): void
    {
        $magic = (string) file_get_contents($path, false, null, 0, 2);
        if ($magic !== 'PK' || ! class_exists(\ZipArchive::class)) {
            return;
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return;
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! preg_match('/\.(mp4|mov|webm|m4v|mkv)$/i', $name)) {
                continue;
            }
            $in = $zip->getStream($name);
            if ($in === false) {
                continue;
            }
            $raw = $path.'.raw';
            $out = fopen($raw, 'wb');
            if ($out !== false) {
                stream_copy_to_stream($in, $out);
                fclose($out);
                fclose($in);
                $zip->close();
                rename($raw, $path);

                return;
            }
            fclose($in);
        }
        $zip->close();
    }

    private function alignVideoStart(string $path): void
    {
        $chunk = (string) file_get_contents($path, false, null, 0, 262144);
        $pos = strpos($chunk, 'ftyp');
        $start = ($pos !== false && $pos >= 4) ? $pos - 4 : 0;
        if ($start < 1) {
            return;
        }
        $raw = $path.'.raw';
        $in = fopen($path, 'rb');
        $out = fopen($raw, 'wb');
        if ($in === false || $out === false) {
            return;
        }
        fseek($in, $start);
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        rename($raw, $path);
    }

    private function looksLikeVideo(string $path): bool
    {
        $head = (string) file_get_contents($path, false, null, 0, 64);
        if (str_contains($head, 'ftyp') || str_starts_with($head, "\x1A\x45\xDF\xA3") || str_starts_with($head, 'RIFF')) {
            return true;
        }

        return false;
    }

    private function downloadToFile(string $url, string $path): int
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            return 0;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
            CURLOPT_ENCODING => '',
            CURLOPT_TIMEOUT => 180,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($handle);

        return $status;
    }

    private function convertWithFfmpeg(string $input, string $output, string $size = '720:1280', string $label = '9:16'): void
    {
        $ffmpeg = $this->ffmpegBinary();
        if ($ffmpeg === null) {
            throw new RuntimeException('ffmpeg is not installed on this server, so the video ratio cannot be changed.');
        }

        [$width, $height] = array_pad(explode(':', $size), 2, '0');
        $vf = 'scale='.$width.':'.$height.':flags=lanczos,setsar=1';
        $run = Process::timeout(300)->run([
            $ffmpeg, '-y', '-i', $input,
            '-vf', $vf,
            '-map', '0:v:0', '-map', '0:a:0?',
            '-c:v', 'libx264', '-preset', 'fast', '-crf', '18', '-profile:v', 'high', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '192k', '-movflags', '+faststart',
            $output,
        ]);
        if (! $run->successful() || ! is_file($output) || filesize($output) < 1024) {
            $detail = trim($run->errorOutput());
            $errors = array_values(array_filter(
                preg_split('/\R/', $detail) ?: [],
                static fn ($line) => str_contains($line, 'Error') || str_contains($line, 'Invalid')
            ));
            $message = $errors !== [] ? implode(' ', array_slice($errors, -2)) : 'The file could not be read as a video.';
            throw new RuntimeException('Could not convert that video to '.$label.'. '.$message);
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

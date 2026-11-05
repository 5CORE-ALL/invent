<?php

namespace App\Http\Controllers\MarketingMaster;

use App\Http\Controllers\Controller;
use App\Models\NineRationVideo;
use App\Models\ProductMaster;
use App\Models\RationVideoUpload;
use App\Models\SixteenRationVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Upload / download of the video files behind the 9:16 and 16:9 columns of the Shoppable Video pages.
 *
 * Videos arrive in 1 MB pieces (the same approach as Video Ads Master) so no proxy or PHP upload limit stops a
 * large file. The finished file lives on the public disk and the row's video link points at it, so the existing
 * Open / Change Ratio / export features keep working on uploaded videos.
 */
class RationVideoUploadController extends Controller
{
    private const EXTENSIONS = ['mp4', 'mov', 'webm', 'm4v', 'mkv'];

    private const MAX_BYTES = 500 * 1024 * 1024;

    private const CHUNK_BYTES = 1024 * 1024;

    /** @var array<string, array{field: string, label: string, folder: string}> */
    private const RATIOS = [
        'nine' => ['field' => 'nine_ratio_link', 'label' => '9:16', 'folder' => 'nine-ration-uploads'],
        'sixteen' => ['field' => 'sixteen_ratio_link', 'label' => '16:9', 'folder' => 'sixteen-ration-uploads'],
    ];

    public function chunk(Request $request): JsonResponse
    {
        $uploadId = (string) $request->input('upload_id', '');
        $index = (int) $request->input('chunk_index', -1);
        $total = (int) $request->input('total_chunks', 0);
        $filename = (string) $request->input('filename', '');
        $maxChunks = (int) ceil(self::MAX_BYTES / self::CHUNK_BYTES) + 2;
        if (! preg_match('/^[a-f0-9]{32}$/', $uploadId) || $index < 0 || $total < 1 || $total > $maxChunks || $index >= $total) {
            return $this->fail('The video upload was not accepted.', 422);
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (! in_array($ext, self::EXTENSIONS, true)) {
            return $this->fail('Upload an mp4, mov, webm, m4v or mkv video.', 422);
        }

        $chunk = $request->file('chunk');
        if (! $chunk || ! $chunk->isValid()) {
            return $this->fail('A piece of the video did not upload. Try again.', 422);
        }
        if ($chunk->getSize() > self::CHUNK_BYTES + 1024) {
            return $this->fail('A piece of the video is too large.', 422);
        }

        $dir = $this->tempDir();
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return $this->fail('Could not store the video.', 500);
        }

        $part = $dir.'/'.$uploadId.'.part';
        $metaPath = $part.'.json';
        if ($index === 0) {
            $this->purgeStaleTemps($dir);
            @unlink($part);
            file_put_contents($metaPath, json_encode(['next' => 0, 'ext' => $ext, 'done' => false]));
        }

        $state = is_file($metaPath) ? json_decode((string) file_get_contents($metaPath), true) : null;
        if (! is_array($state) || (int) ($state['next'] ?? -1) !== $index || ($state['ext'] ?? '') !== $ext) {
            return $this->fail('The video upload was interrupted. Please try again.', 409);
        }

        $in = fopen($chunk->getRealPath(), 'rb');
        $out = fopen($part, 'ab');
        if ($in === false || $out === false) {
            if (is_resource($in)) {
                fclose($in);
            }
            if (is_resource($out)) {
                fclose($out);
            }

            return $this->fail('Could not store the video.', 500);
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        clearstatcache(true, $part);

        if ((int) filesize($part) > self::MAX_BYTES) {
            @unlink($part);
            @unlink($metaPath);

            return $this->fail('Video must be 500 MB or smaller.', 422);
        }

        $done = $index === $total - 1;
        file_put_contents($metaPath, json_encode(['next' => $index + 1, 'ext' => $ext, 'done' => $done]));

        return response()->json(['success' => true, 'done' => $done]);
    }

    /** Finish an upload: move the assembled file into storage, record it and point the row's link at it. */
    public function finish(Request $request, string $ratio): JsonResponse
    {
        $cfg = self::RATIOS[$ratio] ?? null;
        if ($cfg === null) {
            return $this->fail('Unknown video ratio.', 404);
        }

        $sku = trim((string) $request->input('sku', ''));
        $uploadId = (string) $request->input('upload_id', '');
        $original = trim(basename(str_replace('\\', '/', (string) $request->input('filename', 'video'))));
        if ($sku === '' || ! preg_match('/^[a-f0-9]{32}$/', $uploadId)) {
            return $this->fail('The video upload was not accepted.', 422);
        }
        if (! ProductMaster::query()->where('sku', $sku)->exists()) {
            return $this->fail('SKU "'.$sku.'" was not found in Product Master.', 422);
        }

        $part = $this->tempDir().'/'.$uploadId.'.part';
        $metaPath = $part.'.json';
        $state = is_file($metaPath) ? json_decode((string) file_get_contents($metaPath), true) : null;
        $ext = strtolower((string) (is_array($state) ? ($state['ext'] ?? '') : ''));
        if (! is_array($state) || empty($state['done']) || ! is_file($part) || ! in_array($ext, self::EXTENSIONS, true)) {
            return $this->fail('The video upload did not finish. Please try again.', 422);
        }

        $size = (int) filesize($part);
        if ($size < 1024 || ! $this->looksLikeVideo($part)) {
            @unlink($part);
            @unlink($metaPath);

            return $this->fail('That file is not a readable video.', 422);
        }

        $slug = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $sku), '-') ?: 'sku';
        $relative = $cfg['folder'].'/'.$slug.'-'.time().'-'.bin2hex(random_bytes(3)).'.'.$ext;
        $stream = fopen($part, 'rb');
        if ($stream === false) {
            return $this->fail('Could not read the uploaded video.', 500);
        }
        try {
            $stored = Storage::disk('public')->put($relative, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        if (! $stored) {
            return $this->fail('Could not save the video to storage.', 500);
        }
        @unlink($part);
        @unlink($metaPath);

        $url = asset('storage/'.$relative);
        $upload = RationVideoUpload::create([
            'ratio' => $ratio,
            'sku' => $sku,
            'original_name' => mb_substr($original !== '' ? $original : $slug.'.'.$ext, 0, 255),
            'path' => $relative,
            'size' => $size,
            'mime' => $this->mimeFor($ext),
            'uploaded_by' => (string) (auth()->user()->name ?? ''),
        ]);

        if (! $this->saveLink($ratio, $sku, $url)) {
            Log::warning('Ration video uploaded but the row link was not saved', ['ratio' => $ratio, 'sku' => $sku]);

            return $this->fail('The video was uploaded but its link could not be saved on the row.', 500);
        }

        return response()->json([
            'success' => true,
            'url' => $url,
            'field' => $cfg['field'],
            'upload' => $this->present($upload),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $ratio = (string) $request->query('ratio', '');
        $q = trim((string) $request->query('q', ''));

        $rows = RationVideoUpload::query()
            ->when(isset(self::RATIOS[$ratio]), fn ($query) => $query->where('ratio', $ratio))
            ->when($q !== '', fn ($query) => $query->where('sku', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%'))
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (RationVideoUpload $row) => $this->present($row))->values(),
        ]);
    }

    public function download(int $id)
    {
        $row = RationVideoUpload::query()->findOrFail($id);
        $disk = Storage::disk('public');
        abort_unless($disk->exists($row->path), 404, 'The uploaded file is no longer on the server.');

        return response()->download($disk->path($row->path), $row->original_name);
    }

    /** Download whatever video the row's link points at: files on this server stream, other links open as they are. */
    public function downloadCurrent(Request $request)
    {
        $ratio = (string) $request->query('ratio', '');
        $sku = trim((string) $request->query('sku', ''));
        abort_unless(isset(self::RATIOS[$ratio]) && $sku !== '', 404);

        $link = $this->currentLink($ratio, $sku);
        abort_if($link === '', 404, 'This row has no video.');

        $local = $this->localFileForUrl($link, $request->getHost());
        if ($local !== null) {
            return response()->download($local, $this->downloadName($sku, $ratio, $local));
        }

        if (preg_match('#^https?://#i', $link)) {
            if (stripos($link, 'dropbox.com') !== false) {
                $link = preg_replace('/([?&])dl=\d/i', '$1dl=1', $link) ?? $link;
                if (! preg_match('/[?&]dl=1/i', $link)) {
                    $link .= (str_contains($link, '?') ? '&' : '?').'dl=1';
                }
            }

            return redirect()->away($link);
        }

        abort(404, 'This video file is not on the server.');
    }

    public function destroy(int $id): JsonResponse
    {
        $row = RationVideoUpload::query()->findOrFail($id);
        $cfg = self::RATIOS[$row->ratio] ?? null;

        $clearedField = null;
        if ($cfg !== null) {
            $current = $this->currentLink((string) $row->ratio, (string) $row->sku);
            $mine = (string) parse_url(asset('storage/'.$row->path), PHP_URL_PATH);
            if ($current !== '' && (string) parse_url($current, PHP_URL_PATH) === $mine) {
                if ($this->saveLink((string) $row->ratio, (string) $row->sku, '')) {
                    $clearedField = $cfg['field'];
                }
            }
        }

        Storage::disk('public')->delete($row->path);
        $row->delete();

        return response()->json(['success' => true, 'cleared_field' => $clearedField, 'sku' => $row->sku]);
    }

    /** @return array<string, mixed> */
    private function present(RationVideoUpload $row): array
    {
        $cfg = self::RATIOS[$row->ratio] ?? ['label' => (string) $row->ratio];

        return [
            'id' => $row->id,
            'ratio' => $row->ratio,
            'ratio_label' => $cfg['label'],
            'sku' => $row->sku,
            'name' => $row->original_name,
            'size' => (int) $row->size,
            'uploaded_by' => (string) $row->uploaded_by,
            'uploaded_at' => $row->created_at ? $row->created_at->format('Y-m-d H:i') : '',
            'url' => asset('storage/'.$row->path),
            'download_url' => url('/ration-video/uploads/'.$row->id.'/download'),
            'exists' => Storage::disk('public')->exists($row->path),
        ];
    }

    /** Set (or clear) the row's video link through the page's own save, so every linked ads table stays in step. */
    private function saveLink(string $ratio, string $sku, string $url): bool
    {
        $cfg = self::RATIOS[$ratio];
        $controller = app(ShoppableVideoController::class);
        $payload = new Request(['sku' => $sku, 'value' => [$cfg['field'] => $url]]);
        $response = $ratio === 'nine'
            ? $controller->saveNineRationVideo($payload)
            : $controller->saveSixteenRationVideo($payload);
        $data = $response instanceof JsonResponse ? $response->getData(true) : [];

        return ! empty($data['success']);
    }

    private function currentLink(string $ratio, string $sku): string
    {
        $model = $ratio === 'nine' ? NineRationVideo::query() : SixteenRationVideo::query();
        $row = $model->where('sku', $sku)->first();
        if (! $row) {
            return '';
        }
        $value = $row->value;
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? trim((string) ($value[self::RATIOS[$ratio]['field']] ?? '')) : '';
    }

    private function localFileForUrl(string $url, string $appHost): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host !== '' && $host !== strtolower($appHost)) {
            return null;
        }
        $path = rawurldecode((string) ($parts['path'] ?? ''));
        if ($path === '' || str_contains($path, '..') || ! preg_match('#^/storage/(.+)$#', $path, $match)) {
            return null;
        }

        $root = realpath(Storage::disk('public')->path(''));
        $file = realpath(Storage::disk('public')->path(ltrim($match[1], '/')));
        if ($root === false || $file === false || ! is_file($file) || ! str_starts_with($file, $root)) {
            return null;
        }

        return $file;
    }

    private function downloadName(string $sku, string $ratio, string $file): string
    {
        $slug = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $sku), '-') ?: 'video';
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION)) ?: 'mp4';

        return $slug.'-'.str_replace(':', 'x', self::RATIOS[$ratio]['label']).'.'.$ext;
    }

    private function looksLikeVideo(string $path): bool
    {
        $head = (string) file_get_contents($path, false, null, 0, 64);
        if (str_starts_with($head, "\x1A\x45\xDF\xA3") || str_starts_with($head, 'RIFF')) {
            return true;
        }
        foreach (['ftyp', 'moov', 'mdat', 'wide', 'free', 'skip'] as $atom) {
            if (str_contains($head, $atom)) {
                return true;
            }
        }

        return false;
    }

    private function mimeFor(string $ext): string
    {
        return match ($ext) {
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
            default => 'video/mp4',
        };
    }

    private function tempDir(): string
    {
        return storage_path('app/ration-uploads-tmp');
    }

    private function purgeStaleTemps(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < time() - 86400) {
                @unlink($file);
            }
        }
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}

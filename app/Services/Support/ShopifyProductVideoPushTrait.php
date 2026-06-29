<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait ShopifyProductVideoPushTrait
{
    private function shopifyProductMediaNodesQuery(): string
    {
        return 'query($id: ID!) { product(id: $id) { media(first: 100) { nodes { id mediaContentType } } } } }';
    }

    private function shopifyProductMediaNodesQueryLarge(): string
    {
        return 'query($id: ID!) { product(id: $id) { media(first: 250) { nodes { id mediaContentType } } } } }';
    }

    private function shopifyProductVideoCountQuery(): string
    {
        return 'query($id: ID!) { product(id: $id) { media(first: 100) { nodes { mediaContentType status } } } } }';
    }

    /**
     * @param  list<string>  $urls
     * @return array{success: bool, message: string, uploaded?: int, deleted?: int, normalized_urls?: list<string>}
     */
    protected function attachProductVideosViaGraphql(string $domain, string $token, string $productId, array $urls, string $mode): array
    {
        $version = config('services.shopify.api_version', '2025-01');
        $gql = "https://{$domain}/admin/api/{$version}/graphql.json";
        $headers = ['X-Shopify-Access-Token' => $token, 'Content-Type' => 'application/json'];
        $pgid = 'gid://shopify/Product/'.$productId;

        $post = fn (string $query, array $vars) => $this->retryOnRateLimit(fn () => Http::withHeaders($headers)
            ->timeout(90)->post($gql, ['query' => $query, 'variables' => $vars]));

        $oldVideoMediaIds = [];
        if ($mode === 'replace') {
            $lr = $post($this->shopifyProductMediaNodesQuery(), ['id' => $pgid]);
            foreach ($lr->json('data.product.media.nodes') ?: [] as $node) {
                if (! is_array($node)) {
                    continue;
                }
                $type = strtoupper((string) ($node['mediaContentType'] ?? ''));
                if (in_array($type, ['VIDEO', 'EXTERNAL_VIDEO'], true) && ! empty($node['id'])) {
                    $oldVideoMediaIds[] = $node['id'];
                }
            }
        }

        if ($urls === [] && $mode === 'replace') {
            $deleted = 0;
            if ($oldVideoMediaIds !== []) {
                $dq = 'mutation($pid:ID!,$ids:[ID!]!){productDeleteMedia(productId:$pid,mediaIds:$ids){deletedMediaIds mediaUserErrors{message}}}';
                $dr = $post($dq, ['pid' => $pgid, 'ids' => $oldVideoMediaIds]);
                $deleted = count($dr->json('data.productDeleteMedia.deletedMediaIds') ?: []);
            }

            return [
                'success' => true,
                'message' => "All product videos removed from Shopify ({$deleted} deleted).",
                'deleted' => $deleted,
                'normalized_urls' => [],
            ];
        }

        $createdIds = [];
        $createErrors = [];
        $createMutation = 'mutation($pid:ID!,$media:[CreateMediaInput!]!){productCreateMedia(productId:$pid,media:$media){media{id status} mediaUserErrors{field message}}}';

        foreach ($urls as $index => $url) {
            $cr = $post($createMutation, [
                'pid' => $pgid,
                'media' => [$this->shopifyCreateMediaInputForVideoUrl($url)],
            ]);
            $userErrs = $cr->json('data.productCreateMedia.mediaUserErrors') ?: [];
            $nodes = $cr->json('data.productCreateMedia.media') ?: [];
            $mediaId = is_array($nodes[0] ?? null) ? ($nodes[0]['id'] ?? null) : null;

            if ($userErrs !== []) {
                Log::warning('Shopify productCreateMedia video userErrors', [
                    'product_id' => $productId,
                    'index' => $index + 1,
                    'errors' => $userErrs,
                ]);
                $createErrors[] = 'Video '.($index + 1).': '.json_encode($userErrs);
            }

            if (! is_string($mediaId) || $mediaId === '') {
                $createErrors[] = 'Video '.($index + 1).': no media id returned';
                continue;
            }

            $createdIds[] = $mediaId;

            if ($index < count($urls) - 1) {
                usleep(800_000);
            }
        }

        if ($createdIds === []) {
            return [
                'success' => false,
                'message' => 'productCreateMedia (video) failed: '.implode(' | ', $createErrors ?: ['no media created']),
            ];
        }

        $deleted = 0;
        if ($mode === 'replace' && $oldVideoMediaIds !== []) {
            $dq = 'mutation($pid:ID!,$ids:[ID!]!){productDeleteMedia(productId:$pid,mediaIds:$ids){deletedMediaIds mediaUserErrors{message}}}';
            $dr = $post($dq, ['pid' => $pgid, 'ids' => $oldVideoMediaIds]);
            $deleted = count($dr->json('data.productDeleteMedia.deletedMediaIds') ?: []);
            $delErrs = $dr->json('data.productDeleteMedia.mediaUserErrors') ?: [];
            if ($delErrs !== []) {
                Log::warning('Shopify productDeleteMedia video errors', [
                    'product_id' => $productId,
                    'errors' => $delErrs,
                ]);
            }
        }

        $this->reorderAllShopifyVideosToGalleryEnd($post, $pgid, $createdIds);
        $statusSummary = $this->pollShopifyVideoMediaStatuses($post, $createdIds);

        $verify = $this->countShopifyProductVideos($post, $pgid);
        Log::info('Shopify video push complete', [
            'product_id' => $productId,
            'requested' => count($urls),
            'created' => count($createdIds),
            'deleted_old' => $deleted,
            'on_product_now' => $verify,
            'status' => $statusSummary,
        ]);

        $action = $mode === 'replace' ? 'Replaced with' : 'Added';
        $ready = (int) ($statusSummary['ready'] ?? 0);
        $processing = (int) ($statusSummary['processing'] ?? 0);
        $failed = (int) ($statusSummary['failed'] ?? 0);

        $message = "{$action} ".count($createdIds).' video(s) on Shopify';
        if ($deleted > 0) {
            $message .= " ({$deleted} old video(s) removed)";
        }
        if ($verify > 0) {
            $message .= ". {$verify} video(s) on product now";
        }
        if ($processing > 0) {
            $message .= ". {$ready} ready, {$processing} still processing — large videos can take several minutes before they appear on the storefront";
        }
        if ($failed > 0) {
            $message .= ". {$failed} failed on Shopify";
        }
        if ($createErrors !== []) {
            $message .= '. Errors: '.implode(' | ', $createErrors);
        }

        if (count($createdIds) < count($urls) || $failed > 0) {
            return [
                'success' => false,
                'message' => $message,
                'uploaded' => count($createdIds),
                'deleted' => $deleted,
                'normalized_urls' => array_values($urls),
            ];
        }

        return [
            'success' => true,
            'message' => $message.'.',
            'uploaded' => count($createdIds),
            'deleted' => $deleted,
            'normalized_urls' => array_values($urls),
        ];
    }

    /**
     * Move every video to the end of the product media gallery (all images / 3D first, then videos).
     *
     * @param  list<string>  $preferredVideoOrder  Newly created media IDs in push order
     */
    protected function reorderAllShopifyVideosToGalleryEnd(callable $post, string $productGid, array $preferredVideoOrder = []): void
    {
        $lr = $post($this->shopifyProductMediaNodesQueryLarge(), ['id' => $productGid]);

        $nodes = $lr->json('data.product.media.nodes') ?: [];
        $nonVideoIds = [];
        $videoIdSet = [];
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $id = (string) ($node['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $type = strtoupper((string) ($node['mediaContentType'] ?? ''));
            if (in_array($type, ['VIDEO', 'EXTERNAL_VIDEO'], true)) {
                $videoIdSet[$id] = true;
            } else {
                $nonVideoIds[] = $id;
            }
        }

        if ($videoIdSet === []) {
            return;
        }

        $orderedVideoIds = [];
        foreach ($preferredVideoOrder as $id) {
            $id = (string) $id;
            if ($id !== '' && isset($videoIdSet[$id])) {
                $orderedVideoIds[] = $id;
                unset($videoIdSet[$id]);
            }
        }
        foreach (array_keys($videoIdSet) as $id) {
            $orderedVideoIds[] = $id;
        }

        $moves = [];
        $position = 0;
        foreach ($nonVideoIds as $id) {
            $moves[] = ['id' => $id, 'newPosition' => (string) $position++];
        }
        foreach ($orderedVideoIds as $id) {
            $moves[] = ['id' => $id, 'newPosition' => (string) $position++];
        }

        if ($moves === []) {
            return;
        }

        $rq = 'mutation($id:ID!,$moves:[MoveInput!]!){productReorderMedia(id:$id,moves:$moves){mediaUserErrors{field message}}}';
        $rr = $post($rq, ['id' => $productGid, 'moves' => $moves]);
        $errs = $rr->json('data.productReorderMedia.mediaUserErrors') ?: [];
        if ($errs !== []) {
            Log::warning('Shopify productReorderMedia (videos to end) errors', [
                'errors' => $errs,
                'image_count' => count($nonVideoIds),
                'video_count' => count($orderedVideoIds),
            ]);
        } else {
            Log::info('Shopify videos moved to end of gallery', [
                'image_count' => count($nonVideoIds),
                'video_count' => count($orderedVideoIds),
                'first_video_position' => count($nonVideoIds),
            ]);
        }
    }

    /**
     * @deprecated Use reorderAllShopifyVideosToGalleryEnd()
     *
     * @param  list<string>  $mediaIds
     */
    protected function reorderShopifyProductVideoMedia(callable $post, string $productGid, array $mediaIds): void
    {
        $this->reorderAllShopifyVideosToGalleryEnd($post, $productGid, $mediaIds);
    }

    /**
     * @param  list<string>  $mediaIds
     * @return array{ready: int, processing: int, failed: int, statuses: list<string>}
     */
    protected function pollShopifyVideoMediaStatuses(callable $post, array $mediaIds): array
    {
        $ready = 0;
        $processing = 0;
        $failed = 0;
        $statuses = [];
        $statusQuery = 'query($id:ID!){node(id:$id){... on Media{status mediaContentType}}}';

        foreach ($mediaIds as $index => $mediaId) {
            $status = 'UNKNOWN';
            for ($attempt = 0; $attempt < 20; $attempt++) {
                $sr = $post($statusQuery, ['id' => $mediaId]);
                $status = strtoupper((string) ($sr->json('data.node.status') ?? 'UNKNOWN'));
                if (in_array($status, ['READY', 'FAILED'], true)) {
                    break;
                }
                usleep(1_500_000);
            }

            $statuses[] = 'Video '.($index + 1).': '.$status;
            if ($status === 'READY') {
                $ready++;
            } elseif ($status === 'FAILED') {
                $failed++;
            } else {
                $processing++;
            }
        }

        return compact('ready', 'processing', 'failed', 'statuses');
    }

    protected function countShopifyProductVideos(callable $post, string $productGid): int
    {
        $lr = $post($this->shopifyProductVideoCountQuery(), ['id' => $productGid]);

        $count = 0;
        foreach ($lr->json('data.product.media.nodes') ?: [] as $node) {
            if (! is_array($node)) {
                continue;
            }
            $type = strtoupper((string) ($node['mediaContentType'] ?? ''));
            if (in_array($type, ['VIDEO', 'EXTERNAL_VIDEO'], true)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{originalSource: string, mediaContentType: string}
     */
    protected function shopifyCreateMediaInputForVideoUrl(string $url): array
    {
        $url = trim($url);
        if (preg_match('#(?:youtube\.com|youtu\.be|vimeo\.com)#i', $url)) {
            return ['originalSource' => $url, 'mediaContentType' => 'EXTERNAL_VIDEO'];
        }

        return ['originalSource' => $url, 'mediaContentType' => 'VIDEO'];
    }

    /**
     * Resolve video URLs for Shopify productCreateMedia: upload local /storage files via staged upload.
     *
     * @param  list<string>  $urls
     * @return array{success: bool, message: string, urls?: list<string>}
     */
    protected function normalizeVideoUrlsForShopifyPush(string $domain, string $token, array $urls): array
    {
        $resolved = [];
        foreach ($urls as $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }

            if (method_exists($this, 'isLocalStorageUrl') && $this->isLocalStorageUrl($url)) {
                $file = $this->readLocalStorageVideoFile($url);
                if ($file === null) {
                    return [
                        'success' => false,
                        'message' => 'Local video file not found or unreadable: '.basename((string) parse_url($url, PHP_URL_PATH)).'. Re-upload the video or pull from Shopify.',
                    ];
                }

                Log::info('Shopify video push: staging local file', [
                    'filename' => $file['filename'],
                    'bytes' => strlen($file['contents']),
                ]);

                $staged = $this->stagedUploadVideoBytesToShopify(
                    $domain,
                    $token,
                    $file['contents'],
                    $file['filename'],
                    $file['mimeType']
                );
                if (! ($staged['success'] ?? false)) {
                    return [
                        'success' => false,
                        'message' => (string) ($staged['message'] ?? 'Shopify staged video upload failed.'),
                    ];
                }

                $resourceUrl = trim((string) ($staged['resourceUrl'] ?? ''));
                if ($resourceUrl === '') {
                    return ['success' => false, 'message' => 'Shopify staged video upload did not return a resource URL.'];
                }

                $resolved[] = $resourceUrl;

                continue;
            }

            if (! preg_match('#^https?://#i', $url) || ! parse_url($url, PHP_URL_HOST)) {
                return ['success' => false, 'message' => 'Invalid video URL (must be http/https with a host).'];
            }

            $resolved[] = $url;
        }

        if ($resolved === []) {
            return ['success' => false, 'message' => 'No video URLs to push.'];
        }

        return ['success' => true, 'urls' => array_values($resolved)];
    }

    /**
     * @return array{success: bool, message: string, resourceUrl?: string}
     */
    protected function stagedUploadVideoBytesToShopify(
        string $domain,
        string $token,
        string $contents,
        string $filename,
        string $mimeType,
    ): array {
        try {
            $version = config('services.shopify.api_version', '2025-01');
            $gql = "https://{$domain}/admin/api/{$version}/graphql.json";
            $headers = ['X-Shopify-Access-Token' => $token, 'Content-Type' => 'application/json'];
            $filename = $this->sanitizeShopifyVideoFilename($filename, $mimeType);
            $mimeType = $this->normalizeShopifyVideoMimeType($mimeType, $filename);

            $stagedQuery = 'mutation($input:[StagedUploadInput!]!){stagedUploadsCreate(input:$input){stagedTargets{url resourceUrl parameters{name value}} userErrors{field message}}}';
            $stagedVars = ['input' => [[
                'filename' => $filename,
                'mimeType' => $mimeType,
                'resource' => 'VIDEO',
                'httpMethod' => 'POST',
                'fileSize' => (string) strlen($contents),
            ]]];

            $sr = $this->retryOnRateLimit(fn () => Http::withHeaders($headers)->timeout(60)
                ->post($gql, ['query' => $stagedQuery, 'variables' => $stagedVars]));
            $target = $sr->json('data.stagedUploadsCreate.stagedTargets.0');
            $errs = $sr->json('data.stagedUploadsCreate.userErrors') ?: [];
            if (! is_array($target) || empty($target['url']) || $errs) {
                return ['success' => false, 'message' => 'stagedUploadsCreate (video) failed: '.json_encode($errs ?: $sr->json() ?: $sr->body())];
            }

            $upload = Http::asMultipart()->timeout(600);
            foreach (($target['parameters'] ?? []) as $param) {
                $upload = $upload->attach((string) $param['name'], (string) $param['value']);
            }
            $upload = $upload->attach('file', $contents, $filename);

            $uploadOk = false;
            $lastMsg = '';
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                try {
                    $ur = $upload->post($target['url']);
                    if (in_array($ur->status(), [200, 201, 204], true)) {
                        $uploadOk = true;
                        break;
                    }
                    $lastMsg = 'Staged video upload failed (HTTP '.$ur->status().'): '.mb_substr($ur->body(), 0, 300);
                } catch (\Throwable $e) {
                    $lastMsg = $e->getMessage();
                }
                if ($attempt < 3) {
                    Log::warning('Shopify staged video upload retry', ['attempt' => $attempt, 'error' => $lastMsg]);
                    sleep(2);
                }
            }
            if (! $uploadOk) {
                return ['success' => false, 'message' => $lastMsg !== '' ? $lastMsg : 'Staged video upload failed.'];
            }

            $resourceUrl = trim((string) ($target['resourceUrl'] ?? ''));
            if ($resourceUrl === '') {
                return ['success' => false, 'message' => 'Staged video upload succeeded but resourceUrl is missing.'];
            }

            return ['success' => true, 'resourceUrl' => $resourceUrl, 'message' => 'Video staged on Shopify.'];
        } catch (\Throwable $e) {
            Log::error('Shopify staged video upload failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{contents: string, filename: string, mimeType: string}|null
     */
    protected function readLocalStorageVideoFile(string $url): ?array
    {
        try {
            if (! method_exists($this, 'isLocalStorageUrl') || ! $this->isLocalStorageUrl($url)) {
                return null;
            }

            $urlPath = (string) parse_url($url, PHP_URL_PATH);
            if (! preg_match('#/storage/(.+)$#', $urlPath, $m)) {
                return null;
            }

            $candidates = array_unique([
                rawurldecode($m[1]),
                urldecode($m[1]),
                $m[1],
            ]);

            $absolutePath = null;
            foreach ($candidates as $candidate) {
                if ($candidate === '') {
                    continue;
                }
                $abs = Storage::disk('public')->path($candidate);
                if (is_file($abs) && filesize($abs) > 0) {
                    $absolutePath = $abs;
                    break;
                }
            }

            if ($absolutePath === null) {
                return null;
            }

            $fh = @fopen($absolutePath, 'rb');
            if ($fh === false) {
                return null;
            }
            $content = stream_get_contents($fh);
            fclose($fh);
            if ($content === false || $content === '') {
                return null;
            }

            $filename = basename($absolutePath);
            $mimeType = 'video/mp4';
            if (function_exists('mime_content_type')) {
                $detected = @mime_content_type($absolutePath);
                if (is_string($detected) && str_starts_with($detected, 'video/')) {
                    $mimeType = $detected;
                }
            }

            return [
                'contents' => $content,
                'filename' => $filename,
                'mimeType' => $mimeType,
            ];
        } catch (\Throwable $e) {
            Log::warning('readLocalStorageVideoFile failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    protected function sanitizeShopifyVideoFilename(string $name, string $mime = ''): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', pathinfo($name, PATHINFO_FILENAME) ?? '');
        $base = trim((string) $base, '_');
        if ($base === '') {
            $base = 'video_'.substr(md5($name.microtime()), 0, 8);
        }
        $byMime = [
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/webm' => 'webm',
            'video/x-msvideo' => 'avi',
        ];
        $ext = $byMime[strtolower($mime)] ?? strtolower(preg_replace('/[^A-Za-z0-9]+/', '', pathinfo($name, PATHINFO_EXTENSION) ?? '') ?: 'mp4');

        return $base.'.'.$ext;
    }

    protected function normalizeShopifyVideoMimeType(string $mime, string $filename): string
    {
        $mime = strtolower(trim($mime));
        if ($mime !== '' && str_starts_with($mime, 'video/')) {
            return $mime;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'mp4');

        return match ($ext) {
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'avi' => 'video/x-msvideo',
            default => 'video/mp4',
        };
    }

    /**
     * @param  list<string>  $urls
     */
    protected function shopifyAllVideoUrlsArePublic(array $urls): bool
    {
        foreach ($urls as $u) {
            if (method_exists($this, 'isLocalStorageUrl') && $this->isLocalStorageUrl($u)) {
                return false;
            }
            if (! parse_url($u, PHP_URL_HOST)) {
                return false;
            }
        }

        return $urls !== [];
    }
}

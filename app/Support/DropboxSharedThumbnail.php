<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Finds the poster image uploaded beside a video in a Dropbox shared folder
 * and returns the image bytes with a real image content type.
 *
 * Dropbox serves those jpg/png files as application/json, so an <img> tag
 * cannot use the shared link directly.
 */
class DropboxSharedThumbnail
{
    private const MAX_IMAGE_BYTES = 8388608;

    /**
     * @param  array<int, string>  $hooks
     * @return array{body: string, mime: string}|null
     */
    public function imageFor(string $pageUrl, array $hooks): ?array
    {
        $share = $this->classify($pageUrl);
        if ($share === null || $share['mode'] === 'video') {
            return null;
        }

        $href = $share['mode'] === 'image'
            ? $pageUrl
            : $this->thumbnailHref($share, $hooks);

        if ($href === null) {
            return null;
        }

        $cacheKey = 'vam-dbx-thumb:'.sha1($href);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['body'], $cached['mime']) && is_string($cached['body'])) {
            return ['body' => $cached['body'], 'mime' => (string) $cached['mime']];
        }

        $fetched = $this->fetchImage($this->rawUrl($href));
        if ($fetched === null) {
            return null;
        }

        Cache::put($cacheKey, $fetched, now()->addHours(12));

        return $fetched;
    }

    /**
     * Choose the uploaded poster that belongs to this hook.
     *
     * @param  array<int, array{filename?: string, href?: string}>  $entries
     * @param  array<int, string>  $hooks
     */
    public static function pick(array $entries, array $hooks): ?string
    {
        $images = [];
        foreach ($entries as $entry) {
            $name = trim((string) ($entry['filename'] ?? ''));
            $href = trim((string) ($entry['href'] ?? ''));
            if ($name === '' || ! self::isImageName($name) || ! self::isDropboxUrl($href)) {
                continue;
            }
            $images[] = ['name' => $name, 'href' => $href, 'norm' => self::norm($name)];
        }

        if ($images === []) {
            return null;
        }

        $bestHref = null;
        $bestScore = 0;
        foreach ($hooks as $hook) {
            $hookNorm = self::norm((string) $hook);
            if ($hookNorm === '') {
                continue;
            }
            foreach ($images as $image) {
                $score = self::score($hookNorm, $image['norm']);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestHref = $image['href'];
                }
            }
        }

        if ($bestHref !== null && $bestScore >= 80) {
            return $bestHref;
        }

        if (count($images) === 1) {
            return $images[0]['href'];
        }

        return null;
    }

    /**
     * @param  array{page: string, link_key: string, secure_hash: string, rlkey: string}  $share
     * @param  array<int, string>  $hooks
     */
    private function thumbnailHref(array $share, array $hooks): ?string
    {
        $cacheKey = 'vam-dbx-folder:'.sha1($share['link_key'].'|'.$share['secure_hash'].'|'.$share['rlkey']);
        $entries = Cache::get($cacheKey);
        if (! is_array($entries)) {
            $entries = $this->listFolder($share);
            if ($entries !== []) {
                Cache::put($cacheKey, $entries, now()->addMinutes(30));
            }
        }

        return self::pick($entries, $hooks);
    }

    /**
     * @param  array{page: string, link_key: string, secure_hash: string, rlkey: string}  $share
     * @return array<int, array{filename: string, href: string}>
     */
    private function listFolder(array $share): array
    {
        $jar = tempnam(sys_get_temp_dir(), 'dbx');
        if ($jar === false) {
            return [];
        }

        try {
            $page = $this->request($share['page'], $jar);
            if (! $page['ok'] || ! $this->isDropboxHost($this->hostOf($page['effective']))) {
                return [];
            }

            $csrf = $this->cookieValue($jar, '__Host-js_csrf');
            if ($csrf === '') {
                $csrf = $this->cookieValue($jar, 't');
            }
            if ($csrf === '') {
                return [];
            }

            $listed = $this->request(
                'https://www.dropbox.com/list_shared_link_folder_entries',
                $jar,
                http_build_query([
                    't' => $csrf,
                    'link_key' => $share['link_key'],
                    'link_type' => 'c',
                    'secure_hash' => $share['secure_hash'],
                    'sub_path' => '',
                    'rlkey' => $share['rlkey'],
                ]),
                [
                    'Content-Type: application/x-www-form-urlencoded',
                    'X-CSRF-Token: '.$csrf,
                    'Origin: https://www.dropbox.com',
                    'Referer: '.$share['page'],
                ]
            );

            $data = json_decode($listed['body'], true);
            $rows = is_array($data) ? ($data['entries'] ?? null) : null;
            if (! is_array($rows)) {
                return [];
            }

            $compact = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['filename'] ?? ''));
                $href = trim((string) ($row['href'] ?? ''));
                if ($name === '' || $href === '') {
                    continue;
                }
                $compact[] = ['filename' => $name, 'href' => $href];
            }

            return $compact;
        } finally {
            @unlink($jar);
        }
    }

    /**
     * @return array{body: string, mime: string}|null
     */
    private function fetchImage(string $url): ?array
    {
        if (! self::isDropboxUrl($url)) {
            return null;
        }

        $jar = tempnam(sys_get_temp_dir(), 'dbx');
        if ($jar === false) {
            return null;
        }

        try {
            $response = $this->request($url, $jar);
        } finally {
            @unlink($jar);
        }

        if (! $response['ok'] || ! $this->isDropboxHost($this->hostOf($response['effective']))) {
            return null;
        }

        $body = $response['body'];
        if ($body === '' || strlen($body) > self::MAX_IMAGE_BYTES) {
            return null;
        }

        $mime = $this->imageMime($body);
        if ($mime === null) {
            return null;
        }

        return ['body' => $body, 'mime' => $mime];
    }

    /**
     * @return array{mode: string, page: string, link_key: string, secure_hash: string, rlkey: string}|null
     */
    private function classify(string $url): ?array
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\r\n\s]/', $url) || ! preg_match('#^https://#i', $url)) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        if (! $this->isDropboxHost($host)) {
            return null;
        }

        if (! preg_match('#^/scl/(fo|fi)/([A-Za-z0-9_-]{6,})(?:/([^/]+))?(?:/(.+))?$#', $path, $match)) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $rlkey = (string) ($query['rlkey'] ?? '');
        if (! preg_match('/^[A-Za-z0-9_-]{6,}$/', $rlkey)) {
            return null;
        }

        $kind = $match[1];
        $second = rawurldecode((string) ($match[3] ?? ''));
        $rest = rawurldecode((string) ($match[4] ?? ''));
        $fileName = $kind === 'fi' ? $second : $rest;

        if (self::isImageName($fileName)) {
            return [
                'mode' => 'image',
                'page' => $url,
                'link_key' => '',
                'secure_hash' => '',
                'rlkey' => $rlkey,
            ];
        }

        if ($fileName !== '' && preg_match('/\.(mp4|webm|mov|m4v|ogg|ogv|mkv)$/i', $fileName)) {
            return [
                'mode' => 'video',
                'page' => $url,
                'link_key' => '',
                'secure_hash' => '',
                'rlkey' => $rlkey,
            ];
        }

        if ($kind !== 'fo' || ! preg_match('/^[A-Za-z0-9_-]{6,}$/', $second)) {
            return null;
        }

        return [
            'mode' => 'folder',
            'page' => $url,
            'link_key' => $match[2],
            'secure_hash' => $second,
            'rlkey' => $rlkey,
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{ok: bool, body: string, effective: string}
     */
    private function request(string $url, string $jar, ?string $post = null, array $headers = []): array
    {
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_COOKIEJAR => $jar,
            CURLOPT_COOKIEFILE => $jar,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => $headers,
        ];

        $ca = ini_get('curl.cainfo') ?: getenv('SSL_CERT_FILE');
        if (is_string($ca) && $ca !== '' && is_file($ca)) {
            $options[CURLOPT_CAINFO] = $ca;
        }

        if ($post !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $post;
        }

        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effective = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        return [
            'ok' => $body !== false && $status >= 200 && $status < 400,
            'body' => $body === false ? '' : $body,
            'effective' => $effective,
        ];
    }

    private function cookieValue(string $jar, string $name): string
    {
        $raw = @file_get_contents($jar);
        if (! is_string($raw) || $raw === '') {
            return '';
        }

        foreach (preg_split('/\r\n|\n|\r/', $raw) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $cols = explode("\t", $line);
            if (count($cols) >= 7 && $cols[5] === $name && $cols[6] !== '') {
                return $cols[6];
            }
        }

        return '';
    }

    private function rawUrl(string $url): string
    {
        $out = preg_replace('/([?&])dl=[01](&|$)/i', '$1raw=1$2', $url) ?? $url;
        if (! preg_match('/[?&]raw=1(&|$)/i', $out)) {
            $out .= (str_contains($out, '?') ? '&' : '?').'raw=1';
        }

        return $out;
    }

    private static function isImageName(string $name): bool
    {
        return (bool) preg_match('/\.(png|jpe?g|gif|webp)$/i', $name);
    }

    private static function isDropboxUrl(string $url): bool
    {
        if (! preg_match('#^https://#i', $url) || preg_match('/[\r\n\s]/', $url)) {
            return false;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        return $host === 'dropbox.com'
            || $host === 'dropboxusercontent.com'
            || str_ends_with($host, '.dropbox.com')
            || str_ends_with($host, '.dropboxusercontent.com');
    }

    private function isDropboxHost(string $host): bool
    {
        return self::isDropboxUrl('https://'.$host.'/');
    }

    private function hostOf(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }

    private function imageMime(string $body): ?string
    {
        if (str_starts_with($body, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($body, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($body, 'GIF87a') || str_starts_with($body, 'GIF89a')) {
            return 'image/gif';
        }
        if (strlen($body) > 12 && str_starts_with($body, 'RIFF') && substr($body, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return null;
    }

    private static function norm(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/\.(png|jpe?g|gif|webp|mp4|webm|mov|m4v)$/i', '', $value) ?? $value;
        $value = preg_replace('/\b(thumbnail|thumbnails|thumb|hooks|hook|video)\b/', ' ', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private static function score(string $hook, string $file): int
    {
        if ($hook === '' || $file === '') {
            return 0;
        }
        if ($hook === $file) {
            return 100;
        }

        $hookTokens = array_values(array_filter(explode(' ', $hook)));
        $fileTokens = array_values(array_filter(explode(' ', $file)));
        if ($hookTokens === [] || $fileTokens === []) {
            return 0;
        }

        $hookInFile = count(array_diff($hookTokens, $fileTokens)) === 0;
        $fileInHook = count(array_diff($fileTokens, $hookTokens)) === 0;
        if (! $hookInFile && ! $fileInHook) {
            return 0;
        }

        $extra = abs(count($hookTokens) - count($fileTokens));
        if ($extra === 0) {
            return 100;
        }
        if (count($hookTokens) >= 2 && $extra <= 1) {
            return 80;
        }

        return 0;
    }
}

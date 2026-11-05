<?php

namespace App\Support\Marketplace;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The A+ editor (TinyMCE) embeds a picture chosen from the computer as a base64 "data:image/..." string inside the
 * HTML. One photo is hundreds of thousands of characters, so the saved A+ looked huge (605,686 chars) and every
 * page that loads it became slow. Before the HTML is stored, each embedded picture is saved as a file and replaced by
 * its link, which is how a child SKU's A+ (pictures as links) is already stored.
 */
class AplusInlineImages
{
    private const DISK_DIR = 'variation-aplus-images';

    private const EXTENSIONS = [
        'jpeg' => 'jpg',
        'jpg' => 'jpg',
        'pjpeg' => 'jpg',
        'png' => 'png',
        'gif' => 'gif',
        'webp' => 'webp',
    ];

    /**
     * @return string the same HTML with every embedded picture swapped for a link to a stored file
     */
    public static function externalize(string $html): string
    {
        if ($html === '' || stripos($html, 'data:image/') === false) {
            return $html;
        }

        $converted = preg_replace_callback(
            '#data:image/([a-z0-9.+-]+);base64,([A-Za-z0-9+/=\s%]+)#i',
            static function (array $m): string {
                $url = self::store(strtolower($m[1]), $m[2]);

                // A picture that cannot be saved stays as it was, so nothing is lost.
                return $url !== null ? $url : $m[0];
            },
            $html
        );

        return is_string($converted) ? $converted : $html;
    }

    private static function store(string $type, string $encoded): ?string
    {
        $ext = self::EXTENSIONS[$type] ?? null;
        if ($ext === null) {
            return null; // svg and other types are left inside the HTML
        }

        $bytes = base64_decode(preg_replace('/\s+/', '', rawurldecode($encoded)) ?? '', true);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        try {
            // Named after the content, so saving the same picture twice (or for several parents) stores it once.
            $path = self::DISK_DIR.'/'.substr(sha1($bytes), 0, 2).'/'.sha1($bytes).'.'.$ext;
            $disk = Storage::disk('public');
            if (! $disk->exists($path)) {
                $disk->put($path, $bytes);
            }

            return url($disk->url($path));
        } catch (\Throwable $e) {
            Log::warning('A+ inline image could not be stored', ['error' => $e->getMessage()]);

            return null;
        }
    }
}

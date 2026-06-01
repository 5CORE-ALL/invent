<?php

namespace App\Support;

/**
 * Normalize hosted thumbnail URLs so they work in <img src="...">.
 * Dropbox "dl=0" links return HTML; use raw=1 for direct file bytes.
 * Google Drive /file/d/.../view links need uc?export=view.
 */
class VideoThumbnailUrl
{
    public static function normalize(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        // Inline images (paste from clipboard) — never pass through Dropbox/Drive rules
        if (preg_match('#^data:image/#i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        if (preg_match('#drive\.google\.com/file/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
            return 'https://drive.google.com/uc?export=view&id=' . $m[1];
        }

        if (stripos($url, 'dropbox.com') === false) {
            return $url;
        }

        // Shared folder preview — not a single file; cannot be used as an image URL
        if (str_contains($url, '/scl/fo/')) {
            return $url;
        }

        $out = preg_replace('/([?&])dl=0(&|$)/i', '$1raw=1$2', $url) ?? $url;
        $out = preg_replace('/([?&])dl=1(&|$)/i', '$1raw=1$2', $out) ?? $out;

        if (! preg_match('/[?&]raw=1(&|$)/i', $out)) {
            $out .= (str_contains($out, '?') ? '&' : '?') . 'raw=1';
        }

        return $out;
    }
}

<?php

namespace App\Services\Support;

/**
 * Phase 1 (Bullet Points Master): overwrites Shopify product `body_html` with formatted bullets only.
 *
 * Phase 2 (future — Description Master): merge long-form description below this block in the same
 * `body_html` field (bullets at top, description at bottom). Reuse or extend this formatter then.
 */
final class ShopifyBulletPointsFormatter
{
    /**
     * Build HTML for the product description: "Key Features" heading + checklist lines.
     * Replaces entire body in Phase 1; does not append to existing HTML.
     */
    public static function formatBodyHtml(string $bulletPoints): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($bulletPoints));
        $points = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $points[] = $line;
        }

        $html = "<h3>Key Features:</h3>\n<ul class=\"shopify-bullet-points-phase1\">\n";
        foreach ($points as $point) {
            $escaped = htmlspecialchars($point, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= "  <li>✅ {$escaped}</li>\n";
        }
        $html .= '</ul>';

        return $html;
    }

    public static function hasAnyBulletLine(string $bulletPoints): bool
    {
        foreach (preg_split('/\r\n|\r|\n/', trim($bulletPoints)) as $line) {
            if (trim($line) !== '') {
                return true;
            }
        }

        return false;
    }
}

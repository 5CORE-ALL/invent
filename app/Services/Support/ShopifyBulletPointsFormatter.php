<?php

namespace App\Services\Support;

/**
 * Phase 1 (Bullet Points Master): overwrites Shopify product `body_html` with formatted bullets only.
 *
 * Phase 2 (Description Master): merge long-form description below Key Features in the same
 * `body_html` field (bullets at top, description at bottom).
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

    /**
     * Long-form copy as HTML (escaped, line breaks preserved).
     */
    public static function formatLongDescriptionHtml(string $description): string
    {
        $d = trim($description);
        if ($d === '') {
            return '';
        }

        return '<div class="product-description-master">'
            .nl2br(htmlspecialchars($d, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false)
            .'</div>';
    }

    /**
     * Shopify body_html: Key Features block (if any bullet lines) + long description (if non-empty).
     */
    public static function combineBulletPointsAndDescription(string $bulletPointsPlain, string $descriptionPlain): string
    {
        $parts = [];
        if (self::hasAnyBulletLine($bulletPointsPlain)) {
            $parts[] = self::formatBodyHtml($bulletPointsPlain);
        }
        $descHtml = self::formatLongDescriptionHtml($descriptionPlain);
        if ($descHtml !== '') {
            $parts[] = $descHtml;
        }

        return implode("\n\n", array_filter($parts));
    }
}

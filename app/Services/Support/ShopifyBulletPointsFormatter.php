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

    public static function formatAboutItemBlock(string $bulletPoints): string
    {
        $aboutHtml = DescriptionWithImagesFormatter::formatAboutItemHtml($bulletPoints);
        if ($aboutHtml === '') {
            return '';
        }

        return '<!-- bullet-points-master:start -->'."\n"
            .'<div class="bullet-points-master-section" data-bullet-points-master="1">'."\n"
            .'<h3 style="margin-top:0;">About Item</h3>'."\n"
            .'<div class="about-item" style="margin:15px 0; line-height:1.6;">'.$aboutHtml.'</div>'."\n"
            .'</div>'."\n"
            .'<!-- bullet-points-master:end -->';
    }

    public static function replaceAboutItemBlock(string $currentBodyHtml, string $bulletPoints): string
    {
        $replacement = self::formatAboutItemBlock($bulletPoints);
        if ($replacement === '') {
            return $currentBodyHtml;
        }

        $body = trim($currentBodyHtml);
        if ($body === '') {
            return $replacement;
        }

        $patterns = [
            // Stable marker/class added by Bullet Points Master. Prefer this over guessing HTML structure.
            '/<!--\s*bullet-points-master:start\s*-->.*?<!--\s*bullet-points-master:end\s*-->/is',
            '/<div\b(?=[^>]*\bdata-bullet-points-master\s*=\s*(["\'])1\1)(?=[^>]*\bclass\s*=\s*(["\'])(?=[^"\']*\bbullet-points-master-section\b)[^"\']*\2)[^>]*>\s*<h[1-6]\b[^>]*>\s*(?:<[^>]+>\s*)*About\s+Item\s*(?:<\/[^>]+>\s*)*<\/h[1-6]>\s*<div\b[^>]*class\s*=\s*(["\'])(?=[^"\']*\babout-item\b)[^"\']*\3[^>]*>.*?<\/div>\s*<\/div>/is',
            // Current rich layout: About Item heading followed by the about-item div.
            '/<h[1-6]\b[^>]*>\s*(?:<[^>]+>\s*)*About\s+Item\s*(?:<\/[^>]+>\s*)*<\/h[1-6]>\s*<div\b[^>]*class\s*=\s*(["\'])(?=[^"\']*\babout-item\b)[^"\']*\1[^>]*>.*?<\/div>/is',
            // Fallback for Shopify/editor-normalized About Item sections without the expected class.
            '/<h[1-6]\b[^>]*>\s*(?:<[^>]+>\s*)*About\s+Item\s*(?:<\/[^>]+>\s*)*<\/h[1-6]>.*?(?=<h[1-6]\b[^>]*>\s*(?:<[^>]+>\s*)*(?:Product\s+Description|Features?|Images?)\b|$)/is',
            // Older Phase-1 Shopify bullet list layout.
            '/<h[1-6]\b[^>]*>\s*(?:<[^>]+>\s*)*Key\s+Features:?\s*(?:<\/[^>]+>\s*)*<\/h[1-6]>\s*<(?:ul|ol)\b[^>]*class\s*=\s*(["\'])(?=[^"\']*\bshopify-bullet-points-phase1\b)[^"\']*\1[^>]*>.*?<\/(?:ul|ol)>/is',
            // Fallback for generic Key Features sections.
            '/<h[1-6]\b[^>]*>\s*(?:<[^>]+>\s*)*Key\s+Features:?\s*(?:<\/[^>]+>\s*)*<\/h[1-6]>.*?(?=<h[1-6]\b[^>]*>\s*(?:<[^>]+>\s*)*(?:Product\s+Description|About\s+Item|Features?|Images?)\b|$)/is',
            // Legacy "Highlighted Features" heading followed by bracket bullet paragraphs/headings.
            '/<h[1-6]\b[^>]*>(?=[\s\S]*?Highlighted\s+Features)[\s\S]*?<\/h[1-6]>\s*(?:<(?:p|h[1-6])\b[^>]*>(?:(?!<\/(?:p|h[1-6])>)[\s\S])*?【(?:(?!<\/(?:p|h[1-6])>)[\s\S])*?<\/(?:p|h[1-6])>\s*){1,8}/is',
            // Other safe legacy heading names followed by obvious bullet paragraphs/lists.
            '/<h[1-6]\b[^>]*>(?=[\s\S]*?(?:Key\s+Benefits|Product\s+Highlights|Main\s+Features|Bullet\s+Points))[\s\S]*?<\/h[1-6]>\s*(?:(?:<(?:p|h[1-6])\b[^>]*>(?:(?!<\/(?:p|h[1-6])>)[\s\S])*?(?:【|•|\*|-|\d+[.)])(?:(?!<\/(?:p|h[1-6])>)[\s\S])*?<\/(?:p|h[1-6])>\s*){1,8}|<(?:ul|ol)\b[^>]*>[\s\S]*?<\/(?:ul|ol)>)/is',
            // Top-of-description symbol/number bullet paragraphs without a heading.
            '/^\s*(?:<p\b[^>]*>\s*(?:<[^>]+>\s*)*(?:•|\*|-|\d+[.)])[\s\S]*?<\/p>\s*){2,8}/is',
            // Top-of-description simple bullet list before real product content begins.
            '/^\s*<(?:ul|ol)\b[^>]*>[\s\S]*?<\/(?:ul|ol)>\s*/is',
            // Top-of-description About Item + bold-label bullets where the separator is outside <strong>.
            '/^\s*(?:<p\b[^>]*>\s*(?:<[^>]+>\s*)*About\s+Item:?\s*(?:<\/[^>]+>\s*)*<\/p>\s*)?(?:<(?:p|h[1-6])\b[^>]*>\s*(?:<[^>]+>\s*)*<strong\b[^>]*>[^<]{2,120}<\/strong>\s*(?:-|:|–|—)\s*[\s\S]*?<\/(?:p|h[1-6])>\s*){2,8}/is',
            // Top-of-description bold-label bullet paragraphs/headings after a manual link; preserve the manual link.
            '/\A\s*<p\b[^>]*>\s*<a\b[^>]*>[\s\S]*?Download\s+Product\s+Manual[\s\S]*?<\/a>\s*<\/p>\s*\K(?:<(?:p|h[1-6])\b[^>]*>\s*(?:<[^>]+>\s*)*<strong\b[^>]*>[^<]{2,120}(?:-|:|–|—)\s*<\/strong>[\s\S]*?<\/(?:p|h[1-6])>\s*){2,8}/is',
            // Top-of-description bold-label bullet paragraphs/headings without a heading.
            '/^\s*(?:<(?:p|h[1-6])\b[^>]*>\s*(?:<[^>]+>\s*)*<strong\b[^>]*>[^<]{2,120}(?:-|:|–|—)\s*<\/strong>[\s\S]*?<\/(?:p|h[1-6])>\s*){2,8}/is',
            // Amazon A+ style block pasted into Shopify: one center-content div contains About Item + bracket bullets.
            '/<div\b[^>]*class=(["\'])(?=[^"\']*\baplus-3p-center-content\b)[^"\']*\1[^>]*>(?=[\s\S]*?About\s+Item:)(?=[\s\S]*?【)[\s\S]*?<\/div>\s*/is',
            // Legacy paragraph layout generated from markdown-ish descriptions:
            // <p><strong>About Item:</strong></p> followed by bracketed bold bullet paragraphs/headings.
            '/<p\b[^>]*>\s*(?:<[^>]+>\s*)*About\s+Item:?\s*(?:<\/[^>]+>\s*)*<\/p>\s*(?:<(?:p|h[1-6])\b[^>]*>\s*<strong\b[^>]*>\s*【.*?<\/(?:p|h[1-6])>\s*)+/is',
            // Standalone legacy heading left behind after old bullets were removed.
            '/<p\b[^>]*>\s*(?:<[^>]+>\s*)*About\s+Item:?\s*(?:<\/[^>]+>\s*)*<\/p>\s*/is',
            // Legacy bracket-only bullet paragraphs/headings, often directly below the new About Item block.
            // Shopify/Rich Text can wrap the strong tag in spans and add role/dir attributes.
            '/(?:<(?:p|h[1-6])\b[^>]*>(?:(?!<\/(?:p|h[1-6])>)[\s\S])*?【(?:(?!<\/(?:p|h[1-6])>)[\s\S])*?<\/(?:p|h[1-6])>\s*){1,5}/is',
            // Legacy Google-docs style ordered/unordered bullet lists with bracket bullets.
            // These can include empty list items plus one real old bullet in <b><span>【...】</span></b>.
            '/<(?:ol|ul)\b[^>]*>(?=[\s\S]*?【)[\s\S]*?<\/(?:ol|ul)>/is',
            // Empty list shells sometimes remain after Shopify/Google-doc bullet cleanup.
            '/<(?:ol|ul)\b[^>]*>\s*<\/(?:ol|ul)>\s*/is',
        ];

        foreach ($patterns as $pattern) {
            $updated = preg_replace($pattern, $replacement, $body, 1, $count);
            if ($count > 0 && is_string($updated)) {
                // Clean up duplicate legacy blocks that may have been prepended by earlier narrow matches.
                foreach ($patterns as $cleanupPattern) {
                    $cleaned = preg_replace($cleanupPattern, '', $updated, -1);
                    if (is_string($cleaned)) {
                        $updated = $cleaned;
                    }
                }

                return self::keepDownloadManualBeforeBulletBlock($replacement."\n".trim($updated));
            }
        }

        return self::keepDownloadManualBeforeBulletBlock($replacement."\n".$body);
    }

    /**
     * Read-only extraction for Shopify -> Product Master audits/imports.
     *
     * @return array{bullets: list<string>, format: string, confidence: int, notes: list<string>}
     */
    public static function extractBulletPointsForImport(string $bodyHtml): array
    {
        $body = trim($bodyHtml);
        if ($body === '') {
            return ['bullets' => [], 'format' => 'empty', 'confidence' => 0, 'notes' => ['Empty Shopify body_html']];
        }

        $candidates = [
            ['format' => 'marked_master_block', 'confidence' => 100, 'html' => self::matchFirst($body, '/<!--\s*bullet-points-master:start\s*-->([\s\S]*?)<!--\s*bullet-points-master:end\s*-->/is')],
            ['format' => 'about_item_div', 'confidence' => 90, 'html' => self::matchFirst($body, '/<h[1-6]\b[^>]*>(?=[\s\S]*?About\s+Item)[\s\S]*?<\/h[1-6]>\s*<div\b[^>]*class\s*=\s*(["\'])(?=[^"\']*\babout-item\b)[^"\']*\1[^>]*>([\s\S]*?)<\/div>/is', 2)],
            ['format' => 'aplus_about_item_block', 'confidence' => 82, 'html' => self::matchFirst($body, '/<div\b[^>]*class=(["\'])(?=[^"\']*\baplus-3p-center-content\b)[^"\']*\1[^>]*>(?=[\s\S]*?About\s+Item:)(?=[\s\S]*?【)([\s\S]*?)<\/div>/is', 2)],
            ['format' => 'about_item_bracket_paragraphs', 'confidence' => 80, 'html' => self::matchFirst($body, '/<p\b[^>]*>\s*(?:<[^>]+>\s*)*About\s+Item:?\s*(?:<\/[^>]+>\s*)*<\/p>\s*((?:<p\b[^>]*>(?:(?!<\/p>)[\s\S])*?【(?:(?!<\/p>)[\s\S])*?<\/p>\s*){1,8})/is', 1)],
            ['format' => 'highlighted_features', 'confidence' => 78, 'html' => self::matchFirst($body, '/<h[1-6]\b[^>]*>(?=[\s\S]*?(?:Highlighted\s+Features|Key\s+Benefits|Product\s+Highlights|Main\s+Features|Bullet\s+Points))[\s\S]*?<\/h[1-6]>\s*((?:<p\b[^>]*>[\s\S]*?<\/p>\s*){1,8})/is', 1)],
            ['format' => 'top_bracket_paragraphs_spaced', 'confidence' => 76, 'html' => self::matchFirst($body, '/\A\s*((?=[\s\S]*?【)(?:<(?:p|h[1-6])\b[^>]*>(?:(?!<\/(?:p|h[1-6])>)[\s\S])*?(?:【|&nbsp;|\x{00a0})(?:(?!<\/(?:p|h[1-6])>)[\s\S])*?<\/(?:p|h[1-6])>\s*){2,12})/isu', 1)],
            ['format' => 'top_bracket_paragraphs', 'confidence' => 75, 'html' => self::matchFirst($body, '/\A\s*((?:<(?:p|h[1-6])\b[^>]*>(?:(?!<\/(?:p|h[1-6])>)[\s\S])*?【(?:(?!<\/(?:p|h[1-6])>)[\s\S])*?<\/(?:p|h[1-6])>\s*){1,8})/is', 1)],
            ['format' => 'top_bold_label_paragraphs', 'confidence' => 72, 'html' => self::matchFirst($body, '/\A\s*(?:<p\b[^>]*>\s*<a\b[^>]*>[\s\S]*?Download\s+Product\s+Manual[\s\S]*?<\/a>\s*<\/p>\s*)?((?:<p\b[^>]*>\s*(?:<[^>]+>\s*)*<strong\b[^>]*>[^<]{2,120}(?:-|:|–|—)\s*<\/strong>[\s\S]*?<\/p>\s*){2,8})/is', 1)],
            ['format' => 'top_list', 'confidence' => 68, 'html' => self::matchFirst($body, '/\A\s*(<(?:ul|ol)\b[^>]*>[\s\S]*?<\/(?:ul|ol)>)/is', 1)],
        ];

        foreach ($candidates as $candidate) {
            $html = trim((string) ($candidate['html'] ?? ''));
            if ($html === '') {
                continue;
            }

            $bullets = self::extractBulletsFromHtmlFragment($html);
            if ($bullets !== []) {
                if (($candidate['format'] ?? '') === 'about_item_bracket_paragraphs' && count($bullets) < 5) {
                    $mixedBullets = self::extractAboutItemMixedParagraphBullets($body);
                    if (count($mixedBullets) > count($bullets)) {
                        return [
                            'bullets' => $mixedBullets,
                            'format' => 'about_item_mixed_paragraphs',
                            'confidence' => 79,
                            'notes' => ['Extracted from mixed About Item bracket and bold-label paragraphs'],
                        ];
                    }
                }
                if (($candidate['format'] ?? '') === 'top_bold_label_paragraphs' && count($bullets) < 5) {
                    continue;
                }
                return [
                    'bullets' => $bullets,
                    'format' => (string) $candidate['format'],
                    'confidence' => (int) $candidate['confidence'],
                    'notes' => [],
                ];
            }
        }

        $topBoldLabelBullets = self::extractTopBoldLabelParagraphBullets($body);
        if ($topBoldLabelBullets !== []) {
            return [
                'bullets' => $topBoldLabelBullets,
                'format' => 'top_bold_label_paragraphs',
                'confidence' => 72,
                'notes' => ['Extracted from leading bold-label paragraphs'],
            ];
        }

        $plainBullets = self::extractLegacyTextBullets($body);
        if ($plainBullets !== []) {
            return [
                'bullets' => $plainBullets,
                'format' => 'legacy_text_bullets',
                'confidence' => 65,
                'notes' => ['Extracted from plain text / line-break legacy bullet block'],
            ];
        }

        return ['bullets' => [], 'format' => 'not_detected', 'confidence' => 0, 'notes' => ['No supported Shopify bullet format detected']];
    }

    /**
     * @return list<string>
     */
    private static function extractLegacyTextBullets(string $bodyHtml): array
    {
        $withBreaks = preg_replace('/<br\s*\/?>/i', "\n", $bodyHtml) ?? $bodyHtml;
        $withBreaks = preg_replace('/<\/(?:p|div|li|h[1-6])\s*>/i', "\n", $withBreaks) ?? $withBreaks;
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $pos = false;
        $matchedHeading = '';
        foreach (['Highlighted Features', 'Key Features', 'About Item'] as $heading) {
            $candidate = mb_stripos($text, $heading);
            if ($candidate !== false && ($pos === false || $candidate < $pos)) {
                $pos = $candidate;
                $matchedHeading = $heading;
            }
        }
        if ($pos === false) {
            $pos = 0;
        }

        $section = mb_substr($text, $pos);
        $stopWords = ['Product Description', 'Specification', 'Package Information', 'Package', 'About Brand'];
        foreach ($stopWords as $stopWord) {
            $stopOffset = min(20, mb_strlen($section));
            $stopPos = mb_stripos($section, $stopWord, $stopOffset);
            if ($stopPos !== false) {
                $section = mb_substr($section, 0, $stopPos);
                break;
            }
        }

        preg_match_all('/【\s*(.+?)\s*】\s*([^【]+)/u', $section, $matches, PREG_SET_ORDER);
        $bullets = [];
        foreach ($matches as $match) {
            $label = trim(preg_replace('/\s+/u', ' ', (string) ($match[1] ?? '')) ?? '');
            $body = trim(preg_replace('/\s+/u', ' ', (string) ($match[2] ?? '')) ?? '');
            $body = preg_replace('/^(?:-|–|—)\s*/u', '', $body) ?? $body;
            $body = preg_split('/(?:\s+|(?<=[.!?]))(?:\d{4}_{2,}|[Pp]roduct\s+[Dd]escription\b|[A-Z][A-Za-z0-9& -]{2,80}\s+[Dd]escription\b|[A-Z][A-Za-z0-9& -]{2,80}\s+Features\b|[Ss]pecifications?\b|[Ss]pecification\b|[Pp]ackage\s+[Ii]nformation\b|[Aa]bout\s+[Bb]rand\b)/u', $body, 2)[0] ?? $body;
            $body = trim($body);
            if ($label !== '') {
                $bullets[] = trim($label.($body !== '' ? ' - '.$body : ''));
            }
        }

        if ($bullets === []) {
            $lines = preg_split('/\n+/', $section) ?: [];
            foreach ($lines as $line) {
                $line = trim(preg_replace('/\s+/u', ' ', (string) $line) ?? (string) $line);
                if ($line === '' || preg_match('/^(Highlighted\s+Features|Key\s+Features|About\s+Item)\s*:?\s*$/i', $line)) {
                    continue;
                }
                $line = trim(preg_replace('/^(?:[-*•●▪✅✔✓☑]+|\d+[.)])\s*/u', '', $line) ?? $line);
                $line = trim(preg_replace('/^(?:[-*•●▪✅✔✓☑]+)\s*/u', '', $line) ?? $line);
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^(.{2,120}?)(?:\s+-\s+|\s+–\s+|\s+—\s+)(.+)$/u', $line, $m) === 1) {
                    $bullets[] = trim($m[1]).' - '.trim($m[2]);
                } elseif (preg_match('/^([A-Z0-9][^:]{2,100}?):\s*(.{8,})$/u', $line, $m) === 1) {
                    $bullets[] = trim($m[1]).' - '.trim($m[2]);
                } elseif ($matchedHeading !== '' && mb_strlen($line) >= 12 && mb_strlen($line) <= 260) {
                    $bullets[] = $line;
                }
                if (count($bullets) >= 5) {
                    break;
                }
            }
        }

        return array_values(array_slice(array_unique($bullets), 0, 5));
    }

    private static function matchFirst(string $html, string $pattern, int $group = 1): string
    {
        if (preg_match($pattern, $html, $m) !== 1) {
            return '';
        }

        return (string) ($m[$group] ?? '');
    }

    /**
     * @return list<string>
     */
    private static function extractTopBoldLabelParagraphBullets(string $html): array
    {
        $remaining = ltrim($html);
        if (preg_match('/\A<p\b[^>]*>\s*<a\b[^>]*>[\s\S]*?Download\s+Product\s+Manual[\s\S]*?<\/a>\s*<\/p>\s*/i', $remaining, $manual) === 1) {
            $remaining = substr($remaining, strlen($manual[0]));
        }
        if (preg_match('/\A\s*<(?:p|h[1-6])\b[^>]*>\s*(?:<[^>]+>\s*)*About\s+Item:?\s*(?:<\/[^>]+>\s*)*<\/(?:p|h[1-6])>\s*/i', $remaining, $heading) === 1) {
            $remaining = substr($remaining, strlen($heading[0]));
        }

        $bullets = [];
        while (count($bullets) < 5 && preg_match('/\A\s*(<(?:p|h[1-6])\b[^>]*>[\s\S]*?<\/(?:p|h[1-6])>)/iu', $remaining, $paragraphMatch) === 1) {
            $paragraph = (string) $paragraphMatch[1];
            $paragraphBullets = self::extractBoldLabelBulletsFromParagraph($paragraph);
            if ($paragraphBullets !== []) {
                foreach ($paragraphBullets as $paragraphBullet) {
                    $bullets[] = $paragraphBullet;
                    if (count($bullets) >= 5) {
                        break;
                    }
                }
                $remaining = substr($remaining, strlen($paragraphMatch[0]));
                continue;
            }

            if (preg_match('/<strong\b[^>]*>([\s\S]*?)(?:\s*(?:-|:|–|—)\s*)?<\/strong>\s*((?:-|:|–|—)\s*)?([\s\S]*)/iu', $paragraph, $m) !== 1) {
                $plainBullet = self::extractPlainColonBulletFromParagraph($paragraph);
                if ($plainBullet === '') {
                    break;
                }

                $bullets[] = $plainBullet;
                $remaining = substr($remaining, strlen($paragraphMatch[0]));
                continue;
            }

            $label = trim(html_entity_decode(strip_tags((string) $m[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $body = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i', ' ', (string) $m[3]) ?? (string) $m[3]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $label = trim(preg_replace('/\s+/u', ' ', $label) ?? $label);
            $body = trim(preg_replace('/\s+/u', ' ', $body) ?? $body);
            $body = trim(preg_replace('/^(?:-|:|–|—)\s*/u', '', $body) ?? $body);
            $body = preg_split('/(?:\s+|(?<=[.!?]))(?:\d{4}_{2,}|[Pp]roduct\s+[Dd]escription\b|[A-Z][A-Za-z0-9& -]{2,80}\s+[Dd]escription\b|[A-Z][A-Za-z0-9& -]{2,80}\s+Features\b|[Ss]pecifications?\b|[Ss]pecification\b|[Pp]ackage\s+[Ii]nformation\b|[Aa]bout\s+[Bb]rand\b)/u', $body, 2)[0] ?? $body;
            $body = trim($body);

            if ($label === '' || $body === '') {
                break;
            }

            $bullets[] = $label.' - '.$body;
            $remaining = substr($remaining, strlen($paragraphMatch[0]));
        }

        return count($bullets) >= 2 ? array_values(array_unique($bullets)) : [];
    }

    /**
     * @return list<string>
     */
    private static function extractAboutItemMixedParagraphBullets(string $html): array
    {
        if (preg_match('/<p\b[^>]*>\s*(?:<[^>]+>\s*)*About\s+Item:?\s*(?:<\/[^>]+>\s*)*<\/p>\s*([\s\S]*)/iu', $html, $start) !== 1) {
            return [];
        }

        $remaining = (string) $start[1];
        $bullets = [];
        while (count($bullets) < 5 && preg_match('/\A\s*(<(?:p|h[1-6])\b[^>]*>[\s\S]*?<\/(?:p|h[1-6])>)/iu', $remaining, $paragraphMatch) === 1) {
            $paragraph = (string) $paragraphMatch[1];
            $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '');
            if ($plain === '' || preg_match('/^(?:Product\s+Description|Specifications?|Package\s+Information|About\s+Brand)\b/iu', $plain)) {
                break;
            }

            $paragraphBullets = self::extractBulletLinesFromItem($paragraph);
            if ($paragraphBullets === []) {
                break;
            }

            foreach ($paragraphBullets as $paragraphBullet) {
                if (preg_match('/^(?:Product\s+Description|Specifications?|Package\s+Information|About\s+Brand)\b/iu', $paragraphBullet)) {
                    break 2;
                }
                $bullets[] = $paragraphBullet;
                if (count($bullets) >= 5) {
                    break 2;
                }
            }

            $remaining = substr($remaining, strlen($paragraphMatch[0]));
        }

        return count($bullets) >= 3 ? array_values(array_unique($bullets)) : [];
    }

    /**
     * @return list<string>
     */
    private static function extractBoldLabelBulletsFromParagraph(string $paragraph): array
    {
        if (preg_match_all('/<strong\b[^>]*>([\s\S]*?)<\/strong>\s*((?:-|:|–|—)\s*)?([\s\S]*?)(?=<strong\b|$)/iu', $paragraph, $matches, PREG_SET_ORDER) < 1) {
            return [];
        }

        $bullets = [];
        foreach ($matches as $match) {
            $label = trim(html_entity_decode(strip_tags((string) ($match[1] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $bodyHtml = preg_replace('/<br\s*\/?>/i', ' ', (string) ($match[3] ?? '')) ?? (string) ($match[3] ?? '');
            $body = trim(html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $label = trim(preg_replace('/\s+/u', ' ', $label) ?? $label);
            $body = trim(preg_replace('/\s+/u', ' ', $body) ?? $body);
            $label = trim(preg_replace('/\s*(?:-|:|–|—)\s*$/u', '', $label) ?? $label);
            $body = trim(preg_replace('/^(?:-|:|–|—)\s*/u', '', $body) ?? $body);
            $body = preg_split('/(?:\s+|(?<=[.!?]))(?:\d{4}_{2,}|[Pp]roduct\s+[Dd]escription\b|[A-Z][A-Za-z0-9& -]{2,80}\s+[Dd]escription\b|[A-Z][A-Za-z0-9& -]{2,80}\s+Features\b|[Ss]pecifications?\b|[Ss]pecification\b|[Pp]ackage\s+[Ii]nformation\b|[Aa]bout\s+[Bb]rand\b)/u', $body, 2)[0] ?? $body;
            $body = trim($body);

            if ($label === '' || $body === '' || preg_match('/^(?:About\s+Item|Product\s+Description)$/iu', $label)) {
                continue;
            }

            $bullets[] = $label.' - '.$body;
        }

        return array_values(array_unique($bullets));
    }

    private static function extractPlainColonBulletFromParagraph(string $paragraph): string
    {
        $withoutImages = preg_replace('/<img\b[\s\S]*$/iu', '', $paragraph) ?? $paragraph;
        $plain = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i', ' ', $withoutImages) ?? $withoutImages), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);
        if ($plain === '' || preg_match('/^(?:Product\s+Description|Features|Product\s+information|Package\s+information)\b/iu', $plain)) {
            return '';
        }

        if (preg_match('/^([A-Z0-9][A-Za-z0-9& .\/+-]{2,100}?):\s*(.{8,})$/u', $plain, $m) !== 1) {
            return '';
        }

        $label = trim((string) $m[1]);
        $body = trim((string) $m[2]);
        if ($label === '' || $body === '') {
            return '';
        }

        return $label.' - '.$body;
    }

    /**
     * @return list<string>
     */
    private static function extractBulletsFromHtmlFragment(string $html): array
    {
        $items = [];
        if (preg_match_all('/<li\b[^>]*>([\s\S]*?)<\/li>/is', $html, $m) && ! empty($m[1])) {
            $items = $m[1];
        } elseif (preg_match_all('/<(?:p|h[1-6])\b[^>]*>([\s\S]*?)<\/(?:p|h[1-6])>/is', $html, $m) && ! empty($m[1])) {
            $items = $m[1];
        } else {
            $items = preg_split('/<br\s*\/?>/i', $html) ?: [];
        }

        $bullets = [];
        foreach ($items as $item) {
            foreach (self::extractBulletLinesFromItem((string) $item) as $line) {
                if ($line !== '') {
                    $bullets[] = $line;
                }
            }
        }

        return array_values(array_slice(array_unique($bullets), 0, 5));
    }

    /**
     * @return list<string>
     */
    private static function extractBulletLinesFromItem(string $itemHtml): array
    {
        $withBreaks = preg_replace('/<br\s*\/?>/i', "\n", $itemHtml) ?? $itemHtml;
        $plain = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $plain = str_replace("\xc2\xa0", ' ', $plain);
        $plain = preg_replace('/[ \t]+/', ' ', $plain) ?? $plain;
        $plain = trim($plain);
        if ($plain === '' || preg_match('/^(About\s+Item|Highlighted\s+Features|Download\s+Product\s+Manual)\s*:?\s*$/i', $plain)) {
            return [];
        }

        $plain = preg_replace('/^\s*About\s+Item:?\s*/iu', '', $plain) ?? $plain;

        $parts = preg_split('/\n+/', $plain) ?: [$plain];
        $lines = [];
        foreach ($parts as $part) {
            $line = trim(preg_replace('/\s+/', ' ', (string) $part) ?? (string) $part);
            if ($line === '' || preg_match('/^(About\s+Item|Highlighted\s+Features)\s*:?\s*$/i', $line)) {
                continue;
            }

            if (substr_count($line, '【') > 1 && preg_match_all('/【\s*(.+?)\s*】\s*([^【]+)/u', $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $label = trim(preg_replace('/\s+/u', ' ', (string) ($match[1] ?? '')) ?? '');
                    $body = trim(preg_replace('/\s+/u', ' ', (string) ($match[2] ?? '')) ?? '');
                    $body = preg_split('/\s+(?:\d{4}_{2,}|[Pp]roduct\s+[Dd]escription\b|[A-Z][A-Za-z0-9& -]{2,80}\s+[Dd]escription\b|[A-Z][A-Za-z0-9& -]{2,80}\s+Features\b|[Ss]pecifications?\b|[Ss]pecification\b|[Pp]ackage\s+[Ii]nformation\b|[Aa]bout\s+[Bb]rand\b)/u', $body, 2)[0] ?? $body;
                    if ($label !== '') {
                        $lines[] = trim($label.($body !== '' ? ' - '.$body : ''));
                    }
                }
                continue;
            }

            if (preg_match('/^【\s*(.+?)\s*】\s*(.+)$/u', $line, $m)) {
                $label = trim((string) $m[1]);
                $body = trim((string) $m[2]);
                $line = $body !== '' ? "{$label} - {$body}" : $label;
            }

            $line = trim(preg_replace('/^(?:[-*•●▪✅✔✓]|\d+[.)])\s*/u', '', $line) ?? $line);
            if ($line !== '' && ! str_contains(mb_strtolower($line), 'download product manual')) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private static function keepDownloadManualBeforeBulletBlock(string $html): string
    {
        $manualLinkPattern = '<p\b[^>]*>\s*<a\b[^>]*>[\s\S]*?Download\s+Product\s+Manual[\s\S]*?<\/a>\s*<\/p>';
        $markedBlockPattern = '<!--\s*bullet-points-master:start\s*-->[\s\S]*?<!--\s*bullet-points-master:end\s*-->';
        $pattern = '/\A\s*('.$markedBlockPattern.')\s*('.$manualLinkPattern.')/is';

        $updated = preg_replace($pattern, "$2\n$1", $html, 1);
        return is_string($updated) ? trim($updated) : $html;
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

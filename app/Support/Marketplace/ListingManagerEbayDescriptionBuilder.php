<?php

namespace App\Support\Marketplace;

/**
 * Build LitCommerce-style eBay description HTML (text + interleaved images).
 */
class ListingManagerEbayDescriptionBuilder
{
    /**
     * @param  list<string>  $images
     * @param  list<string>  $bullets
     */
    public static function optimize(string $source, array $images = [], string $title = '', array $bullets = []): string
    {
        $images = array_values(array_filter(array_map(fn ($u) => trim((string) $u), $images), fn ($u) => $u !== '' && preg_match('#^https?://#i', $u)));
        $plain = self::toPlain($source);
        if ($plain === '' && $title === '' && $images === []) {
            return '';
        }

        $sections = self::splitFeatureSections($plain);
        $specs = self::extractSpecsBlock($plain);
        $package = self::extractPackageBlock($plain);
        $about = self::extractAboutBlock($plain);

        // Feature boxes from bullets or first short sections
        $featureBoxes = self::featureBoxes($bullets, $sections);

        $parts = [];
        $parts[] = '<div class="ebay-listing-description" style="font-family:Arial,Helvetica,sans-serif;color:#222;line-height:1.55;max-width:1000px;margin:0 auto;">';

        // Headline feature paragraphs (bold title + body), with images interleaved
        $imgIdx = 0;
        $sectionCount = 0;
        foreach ($sections as $section) {
            $sectionCount++;
            $h = htmlspecialchars($section['heading'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $b = htmlspecialchars($section['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $parts[] = '<p style="margin:0 0 14px 0;font-size:15px;">'
                .'<strong style="font-size:16px;text-transform:uppercase;">'.$h.'</strong>'
                .' – '.$b
                .'</p>';

            // Insert a full-width image after every 1–2 feature blocks
            if ($images !== [] && ($sectionCount === 1 || $sectionCount % 2 === 0) && isset($images[$imgIdx])) {
                $parts[] = self::imageBlock($images[$imgIdx], $title !== '' ? $title : 'Product image', $imgIdx + 1);
                $imgIdx++;
            }
        }

        if ($sections === [] && $plain !== '') {
            foreach (preg_split('/\n{2,}/', $plain) ?: [] as $para) {
                $para = trim($para);
                if ($para === '') {
                    continue;
                }
                $parts[] = '<p style="margin:0 0 12px 0;font-size:15px;">'
                    .nl2br(htmlspecialchars($para, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false)
                    .'</p>';
            }
            if ($images !== [] && isset($images[0])) {
                $parts[] = self::imageBlock($images[0], $title !== '' ? $title : 'Product image', 1);
                $imgIdx = 1;
            }
        }

        if ($featureBoxes !== []) {
            $parts[] = self::featuresGridHtml($featureBoxes);
            if ($images !== [] && isset($images[$imgIdx])) {
                $parts[] = self::imageBlock($images[$imgIdx], $title !== '' ? $title : 'Product image', $imgIdx + 1);
                $imgIdx++;
            }
        }

        if ($specs !== []) {
            $parts[] = '<h3 style="margin:22px 0 10px;font-size:18px;">Car Speaker Specification:</h3>';
            $parts[] = '<ul style="margin:0 0 16px 18px;padding:0;">';
            foreach ($specs as $line) {
                $parts[] = '<li style="margin:0 0 6px;">'.htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>';
            }
            $parts[] = '</ul>';
        }

        if ($package !== []) {
            $parts[] = '<h3 style="margin:18px 0 10px;font-size:18px;">Package Includes:</h3>';
            $parts[] = '<ul style="margin:0 0 16px 18px;padding:0;">';
            foreach ($package as $line) {
                $parts[] = '<li style="margin:0 0 6px;">'.htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>';
            }
            $parts[] = '</ul>';
        }

        // Remaining images
        while (isset($images[$imgIdx])) {
            $parts[] = self::imageBlock($images[$imgIdx], $title !== '' ? $title : 'Product image', $imgIdx + 1);
            $imgIdx++;
        }

        if ($about !== '') {
            $parts[] = '<h3 style="margin:22px 0 10px;font-size:18px;">About Brand</h3>';
            $parts[] = '<p style="margin:0 0 12px;font-size:15px;">'
                .nl2br(htmlspecialchars($about, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false)
                .'</p>';
        }

        $parts[] = '</div>';

        return implode("\n", $parts);
    }

    private static function imageBlock(string $url, string $alt, int $n): string
    {
        $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeAlt = htmlspecialchars(trim($alt) !== '' ? "{$alt} {$n}" : "Product Image {$n}", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<div style="margin:18px 0;text-align:center;">'
            .'<img src="'.$safeUrl.'" alt="'.$safeAlt.'" style="max-width:100%;height:auto;border:0;display:inline-block;">'
            .'</div>';
    }

    /**
     * @param  list<array{title: string, body: string}>  $boxes
     */
    private static function featuresGridHtml(array $boxes): string
    {
        $cells = [];
        foreach (array_slice($boxes, 0, 4) as $box) {
            $t = htmlspecialchars($box['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $b = htmlspecialchars($box['body'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $cells[] = '<td style="width:25%;vertical-align:top;padding:10px 12px;border:1px solid #e5e7eb;">'
                .'<div style="font-weight:700;text-transform:uppercase;font-size:13px;margin-bottom:6px;">'.$t.'</div>'
                .'<div style="font-size:13px;color:#374151;">'.$b.'</div>'
                .'</td>';
        }
        while (count($cells) < 4) {
            $cells[] = '<td style="width:25%;padding:10px;"></td>';
        }

        return '<table class="features-grid" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:18px 0;">'
            .'<tr>'.implode('', $cells).'</tr></table>';
    }

    private static function toPlain(string $source): string
    {
        $source = trim($source);
        if ($source === '') {
            return '';
        }
        // Prefer text inside previous optimizer wrappers
        if (preg_match('/class="ebay-listing-description"/i', $source)) {
            $source = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ["\n", "\n", "\n", "\n", "\n"], $source));
        } elseif (preg_match('/<[^>]+>/', $source)) {
            $source = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n\n"], $source)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        // Amazon often glues next ALL-CAPS headline onto previous sentence: "sound.CLEAR SOUND"
        $source = preg_replace('/([a-z0-9\)\]\.])([A-Z]{3,}[A-Z0-9 &\/\-]{0,40}\s*[–\-:—])/u', "$1\n$2", $source) ?? $source;
        $source = preg_replace('/\b(Woofer Description|Car Speaker Specification|Package Includes|About Brand)\b/u', "\n$1\n", $source) ?? $source;
        $source = preg_replace("/[ \t]+/u", ' ', $source) ?? $source;
        $source = preg_replace("/\n{3,}/u", "\n\n", $source) ?? $source;

        return trim($source);
    }

    /**
     * @return list<array{heading: string, body: string}>
     */
    private static function splitFeatureSections(string $plain): array
    {
        $sections = [];
        // Split into lines first so glued Amazon copy becomes separate feature rows
        $lines = preg_split('/\n+/', $plain) ?: [$plain];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (! preg_match('/^([A-Z][A-Z0-9][A-Z0-9 &\/\']{2,70})\s*[–\-:—]\s*(.+)$/u', $line, $m)) {
                // Also allow mid-line matches for remaining glued copy
                if (preg_match_all('/\b([A-Z][A-Z0-9][A-Z0-9 &\/\']{2,70})\s*[–\-:—]\s*([^A-Z\n]+?(?=\b[A-Z]{3,}[A-Z0-9 &\/\']{2,}\s*[–\-:—]|$))/u', $line, $mm, PREG_SET_ORDER)) {
                    foreach ($mm as $row) {
                        self::pushSection($sections, $row[1], $row[2]);
                    }
                }
                continue;
            }
            self::pushSection($sections, $m[1], $m[2]);
        }

        return $sections;
    }

    /**
     * @param  list<array{heading: string, body: string}>  $sections
     */
    private static function pushSection(array &$sections, string $heading, string $body): void
    {
        $heading = trim($heading);
        $body = trim($body);
        if ($heading === '' || $body === '') {
            return;
        }
        if (preg_match('/^(WOOFER DESCRIPTION|CAR SPEAKER SPECIFICATION|PACKAGE INCLUDES|ABOUT BRAND|SPECIFICATION)/i', $heading)) {
            return;
        }
        $sections[] = ['heading' => $heading, 'body' => $body];
    }

    /**
     * @return list<string>
     */
    private static function extractSpecsBlock(string $plain): array
    {
        if (! preg_match('/(?:Car\s+Speaker\s+)?Specification[s]?\s*:?\s*(.+?)(?=Package\s+Includes|About\s+Brand|$)/is', $plain, $m)) {
            return [];
        }
        $chunk = trim($m[1]);
        $lines = preg_split('/[\n•]+|(?<=\.)\s+(?=[A-Z])/', $chunk) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B-•");
            if (strlen($line) > 3 && strlen($line) < 180) {
                $out[] = $line;
            }
        }

        return array_slice($out, 0, 20);
    }

    /**
     * @return list<string>
     */
    private static function extractPackageBlock(string $plain): array
    {
        if (! preg_match('/Package\s+Includes?\s*:?\s*(.+?)(?=About\s+Brand|Simple\s+Replacement|$)/is', $plain, $m)) {
            return [];
        }
        $chunk = trim($m[1]);
        $lines = preg_split('/[\n•]+/', $chunk) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B-•");
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return array_slice($out, 0, 12);
    }

    private static function extractAboutBlock(string $plain): string
    {
        if (preg_match('/About\s+Brand\s*:?\s*(.+)$/is', $plain, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    /**
     * @param  list<string>  $bullets
     * @param  list<array{heading: string, body: string}>  $sections
     * @return list<array{title: string, body: string}>
     */
    private static function featureBoxes(array $bullets, array $sections): array
    {
        $boxes = [];
        foreach ($bullets as $b) {
            $b = trim((string) $b);
            if ($b === '') {
                continue;
            }
            if (preg_match('/^(.{3,40}?)[\.:\-–—]\s*(.+)$/u', $b, $m)) {
                $boxes[] = ['title' => trim($m[1]), 'body' => trim($m[2])];
            } else {
                $words = preg_split('/\s+/', $b) ?: [];
                $title = implode(' ', array_slice($words, 0, 4));
                $boxes[] = ['title' => mb_strtoupper($title), 'body' => $b];
            }
            if (count($boxes) >= 4) {
                return $boxes;
            }
        }
        foreach (array_slice($sections, 0, 4) as $s) {
            $boxes[] = ['title' => $s['heading'], 'body' => $s['body']];
            if (count($boxes) >= 4) {
                break;
            }
        }

        return array_slice($boxes, 0, 4);
    }
}
